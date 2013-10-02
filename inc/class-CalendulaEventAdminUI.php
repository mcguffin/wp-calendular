<?php
/**
* @package WPCalendula
* @version 0.1
*/
if ( !class_exists('CalendulaEventAdminUI') ):

class CalendulaEventAdminUI {
	private static $_current_calendar = 0;
	
	public static function init() {
		if ( is_admin() ) {
			// add meta boxes...
			add_action( 'save_post' , array(__CLASS__,'update_event_meta') , 10 , 2 );
			add_action( 'load-post.php' , array( __CLASS__ , 'change_redirect_after_delete' ) );
			
			
			add_action( 'load-post.php' , array( __CLASS__ , 'enqueue_scripts' ) );
			add_action( 'load-post-new.php' , array( __CLASS__ , 'enqueue_scripts' ) );
			
			add_filter( 'manage_event_posts_custom_column' , array( __CLASS__ , 'print_custom_column' ),10,2);
			add_filter( 'manage_event_posts_columns' , array( __CLASS__ , 'add_custom_columns' ));
		
		
		}
		wp_register_style( 'jquery-ui-all' , plugins_url('/css/jquery-ui-1.10.3.custom.min.css' , dirname(__FILE__) ) );
		wp_register_script( 'modernizr' , plugins_url('/js/modernizr.custom.85851.js' , dirname(__FILE__) ) );
		wp_register_script( 'jquery-ui-timepicker' , plugins_url('/js/jquery-ui-timepicker-addon.min.js' , dirname(__FILE__) ) , array( 'jquery-ui-datepicker','jquery-ui-slider' ) );

		wp_register_style( 'calendular-admin' , plugins_url('/css/calendular-admin.css' , dirname(__FILE__) ) , array('jquery-ui-all'));
		wp_register_script( 'calendular_admin' , plugins_url('/js/calendular-admin.js' , dirname(__FILE__) ) , array('modernizr','jquery','jquery-ui-timepicker'),'1.1');
	//	add_action( 'admin_init', array( __CLASS__ , 'enqueue_scripts' ) );
	}
	
	static function add_custom_columns( $columns ) {
		$columns['calendar'] = __('Calendar','calendular');
		return $columns;
	}
	static function print_custom_column( $column , $post_ID ) {
		switch ( $column ) {
			case 'calendar':
				$event = get_post( $post_ID );
				$cal = get_post( $event->post_parent );
				$url = get_edit_post_link( $cal->ID );
				$title = $cal->post_title;
				if ( $post_ID != $cal->ID )
					printf( '<a href="%s">%s</a>',$url,$title );
				else
					_e( '(No calendar)' , 'calendular' );
		}
	}
	
	/* event */
	public static function enqueue_scripts() {
		if ($_REQUEST['post_type']=='event') {
			wp_enqueue_style( 'jquery-ui-all' );
			wp_enqueue_style('calendular-admin');
		
			wp_enqueue_script('modernizr');
			wp_enqueue_script('calendular_admin');
		}
	}
	
	/* calendar */
	public static function change_redirect_after_delete() {
		if ( isset( $_GET['post'] ) )
			$post_id = (int) $_GET['post'];
		elseif ( isset( $_POST['post_ID'] ) )
			$post_id = (int) $_POST['post_ID'];
		else 
			return;
		
		// deleting?
		wp_reset_vars( array( 'action' ) );
		global $action;
		if ( $action == 'delete' && false !== ( $post = get_post( $post_id ) ) ) {
			if ( 'event' == $post->post_type ) {
				self::$_current_calendar = $post->post_parent;
				add_action( 'wp_redirect' , array( __CLASS__ , 'edit_current_calendar_url' ) );
			}
		}
	}
	public static function edit_current_calendar_url( $location ) {
		$location = add_query_arg( 'post' , self::$_current_calendar , admin_url( 'post.php' ));
		$location = add_query_arg( 'action' , 'edit' , $location );
		return $location;
	}
	
	public static function update_event_meta( $post_ID , $post ) {
		if ( $post->post_type == 'event' && isset( $_POST['event'] ) ) {
			$event = wp_parse_args($_POST['event'] , array(
				'_event_start'			=> array(''),
				'_event_end'			=> array(''),
				'_event_full_day'		=> false,
				
				/*
				'_event_repeat_freq'	=> false, // YEARLY | MONTHLY | WEEKLY | DAYLY
				'_event_repeat_interval'=> false, // @ ! 1 : nextone must be _event_start->month
				
				'_event_repeat_bymonth'	=> false, // not implemented
				'_event_repeat_count'	=> false, // not implemented
				'_event_repeat_until'	=> false, // not implemented
				*/
			//	'_event_icaldata'	=> '',
			) );
			
			if ( ! $event['_event_full_day'] ) {
				$event['_event_start']	= trim( implode(' ' , $event['_event_start']));
				$event['_event_end']	= trim( implode(' ' , $event['_event_end']));
			} else {
				$event['_event_start']	= trim($event['_event_start']['date'] );
				$event['_event_end']	= trim($event['_event_end']['date'] );
			}
			
			foreach ( $event as $key => $value ) {
				if ( $value === false )
					delete_post_meta( $post_ID , $key );
				else
					update_post_meta( $post_ID , $key , $value );
			}
		}
	}
	
	
	private function get_local_calendars() {
		$query_args = array(
			'post_type' => 'calendar',
			'meta_key' => '_calendar_type',
			'meta_value' => 'local',
		);
		return get_posts( $query_args );
	}
	
	public static function calendar_meta_boxes() {
		// type remote(url) / type local
		// on main blog: [|] networkwide
		add_meta_box( 'calendar_events' , __( 'Events','calendular' ) , array(__CLASS__, 'events_meta_box') , 'calendar' , 'normal' , 'default' );
	}
	
	public static function event_meta_boxes() {
		add_meta_box( 'event_options' , __( 'Event' ) , array(__CLASS__, 'event_meta_box') , 'event' , 'side' , 'default' );
	}
	public static function event_meta_box($post) {
		$start				=	get_post_meta( $post->ID , '_event_start'			,true);
		$end				=	get_post_meta( $post->ID , '_event_end'				,true);
		$full_day			=	get_post_meta( $post->ID , '_event_full_day'		,true);

		$repeat_freq		=	get_post_meta( $post->ID , '_event_repeat_freq'		,true);
		$repeat_interval	=	get_post_meta( $post->ID , '_event_repeat_interval'	,true);
		$repeat_bymonth		=	get_post_meta( $post->ID , '_event_repeat_bymonth'	,true);
		$repeat_count		=	get_post_meta( $post->ID , '_event_repeat_count'	,true);
//		$repeat_until		=	get_post_meta( $post->ID , '_event_repeat_until'	,true);
		
		$seconds = get_option( 'gmt_offset' )*3600;

		if ( ! $start )
			$start = date( 'Y-m-d H:00:00' ,  time() + 60*60 + $seconds);
		if ( ! $end )
			$end = date( 'Y-m-d H:00:00' , time() + 2*60*60 + $seconds );
		
		$startdate = date('Y-m-d',strtotime( $start ));
		$enddate = date('Y-m-d',strtotime( $end ));
		$starttime = date('H:i',strtotime( $start ));
		$endtime = date('H:i',strtotime( $end ));
		
		
		if ( ! $repeat_interval )
			$repeat_interval = 1;
		
		//if ( ! $repeat_count )
		//	$repeat_count = 1;
		
		if ( ! $post->post_parent && isset( $_REQUEST['post_parent'] ) ) {
			$calendar = get_post( $_REQUEST['post_parent'] );
			?><input type="hidden" name="post_parent" value="<?php echo (int) $_REQUEST['post_parent'] ?>" /><?php
		} else if ( ! $post->post_parent ) {
			
		} else {
			$calendar = get_post( $post->post_parent );
		}
		
		
		?><div class="event-options misc-pub-section"><?php
			?><p><label for="calendar-post-parent"><?php
				_e( 'Calendar:' , 'calendular' );
				?></label><?php
				
				if ( isset( $calendar ) ) {
					?><?php
					$edit_url = get_edit_post_link($calendar->ID);
					?><a href="<?php echo $edit_url ?>"><?php echo $calendar->post_title; ?></a><?php
				} else {
					$calandars = self::get_local_calendars();
					?><select id="calendar-post-parent" name="post_parent"><?php
						foreach ( $calandars as $cal ) {
							?><option <?php selected($cal->ID,$post->post_parent,true) ?> value="<?php echo $cal->ID ?>"><?php echo $cal->post_title ?></option><?php
						}
					?></select><?php
				}				
				
			?></p><?php

		?></div><?php

		?><div class="event-options misc-pub-section"><?php
			?><h4><?php _e( 'Date and Time' , 'calendular' ) ?></h4><?php
			?><p><?php
				?><input type="checkbox" id="fullday" name="event[_event_full_day]" value="1" <?php checked( $full_day , 1 , true ) ?>><?php
				?><label for="fullday"><?php
					_e('This is a full day event','calendular');
				?></label><?php
			?></p><?php
			
			?><p><?php
				?><label for="start-date"><?php
					_e('Start Date','calendular');
				?></label><?php
				?><input type="date" id="start-date" name="event[_event_start][date]" value="<?php echo $startdate ?>"><?php
				
				?><span class="select-event-time"><?php
					?><br /><?php
					?><label for="start-date"><?php
						_e('Time','calendular');
					?></label><?php
					?><input type="time" id="start-time" name="event[_event_start][time]" value="<?php echo $starttime ?>"><?php
				?></span><?php
			?></p><?php
			
			?><p><?php
				?><label for="end-date"><?php
					_e('End Date','calendular');
				?></label><?php
				?><input type="date" id="end-date" name="event[_event_end][date]" value="<?php echo $enddate ?>"><?php
				
				?><span class="select-event-time"><?php
					?><br /><?php
					?><label for="end-date"><?php
						_e('Time','calendular');
					?></label><?php
					?><input type="time" id="end-time" name="event[_event_end][time]" value="<?php echo $endtime ?>"><?php
				?></span><?php
			?></p><?php
			
		?></div><?php
		// only option we have ... there is no filter for "new_post_link"
		?><script type="text/javascript">
		jQuery(document).ready(function($){
			$('a.add-new-h2').attr('href',"<?php echo add_query_arg( 'post_parent' , $post->post_parent , 'post-new.php?post_type=event' ) ?>");
		});
		
		</script><?php
			
			/*
		?><div class="event-options misc-pub-section"><?php
			?><p><?php
				//checkbox [  ] repeat
				?><label for="repeat-freq"><?php
				//	$sel_itvl = '<input type="number" class="event-repeat-interval small-input" min="1" id="start-date" name="event[_event_repeat][interval]" value="'. $repeat_interval.'" />';
					$sel_freq = '<select id="repeat-freq" name="event[_event_repeat_freq]">'.
						'<option '.selected($repeat_freq,'',false).' value="">' .__("Don't repeat",'calendular') .'</option>'.
						'<option '.selected($repeat_freq,'DAILY',false).' value="DAILY">' .__("Daily",'calendular') .'</option>'.
						'<option '.selected($repeat_freq,'WEEKLY',false).' value="WEEKLY">' .__("Weekly",'calendular'). '</option>'.
						'<option '.selected($repeat_freq,'MONTHLY',false).' value="MONTHLY">' .__("Monthly",'calendular') .'</option>'.
						'<option '.selected($repeat_freq,'YEARLY',false).' value="YEARLY">' .__("Yearly",'calendular') .'</option>'.
						'</select>';
					
//					printf( __('Repeat %1$s every %2$s','calendular') , $sel_freq , $sel_itvl
					printf( __('Repeat %1$s','calendular') , $sel_freq 
					);

				?></label><?php
			?></p><?php
			?><p><?php
				?><input type="checkbox" value="1" id="repeat-count-enable" <?php checked( (bool)$repeat_count , true , true ) ?> name="event[_event_repeat_count][enable]" /><?php

				
				?><label for="repeat-count-enable"><?php
					_e( 'Repeat' , 'calendular' )
				?></label><?php

				?><input id="repeat-count" type="number" name="event[_event_repeat_count][count]"  class="event-repeat-interval small-input" min="1" value="<?php echo $repeat_count; ?>"  /><?php
				
				?><label for="repeat-count"><?php
					_e( 'Times' , 'calendular' )
				?></label><?php
				
			?></p><?php

			
		?></div><?php
			*/
	}
}

CalendulaEventAdminUI::init();

endif;