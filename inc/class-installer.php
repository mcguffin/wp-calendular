<?php
/**
* @package WPCalendula
* @version 0.1
*/


class CalendularInstaller {
	
	public static function activate() {
		// check if 
		if ( is_calendula_active_for_network( ) ) {
			global $wp_rewrite;
			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ( $blogids as $blog_id) {
				switch_to_blog($blog_id);
				CalendulaCore::register_post_types();
				$wp_rewrite->init();
				flush_rewrite_rules();
				restore_current_blog();
			}
		} else {
			CalendulaCore::register_post_types();
			flush_rewrite_rules();
		}
	}
	public static function deactivate() {
		flush_rewrite_rules();
		// do this in all blogs. Fucken.
		global $wpdb,$wp_rewrite;
		
		if ( is_multisite() && is_network_admin() ) {
			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ( $blogids as $blog_id) {
				switch_to_blog($blog_id);
				self::clear_cron( );

				flush_rewrite_rules();


				restore_current_blog();


			}
		} else {
			self::clear_cron( );
			flush_rewrite_rules();
		}
	}
	private static function clear_cron(){
		foreach ( array('daily','weekly','monthly','yearly') as $sync_interval ) {
			$cron_task_hook = "calendar_cron_{$sync_interval}";
			wp_clear_scheduled_hook( $cron_task_hook );
		}
	}
}

?>