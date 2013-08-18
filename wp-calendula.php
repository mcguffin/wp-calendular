<?php
/**
* @package WPCalendula
* @version 0.1
*/

/*
Plugin Name: WP-Calendula
Plugin URI: http://wordpress.org/plugins/
Description: Simple Wordpress Calendar
Author: Joern Lund
Version: 0.9.0b
Author URI: https://github.com/mcguffin

Text Domain: dashboardmessages
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

add_action('wpmu_upgrade_site','calandular_upgrade_blog',10);

function calendular_activate() {
	require_once( dirname(__FILE__). '/inc/class-Installer.php' );
	CalendularInstaller::activate();
}
function calendular_deactivate() {
	require_once( dirname(__FILE__). '/inc/class-Installer.php' );
	CalendularInstaller::deactivate();
}

require_once( dirname(__FILE__). '/inc/vendor/iCalcreator/iCalcreator.class.php' );
require_once( dirname(__FILE__). '/inc/class-Calendar.php' );
require_once( dirname(__FILE__). '/inc/class-CalendulaAdminUI.php' );
require_once( dirname(__FILE__). '/inc/class-CalendulaCore.php' );
require_once( dirname(__FILE__). '/inc/class-CalendulaFrontend.php' );

register_activation_hook( __FILE__ , 'calendular_activate' );
register_deactivation_hook( __FILE__ , 'calendular_deactivate' );



?>