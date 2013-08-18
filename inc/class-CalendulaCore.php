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
			'label' => __( 'Calendars' , 'calendula' ),
			'description' => __( 'A Calendar is a collection of events.' , 'calendula' ),
			'public' => true,
			'has_archive' => true,
			'rewrite' => array('slug' => 'calendars'),

			'show_ui' => true,
			'show_in_menu' => true,
			'menu_position' => 41,
//			'menu_icon' => ...,
			'capability_type' => 'posts',
			'hierarchical' => false,
			'supports' => array(
				'title','editor','author',
			),
			'can_export' => true,
			'register_meta_box_cb' => array( 'CalendulaAdminUI' , 'calendar_meta_boxes' ),
		) );
		
		register_post_type( 'event' , array( 
			'label' => __( 'Events' , 'calendula' ),
			'description' => __( 'Events are gathered in a calendar.' , 'calendula' ),
			'public' => true,
			'show_ui' => true,
			'show_in_menu' => false,
			'menu_position' => 42,
//			'menu_icon' => ...,
			'capability_type' => 'posts',
			'hierarchical' => false,
			'supports' => array(
				'title','editor','author','excerpt',
			),
			'can_export' => true,
			'register_meta_box_cb' => array( 'CalendulaAdminUI' , 'event_meta_boxes' ),
			'has_archive' => true,
			'rewrite' => true,
		) );
		// add event to Calendar menu

	}
}

CalendulaCore::init();

endif;