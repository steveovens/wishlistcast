<?php
/*
Description: Manage the settings for the Wishlistcast Plugin
Author: Steve Ovens
Author URI: http://steveovens.com
*/

// Add a menu for our option page
if (!class_exists("Wishlistcast_Admin")) {

	class Wishlistcast_Admin {
		private static $settings_group = 'wishlistcast_settings';
		private static $logging_enabled_key = 'wlc_logging';
                private static $logtxns_enabled_key = 'wlc_logtxns';
		private static $plugin_name = "Wishlistcast";
		private static $plugin_desc = "Use this page to find the URL to use in Nanacast's outgoing API call for your Wishlist membership levels and control the Wishlistcast plugin options.";

		function Wishlistcast_Admin() {
	
			if ( is_admin() ) { // admin actions
				// create custom plugin settings menu
				add_action('admin_menu', array(&$this, 'admin_menu') );
				//call register settings function
				add_action( 'admin_init', array(&$this, 'register_mysettings') );
				// Add Ajax handler for delete button
				add_action('wp_ajax_ak_attach', array(&$this, 'ajaxResponse' ) );
			}
		}
		
		function admin_menu() {
			$settings = add_options_page( 'WishlistCast Settings', 'Wishlistcast', 'manage_options', WISHLISTCAST_DOMAIN, array(&$this, 'settings_page') );
			// Add JQuery to the admin page load
			add_action('load-'.$settings, array(&$this, 'load_jquery' ) );
		}
                
		function load_jquery() {
			wp_enqueue_script( 'jquery' );
		}                

		function register_mysettings() {
			register_setting(
				self::$settings_group,
				self::$logging_enabled_key
			);
                        register_setting (
				self::$settings_group,
                                self::$logtxns_enabled_key
                        );
		}
                
                function ajaxResponse() {
			$key = isset($_POST['keyID'])?$_POST['keyID']:null;
			if ( (!empty($key)) && 'true' === $key ) {
                                foreach (glob (dirname(dirname(__FILE__)) . "/params_*") as $filename) {
                                    unlink($filename);
                                }
				echo $this->show_current_txns();
			}
			exit;
		}

                function show_current_txns() {
                    $retval = '';
                    $txn_files = glob (dirname(dirname(__FILE__)) . "/params_*" );
                    $logging_enabled = get_option( self::$logging_enabled_key );
                    $log_txns = get_option( self::$logtxns_enabled_key );

                    $retval .= '<table width="100%" border="0" class="form-table" style="background-color:#eef5fb; padding:10px;">';

                    $retval .= '<tr valign="top">';
                    $retval .= '<th scope="row">Enable logging</th>';
                    if ($logging_enabled) { $checked = "checked=\"checked\""; } else{ $checked = ""; }
                    $retval .= '<td><input type="checkbox" name="' . self::$logging_enabled_key . '" id="' . self::$logging_enabled_key . '" value="true" ' . $checked . ' /><br />';
                    $retval .= '<span class="description">Check the box to enable logging (usually for debugging).</span></td>';
                    $retval .= '</tr>';
                    $retval .= '<tr valign="top">';
                    $retval .= '<th scope="row">Log transactions</th>';
                    if($log_txns) { $checked = "checked=\"checked\""; } else { $checked = ""; }
                    $retval .= '<td><input type="checkbox" name="' . self::$logtxns_enabled_key .'" id="' . self::$logtxns_enabled_key . '" value="true" ' . $checked . ' /><br />';
                    $retval .= '<span class="description">Check the box to enable transaction logging (only use for debugging - very resource hungry!)</span></td>';
                    $retval .= '</tr>';
                        
                    if ( !empty( $txn_files ) ) {
                        $retval .= '<tr valign="top">';
                        $retval .= '<th scope="row">Stored Transactions</th>';
                        $retval .= '<td> ' . count($txn_files) . '&nbsp;<input type="button" value="Delete" onclick="sendAjaxDeleteRequest(\'true\');"></td>';
                        $retval .= '</tr>';
                    }
                    $retval .= "</table>";
                    return $retval;                    
                }

		// Draw the option page
		function settings_page() {
			?>
		<div class="wrap">
			
			<!-- Display Plugin Icon, Header, and Description -->
			<div class="icon32" id="icon-options-general"><br></div>
			<h2><?php echo self::$plugin_name; ?> settings<?php if (WISHLISTCAST_DOMAIN == 'wishlistcastpro') echo ' - Pro Version'; ?></h2>
			<?php
			if (isset($err_msg) && $err_msg!='') { ?>
				<p class="error"><?php echo $err_msg; ?></p>
				<p><?php _e('Please correct the errors displayed above and try again.', WISHLISTCAST_DOMAIN); ?></p>
			<?php	
			}
			?>

			<?php
                            echo '
                                <script>
                                        function sendAjaxDeleteRequest(key)
                                        {
                                                jQuery.post("'.get_option('siteurl').'/wp-admin/admin-ajax.php", {action:"ak_attach", "cookie": encodeURIComponent(document.cookie), keyID: key}, function(str)	{
                       jQuery("#current_txns").html(str);
                        });
                                        }
                                </script>';
			$logging_enabled = get_option( self::$logging_enabled_key );
			if (($logging_enabled != 'true') && file_exists(dirname(dirname(__FILE__)) . '/wishlistcast.log') && is_file(dirname(dirname(__FILE__)) . '/wishlistcast.log')) {
				unlink(dirname(dirname(__FILE__)) . '/wishlistcast.log');
			}
                        

			?>
			<div id="current_settings">

			</div>
			<p><?php echo self::$plugin_desc; ?></p>
			<!-- Beginning of the Plugin Options Form -->
			<form method="post" action="options.php">
				<?php settings_fields( self::$settings_group ); ?>
                                <div id="current_txns">
                                <?php echo $this->show_current_txns(); ?>
                                </div>    			    
			    <p class="submit">
			    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			    </p>

			</form>
	
			<?php
			$urlbase = dirname(WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__))) . '/wishlistcast_api.php?role=' . get_site_option( 'default_role', 'subscriber');

			if (class_exists('WLMAPI')) {
				$urlbase .= '&security_code=' . WLMAPI::GetOption('genericsecret');
			}
			?>
			<style type="text/css">
			#url_table th {
			    background-color: #B52200;
			    color: #EEEEEE;
			}
			.even td { background-color: #d5edff; }
			.hover td { background-color: #ffc; }
			.selected td {
				background-color: #6868D8;
				color: #EEEEEE;
			}
			.selected a {
				color: #EEEEEE;
			}
			</style>
			<?php
			if (!class_exists('WLMAPI')) {
			?>
			<h3 style="color:red">WARNING: Wishlist Member installation NOT detected. Is Wishlist Member installed?</h3>
			<?php
			}
			?>
			<h3>Outgoing API URLs to use for Nanacast</h3>
			<p>Use the URLs below to link your Nanacast membership to a specific Wishlist level.</p>
			<table id="url_table">
				<tr>
					<th>Level</th><th>URL</th>
				</tr>
				<tr class="even"><td>Base</td><td><?php echo $urlbase ?></td></tr>
			<?php
			if (class_exists('WLMAPI')) {
				$levels = WLMAPI::GetLevels();
				$indx = 0;
				foreach ($levels as $level => $details) {
					$indx = ($indx+1) % 2;
					echo '<tr' . ($indx == 0 ? ' class="even"' : '') . '><td>' . $details['name'] . '</td><td>' . $urlbase . '&levels=' . $level . '</td></tr>';
				}
			} else {
				?>
				<tr><td><strong>WARNING</strong></td><td><strong>Wishlist installation not detected.</strong></td></tr>
				<?php
			}
			?>
			</table>
			<img src="<?php echo plugins_url('/images/nanacast-setting.jpg', dirname(__FILE__)); ?>" width="648" height="236" />
			<?php
		}
	}
}
?>