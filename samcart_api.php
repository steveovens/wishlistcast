<?php
/*
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

  You may also view the license here:
  http://www.gnu.org/licenses/gpl.html#SEC1
 */

/*
  INSTALLATION PROCEDURE:

 * Upload the entire wishlistcast directory to your plugins directory
 * Login to the WP admin
 * Go to plugins tab and activate the plugin

 */
 
// Read SAMCart JSON POST data
$inputDataJSON = file_get_contents('php://input');

if ($inputDataJSON === FALSE) return; // No JSON POST data to process - not called from SAMCart so ignore

$inputData = json_decode($inputDataJSON);

// Note: Using array_merge so only have POST/GET variables (default behaviour includes cookies)
// This also allows override of nanacast POST vars with user-defined GET vars
$f = array_merge( $_POST, $_GET );

// Translate SAMCart request into pseudo-nanacast request for processing
$f['u_firstname'] = $inputData->{'customer'}->{'first_name'};
$f['u_lastname'] = $inputData->{'customer'}->{'last_name'};
$f['u_email'] = $inputData->{'customer'}->{'email'};
$f['id'] = $inputData->{'order'}->{'stripe_id'};
$f['mode'] = 'add';
$f['u_address1'] = $inputData->{'customer'}->{'billing_address'};
$f['u_city'] = $inputData->{'customer'}->{'billing_city'};
$f['u_state'] = $inputData->{'customer'}->{'billing_state'};
$f['u_zip'] = $inputData->{'customer'}->{'billing_zip'};
$f['u_country'] = $inputData->{'customer'}->{'billing_country'};
$f['u_phone'] = $inputData->{'customer'}->{'phone_number'};

if ( !array_key_exists( 'mode', $f ) || $f['mode'] == '' ) {
    return; // Not executing as a result of being called from Nanacast - probably plugin activation
}

include_once('../../../wp-config.php');

if ( !defined( 'ABSPATH' ) ) // samcart-api.php could be in plugins/ or plugins/wishlistcast/
    include_once('../../wp-config.php');

include_once(ABSPATH . 'wp-admin/includes/admin.php');
include_once(ABSPATH . 'wp-admin/includes/user.php');
include_once(ABSPATH . 'wp-includes/functions.php');

if ( !class_exists( 'Log' ) )
    require_once('includes/Log.php');

// v1.4.7 Initialise action - used this to trigger initialisation of extensions
do_action('wishlistcast_api_init');

$logger = null;
if ( 'true' === get_option( 'wlc_logging' ) && defined( 'PEAR_LOG_DEBUG' ) ) {
    $logger = Log::Singleton( 'file', 'samcart_api.log', 'WLC' );
}

// v1.4.7 Add hook to filter initial parameters
$f = apply_filters( 'wishlistcast_api_filter_params', $f );

if ( isset( $f['verify_field'] ) && isset( $f['verify_value'] ) && ($f[$f['verify_field']] != $f['verify_value']) ) {
    logmsgandClose( 'Request ignored. Verification failed: ' . $f['verify_field'] . ' value is [' . $f[$f['verify_field']] . ']. Verification value is [' . $f['verify_value'] . ']' );
    return;
}

// Check secret key matches Wishlist Generic secret key from Wishlist Integration panel
if ( class_exists( 'WLMAPI' ) ) {
    $wishlist_security_code = WLMAPI::GetOption( 'genericsecret' );
    if ( $wishlist_security_code != '' && $wishlist_security_code != $f['security_code'] ) {
        logmsg( "Incoming Secret Key '" . $f['security_code'] . "' does not match the Secret Key in WishList Generic Integration settings", PEAR_LOG_NOTICE );
        die( "Incoming Secret Key '" . $f['security_code'] . "' does not match the Secret Key in WishList Generic Integration settings" );
    }
    unset( $wishlist_security_code );
}

if (!function_exists('wlmapi_add_member')) {
	logmsg('Wishlist Member not installed or not activated - Wishlistcast cannot continue');
	die('Wishlist Member not installed or not activated - Wishlistcast cannot continue');
}

// Force username to email if it is blank
$f['u_username'] = (isset($f['u_username']) && $f['u_username'] != '') ? $f['u_username'] : $f['u_email'];

logmsg( 'Request: [' . $f['mode'] . '] id: [' . $f['id'] . '] user: [' . $f['u_username'] . '] email: [' . $f['u_email'] . ']', PEAR_LOG_DEBUG );

global $wpdb;
$id_exists = $wpdb->get_row( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='external_user_id' AND meta_value=" . (int) $f['id'] );
if ( $id_exists->user_id ) { // this should take in 99% of cases
    $exists = $wpdb->get_row( "SELECT * FROM {$wpdb->users} WHERE ID=" . (int) $id_exists->user_id );
} else { // if someone's WP is messed up then we do this as a fallback, OR if someone upgrades using the membership grouping feature
    $exists = $wpdb->get_row( "SELECT * FROM " . $wpdb->users . " WHERE user_login='" . mysql_real_escape_string( $f['u_username'] ) . "'" );
}
unset( $id_exists );
$user_ID = $exists->ID;
$user_registered = null;

$make_active = false;
if ( $user_ID ) {
    if ( 'add' === $f['mode'] ) {
        $f['mode'] = 'modify'; // if the user exists already, then we should update the record instead of inserting
        $make_active = true; // make sure the account is active since their old WP account may have just been suspended and now we need to upgrade
    }
    $user_registered = $exists->user_registered;
} else {
    switch ( $f['mode'] ) {
        case 'add':
        case 'modify':
            $f['mode'] = 'add'; // add the user since they don't exist already
            break;
        case 'suspend':
        case 'delete':
        default:
            logmsgandClose( "User could not be found for suspend / delete", PEAR_LOG_NOTICE );
            die( "User could not be found for suspend / delete" ); // don't add people if they aren't supposed to be valid
            break;
    }
}

// v1.4.7 Hook to filter parameters after they have been 'sanitised', and prior to processing
$f = apply_filters( 'wishlist_api_sanitise_params', $f, $user_ID, $user_registered );

logmsg( "Sanitised request: [{$f['mode']}] user: [{$f['u_username']}] email: [{$f['u_email']}] WP User ID: [{$user_ID}] id: [{$f['id']}] Registered [{$user_registered}] MakeActive [" . ($make_active ? 'true' : 'false') . "]", PEAR_LOG_DEBUG );

// v1.4.7 Hook to perform any pre-processing
do_action( 'wishlistcast_api_before_process', $f, $user_ID );

// v1.4.7 Hook to filter the user_array (Used as either insert / update to $wpdb->users table)
$user_array = apply_filters( 'wishlist_api_filter_user_array', array(
    // updating the username every time and using usernames in Nanacast.com is the best way to keep usernames in sync and unique
    'user_login' => $f['u_username'],
    'user_nicename' => $f['u_username'],
    'user_email' => $f['u_email'],
    'user_url' => $f['u_website'],
    'display_name' => $f['u_firstname'] . ' ' . $f['u_lastname'],
    'user_registered' => $user_registered
        ) );
unset( $user_registered );

// Update / set password
if ( isset($f['u_password']) && $f['u_password'] != '' ) { // password has changed in remote system
    // NOTE: Remove md5 ( ) wrapper if using WLMAPI::AddUser(...) method
    // AddUser requires minimum password length of 8 characters though and Nanacast only generates 6-char passwords
    logmsg( 'Note: Updating password - password is updated in remote system', PEAR_LOG_DEBUG );
    $user_array['user_pass'] = $f['u_password'];
} elseif ( !$user_ID || empty( $exists->user_pass ) ) {
    // only create a new password if there isn't one or we're adding a new account
    // this is really impractical to the user because there is no way for them to know it unless they request it be sent to them,
    // but we just don't want any blank passwords
    // NOTE: Remove md5 ( ) wrapper if using WLMAPI::AddUser(...) method
}

// fix if level parameter passed instead of levels (plural) - can be confusing if just one level
if ( $f['level'] != '' && (!isset( $f['levels'] ) || ('' === $f['levels'] )) )
    $f['levels'] = $f['level'];
logmsg( 'Wishlist Level SKUs [' . print_r( $f['levels'], TRUE ) . ']', PEAR_LOG_DEBUG );

switch ( $f['mode'] ) {
    case 'add':
        // there is one way I can think of where you could get a duplicate username:
        // If you don't use the username field in Nanacast.com (which checks for uniqueness in Nanacast.com) and instead
        // use the email field (which WP will take as the username) and someone uses the same email twice.  
        // the solution: use the username field 
/*
        $wpdb->insert( $wpdb->users, $user_array );
        $user_ID = $wpdb->insert_id;
*/

        if ( $f['levels'] != '' ) {
            $levels = array_map( create_function( '$value', 'return (int)$value;' ), explode( '|', $f['levels'] ) );
        }
	     $args = array(
	          'user_login' => $f['u_username'],
	          'user_email' => $f['u_email'],
	          'Levels' => $levels,
	          'SendMail' => true,
	          'Sequential' => true,
	          'address1' => $f['u_address1'],
	          'city' => $f['u_city'],
	          'state' => $f['u_state'],
	          'zip' => $f['u_zip'],
	          'country' => $f['u_country']
	     );
	     $member = wlmapi_add_member($args);
     
		 if (1 == $member['success']) {
			 $user_ID = $member['member'][0]['ID'];
	        // Update user meta
	        wlapi_update_user_meta($user_ID, $f);
	
	        // Make user a subscriber (per standard memberlock behaviour)
	        // Note - if NO levels passed, then user could still be added as subscriber with no Wishlist membership
	        $new_user_role = empty( $f['role'] ) ? get_site_option( 'default_role', 'subscriber' ) : $f['role'];
	        if ( strtolower( $new_user_role ) != 'none' ) {
	            $user = new WP_User( $user_ID );
	            $user->set_role( $new_user_role );
	        }
	
	        update_user_meta( $user_ID, 'memberlock_status', 'active' );
			 
		 }

        break;
    case 'modify':
        $wpdb->update( $wpdb->users, $user_array, array('ID' => (int) $user_ID) );

        // Update user meta
        wlapi_update_user_meta($user_ID, $f);

        if ( $f['levels'] != '' ) {
            $levels = array_map( create_function( '$value', 'return (int)$value;' ), explode( '|', $f['levels'] ) );
            if ( class_exists( 'WLMAPI' ) ) {
                logmsg( 'Adding user [' . $user_ID . '] to levels [' . print_r( $levels, true ) . ']', PEAR_LOG_DEBUG );
                WLMAPI::AddUserLevels( $user_ID, $levels, '', true );
                foreach ( $levels as $i => $row ) {
                    logmsg( 'Uncancelling level [' . $row . '] for user [' . $user_ID . ']', PEAR_LOG_DEBUG );
                    WLMAPI::UnCancelLevel( $user_ID, $row );
                }
                // Update user registered date for levels
                update_user_level_registration_date( $user_ID, $levels, $f['u_start_date'] );
            } else {
                logmsg( 'Ignoring modify for user [' . $user_ID . '] - Wishlist API class (WLMAPI) unavailable - is Wishlist Member installed?', PEAR_LOG_DEBUG );
            }
        }

        if ( $make_active )
            update_user_meta( $user_ID, 'memberlock_status', 'active' );
        break;
    default:
        logmsgandClose( "Mode is missing or invalid [" . $f['mode'] . "]", PEAR_LOG_WARN );
        die( "Mode is missing or invalid" );
        break;
}

logmsgandClose( "Success: " . $user_ID . " mode=" . $f['mode'], PEAR_LOG_DEBUG );

function wlapi_update_user_meta ($user_ID, $f) {
    update_user_meta( $user_ID, 'first_name', $f['u_firstname'] );
    update_user_meta( $user_ID, 'nickname', $f['u_firstname'] );
    update_user_meta( $user_ID, 'last_name', $f['u_lastname'] );
    update_user_meta( $user_ID, 'external_user_id', $f['id'] );
}

function make_password() {
    return substr( uniqid( microtime() ), 0, 10 );
}

function logmsgandClose($msg = '', $priority = PEAR_LOG_NOTICE) {
    global $logger;
    $log_enabled = (isset( $logger ) && 'true' === get_option( 'wlc_logging' ) );
    if ( $log_enabled ) {
        $logger->log( $msg, $priority );
        $logger->close();
    }
}

function logmsg($msg = '', $priority = PEAR_LOG_NOTICE) {
    global $logger;
    $log_enabled = (isset( $logger ) && 'true' === get_option( 'wlc_logging' ) );
    if ( $log_enabled ) {
        $logger->log( $msg, $priority );
    }
}
?>