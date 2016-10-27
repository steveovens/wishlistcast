<?php

/*
 * Sample custom extension for wishlistcast_api
 * Copy and Save as a separate file in the /plugins directory or create a proper plugin
 * 
 */

if ( !class_exists( "WishlistCastSampleExtension" ) ) {

    class WishlistCastSampleExtension {

        function WishlistCastSampleExtension() {
            add_filter( 'wishlistcast_api_filter_params', array(&$this, 'initial_param_filter') );
            add_filter( 'wishlist_api_sanitise_params', array(&$this, 'sanitised_param_filter'), 10, 3 );
            add_action( 'wishlistcast_api_before_process', array(&$this, 'before_process_action'), 10, 2 );
            add_filter( 'wishlist_api_filter_user_array', array(&$this, 'filter_user_array') );
            add_action( 'wishlistcast_api_after_process', array(&$this, 'after_process_action'), 10, 2 );
        }

        /**
         * Called for valid requests from nanacast, before any processing occurs
         * @param array $f
         * @return array $f
         */
        function initial_param_filter($f) {
            $f['sample'] = 'sample';
            return $f;
        }

        /**
         * Called once parameters have been "sanitised" - before any processing occurs
         * @param array $f
         * @return array $f
         */
        function sanitised_param_filter($f, $user_ID, $user_registered) {
            $f['sanitised'] = 'true';
            return $f;
        }

        /**
         * Called before processing occurs
         * @param type $f
         * @param type $user_ID
         */
        function before_process_action($f, $user_ID) {
            logmsg( 'Sample before process action' );
        }

        /**
         * Called to allow cleanup of user array values inserted/updated to wpdb users table
         * @param type $user_array
         * @return array $user_array
         */
        function filter_user_array($user_array) {
            $user_array['display_name'] = ucwords( $user_array['display_name'] );
            return $user_array;
        }

        /**
         * Called after successful processing of a nanacast API call
         * @param type $f
         * @param type $user_ID
         */
        function after_process_action($f, $user_ID) {
            logmsg( 'Sample after process action' );
        }

    }

}

function wishlistCastSampleInit() {
    global $wlc_sample;
    if ( class_exists( 'WishlistCastSampleExtension' )) {
        $wlc_sample = new WishlistCastSampleExtension();
    }
}
// Uncomment the next line to initialise your extension
// add_action('wishlistcast_api_init', 'wishlistCastSampleInit', 10);
?>