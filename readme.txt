=== WishListCast Pro ===
Contributors: Kent Thompson, Steve Ovens
Tags: nanacast, wishlist, login, restriction, membership, paid, content, management
Requires at least: 4.0
Tested up to: 4.2.4
Recommended Wishlist version: v2.90 (build 2828 or later)
Version: 1.5.1

Links Nanacast.com and SamCart (beta) with WishList Member. Fully integrates with Nanacast.com. Based (loosely) on Nanacast MemberLock plugin.  

Note: The PRO version is only available to private clients of Steve Ovens. It includes enhanced functionality and short codes for working with your Nanacast data. 

== Description ==
WishListCast integrates the Nanacast.com shopping cart with WishList Member.  It is designed to work "out-of-the-box" with no modifications to your theme.

It fully integrates with Nanacast.com allowing you to use the wide variety of payment methods and pricing structures available on Nanacast.com while at the same time using wordpress and WishList Member to distribute your content to your members.

== Installation ==

Basic Install:

1. Download and unzip.  Upload the wishlistcast/ folder to your /wp-content/plugins/ folder on your wordpress blog. 
2. Activate the plugin through the 'Plugins' menu in WordPress

3. You will need to know the Secret Key and Membership SKU from the Generic Integration screen inside your WishList Member Settings page.
4. Create your membership in Nanacast.com. 
5. You MUST add 'Password' as a Custom Field for your membership. You can select the option for Nanacast to generate the password.
6. Checkmark "Activate Advanced Outgoing API" for your membership, and then enter your wishlistcast API URL:
 http://YOUR-BLOG.com/wp-content/plugins/wishlistcast/wishlistcast_api.php?security_code=WishListSecretKey&levels=membershipSKU
Note: You can pass multiple membership levels using a pipe-delimiter (|) to separate the levels, e.g.
levels=12345678|12357909|12468023

You can also pass a "verify field" and "verify_value" which the API will check before processing any API requests. This is handy in situations where you want to manually verify orders in nanacast. Simply add &verify_field=u_custom_xx&verify_value=Y (for example) to your outgoing API, where u_custom_xx is the nanacast field name and "Y" is the value it will hold for verified orders.

Release Notes:
1.0 Initial Release
1.0.1 Bug fix for error message when accessing WishList Member settings pages
1.1.0 Set default user role (to match General settings for blog). Support auto-update for future releases
1.1.1 Fix to work with latest Wishlist Member update (re-activation of cancelled subscribers is working again!)
1.2.0 Update to handle Nanacast membership groups (unsubscribe msg now ignored for changes within groups) and replace deprecated WP API calls with current calls.
1.3.0 Added Settings panel to simplify Nanacast integration (it shows you the URL to use inside nanacast). 
      Added PEAR logging to assist with debugging.
1.4.0 Pro version - added short codes and capture of nanacast data to user meta database in WP.
1.4.1 Pro version - Code tidy up to make maintenance easier - moved wishlistcastpro.php to /includes. Delete logging option on uninstall.
1.4.2 Pro version - added ifmember / ifnotmember shortcodes
1.4.3 Pro version - Added sync between nanacast u_start_date and WP user_registered metadata field to help with drip-fed content (same as non-Pro version 1.4.0 fix)
1.4.4 Pro version - Added "ignore" option to [ifmember] and escape flag to [castshow] shortcodes. Fixed [ifnotmember] shortcode. Added support for verify field to standard version
1.4.5 Pro version - Added mode="or" to [ifmember]
1.4.6 (not released) Added 'make_sequential' parameter (defaults to "true"). This determines whether Wishlist "Sequential" parameter is set to "On" for the user. This was to correct a change in behaviour with the latest Wishlist Member where the default setting for new users changed to "Off".
Pro version - (not released) Update user_registered and user level registration with u_start_date. Code cleanup. Fixed Log compatibility with other plugins. Added transaction logging option.

1.4.7 Pro version - Added filters and hooks to allow custom plugin extensions. (Optionally) exclude expired levels in ifmember shortcode. Fixed bug introduced in 1.4.6 code cleanup
1.4.8 - Add users to level now also adds user to Autoresponder (if defined in Wishlist)
1.4.9 - Fix bug where users were being added to Autoresponder without first or last name
1.5.0 - User registration date (user_registered) is now set to the lowest value of the existing registration date and the level date. This is to handle a case in a Pro project where user start time can be set in the past (to intentionally trigger 'sequential upgrade' logic in Wishlist)
1.5.1 Pro version - Add SamCart (beta) integration - note: this is not required for SAMCart production release which has built in Wishlist integration support.
1.5.2 Pro version - Fix version number to stop continuous update notification bug