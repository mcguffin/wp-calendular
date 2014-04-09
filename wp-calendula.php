<?php
/**
* @package WPCalendula
* @version 0.1
*/

/*
Plugin Name: WP-Calendula
Plugin URI: https://github.com/mcguffin/wp-calendular
Description: Simple Wordpress Calendar.
Author: Joern Lund
Version: 0.9.0b
Author URI: https://github.com/mcguffin

Text Domain: calendular
Domain Path: /lang/
*/

function is_calendula_active_for_network( ) {
	if ( ! is_multisite() )
		return false;
	if ( ! function_exists( 'is_plugin_active_for_network' ) )
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

	return is_plugin_active_for_network( basename(dirname(__FILE__)).'/'.basename(__FILE__) );
}
function calandular_upgrade_blog( $blog_id ) {
	switch_to_blog( $blog_id );
	if ( ! function_exists( 'is_plugin_active' ) )
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

	if ( is_plugin_active( basename(dirname(__FILE__)).'/'.basename(__FILE__) ) )
		calendular_activate();
	else 
		calendular_deactivate();
	
	restore_current_blog();
}
function calandular_plugin_loaded() {
	load_plugin_textdomain( 'calendular' , false, dirname(plugin_basename( __FILE__ )) . '/lang');
}


add_action('wpmu_upgrade_site','calandular_upgrade_blog',10);
add_action( 'plugins_loaded' , 'calandular_plugin_loaded' );

function calendular_activate() {
	require_once( dirname(__FILE__). '/inc/class-Installer.php' );
	CalendularInstaller::activate();
}
function calendular_deactivate() {
	require_once( dirname(__FILE__). '/inc/class-Installer.php' );
	CalendularInstaller::deactivate();
}




function calendular_cron_periods( $schedules ) {
	// Adds once weekly to the existing schedules.
	$day = 3600*24;
	if ( ! isset($schedules['weekly']) ) {
		$schedules['weekly'] = array(
			'interval' => $day*7,
			'display' => __( 'Once Weekly' )
		);
	}
	if ( ! isset($schedules['monthly']) ) {
		$schedules['monthly'] = array(
			'interval' => $day*30,
			'display' => __( 'Every 30 Days','calendular' )
		);
	}
	if ( ! isset($schedules['yearly']) ) {
		$schedules['yearly'] = array(
			'interval' => $day*365,
			'display' => __( 'Once a Year', 'calendular' )
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'calendular_cron_periods' );


function calendar_cron_yearly() {
	calendar_cron_sync('yearly');
}
function calendar_cron_monthly() {
	calendar_cron_sync('monthly');
}
function calendar_cron_weekly() {
	calendar_cron_sync('weekly');
}
function calendar_cron_daily() {
	calendar_cron_sync('daily');
}
function calendar_cron_sync( $period = 'yearly' ){
	global $wpdb;
	$period = strtoupper($period);
	$query = "SELECT p.ID FROM $wpdb->posts AS p
			INNER JOIN $wpdb->postmeta AS m1 
			ON p.ID = m1.post_id AND m1.meta_key='_calendar_type' AND m1.meta_value='remote' 

			INNER JOIN $wpdb->postmeta AS m2 
			ON p.ID = m2.post_id AND m2.meta_key='_calendar_remote_sync_interval' AND m2.meta_value='$period' 
			
			WHERE p.post_type='calendar'";
	$res = $wpdb->get_col($query);
	
	foreach ( $res as $ID )
		RemoteCalendar::synchronize_calendar($ID);
}

add_action('calendar_cron_yearly' , 'calendar_cron_yearly' );
add_action('calendar_cron_monthly' , 'calendar_cron_monthly' );
add_action('calendar_cron_weekly' , 'calendar_cron_weekly' );
add_action('calendar_cron_daily' , 'calendar_cron_daily' );


//add_action('init','calendar_cron_weekly',99);

require_once( dirname(__FILE__). '/inc/vendor/iCalcreator/iCalcreator.class.php' );
require_once( dirname(__FILE__). '/inc/class-Calendar.php' );
require_once( dirname(__FILE__). '/inc/class-CalendulaCalendarAdminUI.php' );
require_once( dirname(__FILE__). '/inc/class-CalendulaEventAdminUI.php' );
require_once( dirname(__FILE__). '/inc/class-CalendulaCore.php' );
require_once( dirname(__FILE__). '/inc/class-CalendulaFrontend.php' );

register_activation_hook( __FILE__ , 'calendular_activate' );
register_deactivation_hook( __FILE__ , 'calendular_deactivate' );





?>
