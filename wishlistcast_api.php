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
// Note: Using array_merge so only have POST/GET variables (default behaviour includes cookies)
// This also allows override of nanacast POST vars with user-defined GET vars
$f = array_merge( $_POST, $_GET );

// v1.4.6 introduce transaction logging with replay capability (for use by support)
$replaying = false;
if ( array_key_exists( 'replay', $f ) || !empty( $f['replay'] ) ) {
    // Note: The transaction replay functionality is not included in the public release
    if ( file_exists( dirname( __FILE__ ) . '/replay/replay_txns.php' ) ) {
        include_once(dirname( __FILE__ ) . '/replay/replay_txns.php');
    }
}

if ( !array_key_exists( 'mode', $f ) || $f['mode'] == '' ) {
    return; // Not executing as a result of being called from Nanacast - probably plugin activation
}

include_once('../../../wp-config.php');

if ( !defined( 'ABSPATH' ) ) // wishlistcast-api.php could be in plugins/ or plugins/wishlistcast/
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
    $logger = Log::Singleton( 'file', 'wishlistcast.log', 'WLC' );
}

// v1.4.6 Log the transaction if txn_logging is enabled (unless we are replaying)
if ( !$replaying && 'listtxns' != $f['mode'] && 'true' === get_option( 'wlc_logtxns' ) ) {
    $filePath = './params_' . strftime( '%Y%m%d-%H%M%S' );
    $suffix = 0;
    while ( file_exists( $filePath ) ) {
        $suffix++;
        $filePath = './params_' . strftime( '%Y%m%d-%H%M%S' ) . "_{$suffix}";
    }
    file_put_contents( $filePath, serialize( $f ) );
    unset( $suffix );
    unset( $filePath );
}
unset( $replaying );

// v1.4.7 Add hook to filter initial parameters
$f = apply_filters( 'wishlistcast_api_filter_params', $f );

if ( 'suspend' === $f['mode'] && array_key_exists( 'u_last_unsubscribe_reason', $f ) && 'changed_membership_within_group' === $f['u_last_unsubscribe_reason'] ) {
    logmsgandClose( 'Ignoring membership change within group', PEAR_LOG_DEBUG );
    return; // Not executing on membership changes within group - Wishlist Member needs to be configured to handle these
}

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

// v1.4.6 Add mode=listtxns (only used by support). Requires secret key
if ( 'listtxns' === $f['mode'] ) {
    $txn_files = glob( './params_*' );
    if ( empty( $txn_files ) ) {
        logmsgandClose( 'No transaction files found.' );
        return;
    }
    ?>
    <style type="text/css">
        #txn_table th {
            background-color: #B52200;
            color: #EEEEEE;
        }
        .even td { background-color: #d5edff; }
    </style>
    <table id="txn_table">
        <thead>
            <tr>
                <th>TXN ID</th>
                <th>Contents</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $indx = 1;
            foreach ( $txn_files as $txn_file ) {
                $indx = ($indx + 1) % 2;
                $txn_id = substr( $txn_file, 9 );
                echo "<tr" . ($indx == 0 ? ' class="even"' : '') . "><td>{$txn_id}</td>";
                echo "<td>" . file_get_contents( $txn_file ) . "</td></tr>";
            }
            ?>
        </tbody>
    </table>
    <?php
    logmsgandClose( 'List transactions requested.' );
    return;
}

// Add the "Pro" functionality (if file exists). Not included in standard version of plugin
if ( file_exists( dirname( __FILE__ ) . '/includes/wishlistcastpro-fields.php' ) ) {
    include(dirname( __FILE__ ) . '/includes/wishlistcastpro-fields.php');
}

logmsg( 'Request: [' . $f['mode'] . '] id: [' . $f['id'] . '] user: [' . $f['u_username'] . '] email: [' . $f['u_email'] . ']', PEAR_LOG_DEBUG );
// Force username to email if it is blank
$f['u_username'] = ($f['u_username'] != '') ? $f['u_username'] : $f['u_email'];

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
        case 'reactivate':
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
// v1.4.6 Update user_registered with u_start_date if u_start_date is earlier than current
// Adjust u_start_date to match format of user_registered in WP table
if ( strlen( $f['u_start_date'] ) <= 10 )
    $f['u_start_date'] .= " 00:00:00";

// Set user registered date to earliest value of start_date (from nanacast) and any existing user_registered date
// Note user_registered (date user registered) may be earlier than 
// u_start_date (date user registered for this membership level)
if ( empty( $user_registered ) || '0000-00-00 00:00:00' === $user_registered ) {
    $user_registered = $f['u_start_date'];
} else {
    $user_registered = ($f['u_start_date'] < $user_registered ? $f['u_start_date'] : $user_registered);
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
if ( $f['u_password'] != '' ) { // password has changed in remote system
    // NOTE: Remove md5 ( ) wrapper if using WLMAPI::AddUser(...) method
    // AddUser requires minimum password length of 8 characters though and Nanacast only generates 6-char passwords
    logmsg( 'Note: Updating password - password is updated in remote system', PEAR_LOG_DEBUG );
    $user_array['user_pass'] = md5( $f['u_password'] );
} elseif ( !$user_ID || empty( $exists->user_pass ) ) {
    // only create a new password if there isn't one or we're adding a new account
    // this is really impractical to the user because there is no way for them to know it unless they request it be sent to them,
    // but we just don't want any blank passwords
    // NOTE: Remove md5 ( ) wrapper if using WLMAPI::AddUser(...) method
    logmsg( 'Note: Creating MD5 Hash of password for new user', PEAR_LOG_DEBUG );
    $user_array['user_pass'] = md5( make_password() );
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
        $wpdb->insert( $wpdb->users, $user_array );
        $user_ID = $wpdb->insert_id;
        // Note: Can't use WLMAPI::AddUser(...) because Nanacast's generated password is too short
        //$user_ID = WLMAPI::AddUser($f['u_username'], $f['u_email'], $user_array['user_pass'], $f['u_firstname'], $f['u_lastname']);

        // Update user meta
        wlapi_update_user_meta($user_ID, $f);

        if ( $f['levels'] != '' ) {
            $levels = array_map( create_function( '$value', 'return (int)$value;' ), explode( '|', $f['levels'] ) );
            if ( class_exists( 'WLMAPI' ) ) {
                logmsg( 'Adding user [' . $user_ID . '] to levels [' . print_r( $levels, true ) . ']', PEAR_LOG_DEBUG );
                WLMAPI::AddUserLevels( $user_ID, $levels, '', true );

                //v1.4.6  Update user registered date for levels
                update_user_level_registration_date( $user_ID, $levels, $f['u_start_date'] );
            } else {
                logmsg( 'Ignoring add levels for user [' . $user_ID . '] - Wishlist API class (WLMAPI) unavailable - is Wishlist Member installed?', PEAR_LOG_DEBUG );
            }
        }
        // Make user a subscriber (per standard memberlock behaviour)
        // Note - if NO levels passed, then user could still be added as subscriber with no Wishlist membership
        $new_user_role = empty( $f['role'] ) ? get_site_option( 'default_role', 'subscriber' ) : $f['role'];
        if ( strtolower( $new_user_role ) != 'none' ) {
            $user = new WP_User( $user_ID );
            $user->set_role( $new_user_role );
        }

        update_user_meta( $user_ID, 'memberlock_status', 'active' );
        break;
    case 'reactivate':
        $wpdb->update( $wpdb->users, $user_array, array('ID' => (int) $user_ID) );

        // Update user meta
        wlapi_update_user_meta($user_ID, $f);

        if ( class_exists( 'WLMAPI' ) ) {
            $user_levels = WLMAPI::GetUserLevels( $user_ID, 'all', 'names', false, false, 2 );
            foreach ( $user_levels as $i => $row ) {
                logmsg( 'Uncancelling level [' . $i . '] for user [' . $user_ID . ']', PEAR_LOG_DEBUG );
                WLMAPI::UnCancelLevel( $user_ID, $i );
            }

            if ( $f['levels'] != '' ) {
                $levels = array_map( create_function( '$value', 'return (int)$value;' ), explode( '|', $f['levels'] ) );
                logmsg( 'Adding user [' . $user_ID . '] to levels [' . print_r( $levels, true ) . ']', PEAR_LOG_DEBUG );
                WLMAPI::AddUserLevels( $user_ID, $levels, '', true );

                //v1.4.6 Update user registered date for levels
                update_user_level_registration_date( $user_ID, $levels, $f['u_start_date'] );
            }
        } else {
            logmsg( 'Ignoring reactivate for user [' . $user_ID . '] - Wishlist API class (WLMAPI) unavailable - is Wishlist Member installed?', PEAR_LOG_DEBUG );
        }

        update_user_meta( $user_ID, 'memberlock_status', 'active' );
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
    case 'suspend':
        $wpdb->update( $wpdb->users, $user_array, array('ID' => (int) $user_ID) );

        // Update user meta
        wlapi_update_user_meta($user_ID, $f);

        if ( class_exists( 'WLMAPI' ) ) {
            $user_levels = WLMAPI::GetUserLevels( $user_ID );
            foreach ( $user_levels as $i => $row ) {
                logmsg( 'Cancelling level [' . $i . '] for user [' . $user_ID . ']', PEAR_LOG_DEBUG );
                WLMAPI::CancelLevel( $user_ID, $i );
            }
        } else {
            logmsg( 'Ignoring suspend for user [' . $user_ID . '] - Wishlist API class (WLMAPI) unavailable - is Wishlist Member installed?', PEAR_LOG_DEBUG );
        }

        update_user_meta( $user_ID, 'memberlock_status', 'suspended' );
        break;
    case 'delete':
        if ( class_exists( 'WLMAPI' ) ) {
            WLMAPI::DeleteUser( $user_ID );
        } else {
            logmsg( 'DELETE - Wishlist API class (WLMAPI) unavailable (is it installed?) - Performing direct delete for user [' . $user_ID . '] instead.', PEAR_LOG_DEBUG );
            wp_delete_user( $user_ID, $reassign = 'novalue' );
        }
        break;
    case 'decline':
        logmsgandClose( "Nothing to do for declined status", PEAR_LOG_DEBUG );
        die( "Nothing to do for declined status" );
        break;
    default:
        logmsgandClose( "Mode is missing or invalid [" . $f['mode'] . "]", PEAR_LOG_WARN );
        die( "Mode is missing or invalid" );
        break;
}

switch ( $f['mode'] ) {
    case 'add':
    case 'reactivate':
    case 'modify':
        //v1.4.6 Force user to sequential mode in Wishlist
        if ( class_exists( 'WLMAPI' ) ) {
            // If make_sequential parameter is either explicitly set to true 
            // or not set at all (ie. this is the default behaviour we want)
            // then make the user's membership "sequential" in Wishlist
            // This is the default behaviour of Wishlist anyway
            // It is really to make sure any manual changes to a user record
            // are reset to default behaviour unless explicitly overridden
            if ( !isset( $f['make_sequential'] ) || 'true' === strtolower( $f['make_sequential'] ) ) {
                WLMAPI::MakeSequential( $user_ID );
            } else {
                WLMAPI::MakeNonSequential( $user_ID );
            }
        }
    case 'suspend':
        update_user_meta( $user_ID, 'memberlock_feedurl', $f['feedurl'] );
        update_user_meta( $user_ID, 'memberlock_membership_name', $f['item_name'] );
        update_user_meta( $user_ID, 'memberlock_membership_id', $f['u_list_id'] );

        do_action( 'wishlistcast_api_after_process', $f, $user_ID );
        break;

    default:
        break; // Do nothing for other modes
}
logmsgandClose( "Success: " . $user_ID . " mode=" . $f['mode'], PEAR_LOG_DEBUG );

function wlapi_update_user_meta ($user_ID, $f) {
    update_user_meta( $user_ID, 'first_name', $f['u_firstname'] );
    update_user_meta( $user_ID, 'nickname', $f['u_firstname'] );
    update_user_meta( $user_ID, 'last_name', $f['u_lastname'] );
    update_user_meta( $user_ID, 'external_user_id', $f['id'] );
}

function make_password() {
    return substr( uniqid( microtime() ), 0, 7 );
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

function update_user_level_registration_date($user_id, $levels, $registered_date) {
    global $wpdb;

    if ( strstr( $registered_date, "#" ) === false ) {
        $registered_date .= "#0"; // Add GMT timestamp indicator to date (used by WLM)
    }

    // User level table name
    $ul_table_name = $wpdb->prefix . 'wlm_userlevels';

    // User level options table name
    $ulo_table_name = $wpdb->prefix . 'wlm_userlevel_options';

    foreach ( $levels as $level_id ) {

        // Get current registration date for level (Note: should only be one row - unclear how multiple transactions might be recorded by WLM, so allowing for multiple)
        $level_options = $wpdb->get_results( $wpdb->prepare( "
            SELECT *
            FROM {$ul_table_name} ul, {$ulo_table_name} ulo
            WHERE ul.user_id = %d
            AND ul.level_id = %d
            AND ulo.option_name = 'registration_date'
            AND ulo.userlevel_id = ul.id
        ", $user_id, $level_id ) );

        foreach ( $level_options as $level_option ) {
            $ulo_id = $level_option->ID;
            $rows = $wpdb->query( $wpdb->prepare( "
                    update {$ulo_table_name}
                    set option_value = %s
                    where id = %d
                ", $registered_date, $ulo_id ) );
            logmsg( "Updated user level registration date for User ID [{$user_id}] Level ID [${level_id}] to [{$registered_date}] (was [{$level_option->option_value}]). {$rows} rows updated." );
        }
    }

    // Set user registration date to lowest value of existing registration date and level date
    // Needed because Wishlist is now including user registration date in sequential upgrade calculations
    $wp_fmt_date = str_replace('#0','',$registered_date);
    $user_registered = get_userdata($user_id) -> user_registered;
    logmsg ("Checking user registration date for User ID [{$user_id}] currently: [{$user_registered}] is before level registration date [{$wp_fmt_date}]");
    if ($user_registered > $wp_fmt_date) {
	logmsg ("Updating user registration date for User ID [{$user_id}] to match level registration date [{$wp_fmt_date}]");
	wp_update_user( array( 'ID' => $user_id, 'user_registered' => $wp_fmt_date ) );
    }    
}
?>