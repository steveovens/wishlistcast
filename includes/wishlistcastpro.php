<?php

/*
 * WishlistcastPro class adds the "Pro" functionality to the standard Wishlistcast plugin
 * 
 * This is the PRO shortcodes:
 * [castnouser] - Show content if user is NOT logged in
 * [castshow] - Show value of a user metadata value passed from nanacast
 * [castif] - Show contents if metadata value evaluates to true
 * [castifnot] - Show contents if metadata value evaluates to false
 * [metashow] - Show value of a user metadata value
 * [ifmember] - show content when user is member of level(s)
 * [ifnotmember] - show content when user is NOT a member of level(s)
 * 
 */

if ( !class_exists( "WishlistCastPro" ) ) {

    define(WLC_EXPIRED_INCLUDE, 'include-expired');
    define(WLC_EXPIRED_EXCLUDE, 'exclude-expired');
    define(WLC_EXPIRED_ONLY, 'only-expired');
    
    class WishlistCastPro {

        function WishlistCastPro() {
            add_shortcode( 'castnouser', array(&$this, 'castnouser') );
            add_shortcode( 'castshow', array(&$this, 'castshow') );
            add_shortcode( 'castif', array(&$this, 'castiftrue') );
            add_shortcode( 'castifnot', array(&$this, 'castiffalse') );
            add_shortcode( 'metashow', array(&$this, 'metashow') );
            add_shortcode( 'ifmember', array(&$this, 'ifmember') );
            add_shortcode( 'ifnotmember', array(&$this, 'ifnotmember') );
        }

        /**
         * getUserLevels - internal function used to return the user
         * levels for a given user (default is current user).
         * 
         * By default, this returns only unexpired levels of which the 
         * user is a member.
         * 
         * The $expired parameter controls the behaviour:
         * - "exclude-expired" (default) - return only unexpired levels
         * - "include-expired" - return both expired and unexpired levels
         * - "only-expired" - return ONLY the expired levels for the user
         * 
         * @param type $user_id - user to get levels for (default is current user)
         * @param type $expired - controls which levels are returned (default is exclude expired levels)
         * @return array levels[level-id] => level_name
         */
        function getUserLevels($user_id = null, $expired = WLC_EXPIRED_EXCLUDE) {
            if ( !class_exists( 'WLMAPI' ) )
                return null;

            if ( empty( $user_id ) ) {
                global $current_user;
                get_currentuserinfo();
                $user = $current_user;
            } else {
                $user = get_userdata( $user_id );
                if ( $user == false )
                    return null;
            }
            global $wpdb;

            $result = Array();

            $user_levels = WLMAPI::GetUserLevels( $user->ID );
            $all_levels = WLMAPI::GetLevels();
            $now = new DateTime(); // Used to compare with computed expiry date for level
            // User level table name
            $ul_table_name = $wpdb->prefix . 'wlm_userlevels';

            // User level options table name
            $ulo_table_name = $wpdb->prefix . 'wlm_userlevel_options';

            foreach ( $user_levels as $level_id => $level_name ) {

                $this_level = $all_levels[$level_id];

                if ( $this_level == null )
                    continue;

                // If this level does not expire OR we are including expired levels
                // then just add this level and continue
                // No need to check the expiry date
                if ( ($this_level['noexpire'] == 1) || (WLC_EXPIRED_INCLUDE == $expired) ) {
                    $result[$level_id] = $level_name;
                    continue;
                }

                // Get current registration date for level (Note: should only be one row - unclear how multiple transactions might be recorded by WLM, so allowing for multiple)
                $level_options = $wpdb->get_results( $wpdb->prepare( "
                            SELECT *
                            FROM {$ul_table_name} ul, {$ulo_table_name} ulo
                            WHERE ul.user_id = %d
                            AND ul.level_id = %d
                            AND ulo.option_name = 'registration_date'
                            AND ulo.userlevel_id = ul.id
                        ", $user->ID, $level_id ) );

                $level_registration_date = new DateTime( $user->user_registered );
                if ( !empty( $level_options ) ) {
                    $level_start_date = $level_options[0]->option_value;

                    if ( !empty( $level_start_date ) && substr( $level_start_date, 0, 4 ) != '0000' ) {
                        $idx = strrpos( $level_start_date, "#" );
                        if ( $idx != false ) {
                            $level_start_date = substr( $level_start_date, 0, $idx );
                        }
                        unset( $idx );
                    }
                    $level_registration_date = new DateTime( $level_start_date );
                }
                $modifier = '+ ' . $this_level['expire'] . ' ' . strtolower( $this_level['calendar'] );
                $level_expiry_date = clone $level_registration_date;
                $level_expiry_date->modify( $modifier );

                switch ( $expired ) {
                    case WLC_EXPIRED_EXCLUDE :
                        // Default case - only include unexpired levels
                        if ( $level_expiry_date > $now ) {
                            $result[$level_id] = $level_name;
                        }
                        break;
                    case WLC_EXPIRED_ONLY:
                        if ( $level_expiry_date <= $now ) {
                            $result[$level_id] = $level_name;
                        }
                        break;
                    case WLC_EXPIRED_INCLUDE:
                        // Cannot really get to here - we check for "include" == $expired above
                        $result[$level_id] = $level_name;
                        break;
                }
            }
            return $result;
        }

        /**
         * Show content if user is a member of a given level
         * Supports AND logic - i.e. user is a member of level1 AND level2
         * Supports OR logic - i.e. user is a member of level1 OR level2
         * 
         * parameters:
         * user_id - WP user id of user to check - default is current user
         * levels - comma-separated list of levels to check user membership
         * mode - "AND" (default) or "OR" - whether check is for any level (OR) or all levels (AND)
         * ignore - comma-separated list of levels to ignore when checking
         * ignore_except - comma-separated list of levels that should NOT be ignored
         *                 If ignore_except is passed, then only these levels are considered.
         * focus - true / false(default) - whether the list of levels is to be treated as an ignore_except set
         *         If focus is true, then only the levels in the 'levels' list are considered
         *         This is specifically to cater for users being a member of another level
         *         which has no bearing on the current test, when the mode is "AND" (default).
         * 
         * @param type $atts
         * @param type $content
         * @return string
         */
        function ifmember($atts, $content = null) {
            $user_id = null;
            $expired = WLC_EXPIRED_EXCLUDE;
            if (!empty( $atts["user_id"])) {
                $user_id = trim($atts["user_id"]);
            }
            if (!empty( $atts["expired"])) {
                $expired = trim(strtolower($atts["expired"]));
            }
            $current_user_level = $this->getUserLevels($user_id, $expired);

            $levels = null;
            $ignore = null;
            $mode = "AND";
            if ( !empty( $atts["levels"] ) )
                $levels = array_filter( explode( ",", $atts["levels"] ), 'strlen' );
            if ( !empty( $atts["ignore"] ) )
                $ignore = array_filter( explode( ",", $atts["ignore"] ), 'strlen' );
            if ( !empty( $atts["ignore_except"] ) )
                $ignore_except = array_filter( explode( ",", $atts["ignore_except"] ), 'strlen' );
            if ( !empty( $atts["mode"] ) && strtolower( $atts["mode"] ) == "or" )
                $mode = "or";

            if ( 'or' == $mode ) {
                $val = '';
                if ( ($current_user_level != null) && !empty( $levels ) ) {
                    foreach ( $levels as $level ) {
                        if ( array_key_exists( $level, $current_user_level ) ) {
                            $val = do_shortcode( $content );
                            break;
                        }
                    }
                }
            } else {

                if ( filter_var( $atts["focus"], FILTER_VALIDATE_BOOLEAN ) ) {
                    $ignore_except = $levels;
                }

                if ( $ignore_except != null && $current_user_level != null ) {
                    
                    // Make sure the levels we're checking for aren't excluded from member, removing possible duplicates
                    $ignore_except = array_unique( array_merge( $ignore_except, $levels ) );
                    
                    // Restrict the current_user_levels we are considering to ONLY those in the "from" list
                    $current_user_level = array_intersect_key( array_flip( $ignore_except ), $current_user_level );
                }

                if ( $ignore != null && $current_user_level != null ) {
                    $current_user_level = array_diff_key( $current_user_level, array_flip( $ignore ) );
                }

                if ( $current_user_level != null ) {
                    $val = $this->membercontents( $levels, $current_user_level, $content );
                } else {
                    $val = '';
                }
            }
            return $val;
        }

        function membercontents($levels, $current_user_level, $content) {

            $arr = array();

            for ( $i = 0; $i < count( $levels ); $i++ ) {
                if ( array_key_exists( $levels[$i], $current_user_level ) ) {

                    $arr[] = '1';
                }
            }

            $filter = array_filter( $arr );

            if ( count( $arr ) == count( $current_user_level ) && count( $filter ) == count( $levels ) ) {
                $value = do_shortcode( $content );
            } else {
                $value = '';
            }

            return $value;
        }

        /**
         * Show the content only if user is NOT a member of any of the levels
         * 
         * @param type $atts
         * @param type $content
         * @return string
         */
        function ifnotmember($atts, $content = null) {
            $user_id = null;
            $expired = WLC_EXPIRED_EXCLUDE;
            if (!empty( $atts["user_id"])) {
                $user_id = trim($atts["user_id"]);
            }
            if (!empty( $atts["expired"])) {
                $expired = trim(strtolower($atts["expired"]));
            }
            $current_user_level = $this->getUserLevels($user_id, $expired);
            $levels = null;
            if ( !empty( $atts["levels"] ) )
                $levels = array_filter( explode( ",", $atts["levels"] ), 'strlen' );

            if ( $current_user_level != null ) {
                $missing = array_filter( array_diff_key( array_flip( $levels ), $current_user_level ), 'strlen' );
                if ( $missing != null && !empty( $missing ) ) {
                    $val = do_shortcode( $content );
                } else {
                    $val = '';
                }
            }

            return $val;
        }

        /**
         * Show content if user is NOT logged in
         * @param type $atts (ignored)
         * @param type $content - content to show
         * @return string
         */
        function castnouser($atts, $content = null) {
            if ( !is_user_logged_in() ) {
                return do_shortcode( $content );
            } else {
                return '';
            }
        }

        /**
         * Display the value of a wishlistcast variable (passed from Nanacast)
         * These are stored with a prefix of "wlc_" in the user metadata table
         * 
         * Many nanacast variables will have a prefix of "u_"
         * You do NOT need to include the wlc_ prefix - you DO need the u_ prefix if required
         * 
         * 
         * Usage examples:
         * [castshow name="u_subscribe_ip" /]
         * [castshow name="account_id" /]
         * 
         * @param type $atts
         * @param type $content
         * @return string
         */
        function castshow($atts, $content = null) {
            if ( !is_user_logged_in() )
                return '';

            if ( !empty( $atts[0] ) && empty( $atts['name'] ) ) {
                $atts['name'] = $atts[0];
            }
            $atts['name'] = 'wlc_' . $atts['name'];
            return $this->metashow( $atts, $content );
        }

        /**
         * Show contents if a metadata value evaluates to true
         * 
         * Usage example:
         *   [castif name="metadata_field"]...[/castif]
         * 
         * @param type $atts
         * @param type $content
         * @return string
         */
        function castiftrue($atts, $content = null) {
            if ( !is_user_logged_in() )
                return '';

            if ( !empty( $atts[0] ) && empty( $atts['name'] ) ) {
                $atts['name'] = $atts[0];
            }

            $name = null;
            extract( shortcode_atts( array(
                'name' => ''
                            ), $atts ) );

            if ( !empty( $name ) ) {
                $val = $this->getusermeta( $name );
                if ( filter_var( $val, FILTER_VALIDATE_BOOLEAN ) ) {
                    return do_shortcode( $content );
                }
            }
            return '';
        }

        /**
         * Show contents if a metadata value evaluates to false
         * 
         * Usage example:
         *   [castifnot name="metadata_field"]...[/castifnot]
         * 
         * @param type $atts
         * @param type $content
         * @return string
         */
        function castiffalse($atts, $content = null) {
            if ( !is_user_logged_in() )
                return '';

            if ( !empty( $atts[0] ) && empty( $atts['name'] ) ) {
                $atts['name'] = $atts[0];
            }

            $name = null;
            extract( shortcode_atts( array(
                'name' => ''
                            ), $atts ) );

            if ( !empty( $name ) ) {
                $val = $this->getusermeta( $name );
                if ( !filter_var( $val, FILTER_VALIDATE_BOOLEAN ) ) {
                    return do_shortcode( $content );
                }
            }
            return '';
        }

        /**
         * Show content of a user metadata value
         * 
         * Usage example:
         * [metashow name="wlc_u_account_id" /]
         * 
         * @param type $atts
         * @param type $content - content to show
         * @return string
         */
        function metashow($atts, $content = null) {
            if ( !is_user_logged_in() )
                return '';

            $name = '';
            $encode = 'false';
            $escape = 'false';
            if ( !empty( $atts[0] ) && empty( $atts['name'] ) ) {
                $atts['name'] = $atts[0];
            }
            extract( shortcode_atts( array(
                'name' => '',
                'encode' => 'false',
                'escape' => 'false'
                            ), $atts ) );
            if ( !empty( $name ) ) {
                return $this->getusermeta( $name, $encode, $escape ) . do_shortcode( $content ); // $content should be blank
            } else {
                return '';
            }
        }

        /**
         * Internal function to retrieve user metadata value
         * 
         * @param type $name - parameter to retrieve
         * @param type $encode - whether or not to urlencode the return value
         * @param type $escape - whether or not to escape (addslashes) to the return value
         * @return type string - the metadata value
         */
        function getusermeta($name, $encode = 'false', $escape = 'false') {
            global $current_user;
            get_currentuserinfo();
            $to_encode = filter_var( $encode, FILTER_VALIDATE_BOOLEAN );
            $to_escape = filter_var( $escape, FILTER_VALIDATE_BOOLEAN );
            $meta_val = get_user_meta( $current_user->ID, $name, true );
            if ( $to_escape ) {
                $meta_val = addslashes( $meta_val );
            }
            if ( $to_encode ) {
                $meta_val = urlencode( $meta_val );
            }
            return $meta_val;
        }

    }

}

add_action( 'init', 'wishlistCastProInit', 10 );

function wishlistCastProInit() {
    global $wishlistCastPro;
    // Initiate the plugin
    if ( class_exists( 'WishlistCastPro' ) ) {
        $wishlistCastPro = new WishlistCastPro();
    }
}

?>