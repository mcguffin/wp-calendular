<?php
/**
* @package WPCalendula
* @version 0.1
*/
if ( !class_exists('CalendulaCore') ):

class CalendulaCore {
	public static function init() {
		add_action( 'init' , array( __CLASS__ , 'register_post_types' ) );
		add_action( 'admin_menu' , array( __CLASS__ , 'register_post_types' ) );
	}
	public static function register_post_types() {
		// register post types ...
		register_post_type( 'calendar' , array( 
	//		'label' => __( 'Calendars' , 'calendular' ),
			'labels' => array(
				'name' => __( 'Calendars' , 'calendular' ),
				'singular_name' => __( 'Calendar' , 'calendular' ),
				'menu_name' => __( 'Calendars' , 'calendular' ),
				'all_items' => __( 'All Calendars' , 'calendular' ),
				'add_new' => _x( 'Add new' , 'calendar' , 'calendular' ),
				'add_new_item' => __( 'Add New Calendar' , 'calendular' ),
				'edit_item' => __( 'Edit Calendar' , 'calendular' ),
				'new_item' => __( 'New Calendar' , 'calendular' ),
				'view_item' => __( 'View Calendar' , 'calendular' ),
				'search_items' => __( 'Search Calendars' , 'calendular' ),
				'not_found' => __( 'No calendars found' , 'calendular' ),
				'not_found_in_trash' => __( 'No calendars found in trash' , 'calendular' ),
				'parent_item_colon' => __( 'parent Calendar' , 'calendular' ),
			),
			'description' => __( 'A Calendar is a collection of events.' , 'calendular' ),
			'public' => true,
			'has_archive' => false,
//			'rewrite' => array('slug' => 'calendars'),

			'show_ui' => true,
			'show_in_menu' => true,
			'menu_position' => 41,
			'menu_icon' => plugins_url( 'img/calendar-icon.png' , dirname(__FILE__) ),
			'capability_type' => 'page',
			'hierarchical' => false,
			'supports' => array(
				'title','editor','author',
			),
			'can_export' => true,
			'register_meta_box_cb' => array( 'CalendulaCalendarAdminUI' , 'calendar_meta_boxes' ),
		) );
		
		global $wpdb;
		$has_calendars = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type='calendar' AND post_status NOT IN('auto-draft','trash');" );

		register_post_type( 'event' , array( 
//			'label' => __( 'Events' , 'calendular' ),
			'labels' => array(
				'name' => __( 'Events' , 'calendular' ),
				'singular_name' => __( 'Event' , 'calendular' ),
				'menu_name' => __( 'Events' , 'calendular' ),
				'all_items' => __( 'All Events' , 'calendular' ),
				'add_new' => _x( 'Add new' , 'event' , 'calendular' ),
				'add_new_item' => __( 'Add New Event' , 'calendular' ),
				'edit_item' => __( 'Edit Event' , 'calendular' ),
				'new_item' => __( 'New Event' , 'calendular' ),
				'view_item' => __( 'View Event' , 'calendular' ),
				'search_items' => __( 'Search Events' , 'calendular' ),
				'not_found' => __( 'No event found' , 'calendular' ),
				'not_found_in_trash' => __( 'No event found in trash' , 'calendular' ),
				'parent_item_colon' => __( 'Calendar' , 'calendular' ),
			),
			'description' => __( 'Events are gathered in a calendar.' , 'calendular' ),
			'public' => false,
			'show_ui' => (bool)$has_calendars,
			'show_in_menu' => (bool)$has_calendars,
			'menu_position' => 42,
			'menu_icon' => plugins_url( 'img/event-icon.png' , dirname(__FILE__) ),
			'capability_type' => 'post',
			'hierarchical' => false,
			'supports' => array(
				'title','editor','author',
			),
			'can_export' => true,
			'register_meta_box_cb' => array( 'CalendulaEventAdminUI' , 'event_meta_boxes' ),
			'has_archive' => false,
			'rewrite' => false,
		) );
		// add event to Calendar menu

	}
}

CalendulaCore::init();

endif;