<?php
/*
Plugin Name: WishList API Pro
Plugin URI: http://steveovens.com/plugins/wishlistcast
Description: Wishlist and Nanacast integration plugin for Wordpress by <a href="http://www.steveovens.com">Steve Ovens</a>. Based on Nanacast's memberlock plugin. Requires Wishlist plugin
Version: 1.5.2
Author: Steve Ovens
Author URI: http://steveovens.com
*/


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
if (file_exists(dirname(__FILE__) . '/includes/wishlistcastpro.php')) {
	define( 'WISHLISTCAST_DOMAIN', 'wishlistcastpro' );
} else {
	define( 'WISHLISTCAST_DOMAIN', 'wishlistcast' );
}

define( 'WISHLISTCAST_ALT_API', 'http://steveovens.com/plugins_updater/' );


if (is_admin() ) {
	require_once( dirname(__FILE__) . '/includes/admin.php');
}
if (file_exists(dirname(__FILE__) . '/includes/wishlistcastpro.php')) {
	require_once(dirname(__FILE__) . '/includes/wishlistcastpro.php');
}

// While testing, keep deleting transient - comment the following line for release       < * * * * * L O O K    H E R E   * * * * * >
//add_action( 'init', 'wishlistcast_delete_transient' );
function wishlistcast_delete_transient() {
    delete_site_transient( 'update_plugins' );
}


// Hook into the plugin update check
add_filter('pre_set_site_transient_update_plugins', 'wishlistcast_check');

function wishlistcast_check( $transient ) {

    // Check if the transient contains the 'checked' information
    // If no, just return its value without hacking it
    if( empty( $transient->checked ) )
        return $transient;
    
    // The transient contains the 'checked' information
    // Now append to it information form your own API
    
    $plugin_slug = plugin_basename( __FILE__ );
    
    // POST data to send to your API
    $args = array(
        'action' => 'update-check',
        'plugin_name' => $plugin_slug,
        'version' => $transient->checked[$plugin_slug],
    );
    
    // Send request checking for an update
    $response = wishlistcast_request( $args );
    
    // If response is false, don't alter the transient
    if( false !== $response ) {
        $transient->response[$plugin_slug] = $response;
    }
    
    return $transient;
}

// Send a request to the alternative API, return an object
function wishlistcast_request( $args ) {

    // Send request
    $request = wp_remote_post( WISHLISTCAST_ALT_API, array( 'body' => $args ) );
    
    // Make sure the request was successful
    if( is_wp_error( $request )
    or
    wp_remote_retrieve_response_code( $request ) != 200
    ) {
        // Request failed
        return false;
    }
    
    // Read server response, which should be an object
    $response = unserialize( wp_remote_retrieve_body( $request ) );
    if( is_object( $response ) ) {
        return $response;
    } else {
        // Unexpected response
        return false;
    }
}


// Hook into the plugin details screen
add_filter('plugins_api', 'wishlistcast_information', 10, 3);

function wishlistcast_information( $plugin_info, $action, $args ) {

    $plugin_slug = plugin_basename( __FILE__ );

    // Check if this plugins API is about this plugin
    if( $args->slug != $plugin_slug ) {
        return $plugin_info;
    }
        
    // POST data to send to your API
    $args = array(
        'action' => 'plugin_information',
        'plugin_name' => $plugin_slug,
        'version' => $transient->checked[$plugin_slug],
    );
    
    // Send request for detailed information
    $response = wishlistcast_request( $args );
    
    // Send request checking for information
    $request = wp_remote_post( WISHLISTCAST_ALT_API, array( 'body' => $args ) );

    return $response;
}

if (!class_exists("WishlistCast")) {
	class WishlistCast {
	
		public static $logger = null;
		
		function WishlistCast() {
			// Initialize 
			if (is_admin() ) {
				$wishlistcast_admin_menu = new Wishlistcast_Admin();
				// Display a Documentation link on the main Plugins page
				add_filter( 'plugin_action_links', array(&$this, 'wishlistcast_plugin_action_links'), 10, 2 );
			}
		}
		
		function wishlistcast_plugin_action_links ($links, $file ) {
			if ( $file == plugin_basename( __FILE__ ) ) {
				$x = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__));
				$my_links = '<a href="'. $x .'/docs/How-To-Link-Nanacast.pdf">'.__('Documentation').'</a>';
				// make the 'Documentation' link appear first
				array_unshift( $links, $my_links );
                                $settings_link = '<a href="' . admin_url( 'options-general.php?page=' ) . WISHLISTCAST_DOMAIN . '">' . __( 'Settings' ) . '</a>';
				array_unshift( $links, $settings_link );
			}
		
			return $links;
		}
	}
}

add_action('init', 'wishlistCastInit', 10);
function wishlistCastInit() {
	global $wishlistCast; 
	// Initiate the plugin
	if (class_exists('WishlistCast')) {
		$wishlistCast = new WishlistCast();
	}
}


?>