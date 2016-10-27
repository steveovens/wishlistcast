<?php
	// If uninstall not called from Wordpress then exit
	if (!defined('WP_UNINSTALL_PLUGIN')) {
		exit();
	}

	delete_option('wlc_logging');
        delete_option('wlc_logtxns');
?>