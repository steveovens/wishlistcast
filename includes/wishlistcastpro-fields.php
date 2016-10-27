<?php

    /**
     * 
     * Adds PRO functionality to save ALL nanacast u_xxx fields as wlc_u_xxx 
     * user metadata.
     * 
     * This is hooked to the wishlist_api_after_process hook
     * 
     * @param type $f - all parameters from nanacast
     * @param type $user_ID - WP user ID
     */
    function wlc_add_user_metadata($f, $user_ID) {
        // Update user metadata with any passed nanacast values 
        // id, coupon_code are passed without the "u_" prefix, all other vars have "u_" prefix
        update_user_meta( $user_ID, 'wlc_id', $f['id']);
        update_user_meta( $user_ID, 'wlc_coupon_code', $f['coupon_code']);
        update_user_meta( $user_ID, 'wlc_feedurl', $f['feedurl']);
        update_user_meta( $user_ID, 'wlc_membership_name', $f['item_name']);
        update_user_meta( $user_ID, 'wlc_account_id', $f['account_id']);
        if (!empty($f['affiliate_url'])) {
                        update_user_meta( $user_ID, 'wlc_affiliate_url', $f['affiliate_url']);
        }
        foreach ($f as $key => $value) {
                if (strlen($key) > 2 && substr($key, 0, 2) == 'u_') {
                        update_user_meta( $user_ID, 'wlc_' . $key, $value);
                }
        }
    }
    
    add_action( 'wishlistcast_api_after_process', 'wlc_add_user_metadata', 10, 2 );
?>