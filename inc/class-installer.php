<?php
/**
* @package WPCalendula
* @version 0.1
*/


class CalendularInstaller {
	
	public static function activate() {
		CalendulaCore::register_post_types();
		flush_rewrite_rules();
		// var_dump
	}
	public static function deactivate() {
		flush_rewrite_rules();
		// do this in all blogs. Fucken.
		global $wpdb;
		
		if ( is_multisite() && is_network_admin() ) {
			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ( $blogids as $blog_id) {
				switch_to_blog($blog_id);
				self::clear_cron( );
				restore_current_blog();
			}
		} else {
			self::clear_cron( );
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