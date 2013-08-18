<?php
/**
* @package WPCalendula
* @version 0.1
*/
if ( !class_exists('CalendulaCore') ):

class CalendulaAdminUI {
	private static $_current_calendar = 0;
	
	public static function init() {
		if ( is_admin() ) {
			// add meta boxes...
			add_action( 'save_post' , array(__CLASS__,'update_calendar_meta') , 10 , 2 );
			add_action( 'load-post.php' , array( __CLASS__ , 'change_redirect_after_delete' ) );
			add_action( 'load-post.php' , array( __CLASS__ , 'include_datepicker' ) );
			add_action( 'load-post-new.php' , array( __CLASS__ , 'include_datepicker' ) );
		//	add_action( '' );
		}
	}
	public static function include_datepicker(){
		add_action( 'admin_enqueue_scripts', array( __CLASS__ , 'enqueue_date_picker' ) );
		add_action( 'admin_head', array( __CLASS__ , 'print_date_picker_init' ) );
	}
	public function print_date_picker_init(){
		?><script type="text/javascript">
			jQuery(document).ready(function($){
				if ( ! Modernizr.inputtypes.date )
					jQuery('input[type="date"]').datepicker({ dateFormat: "yy-mm-dd" });
				if ( ! Modernizr.inputtypes.time )
					jQuery('input[type="time"]').timepicker();
					
				
				$('#fullday').change(function(){
					$('.select-event-time').css( 'display' , $(this).attr('checked') ? 'none' : 'inline' );
				}).trigger('change');
				
			});
		</script><?php
		?><style type="text/css">
		.input-number,
		input[type='number'] {
			width:3em;
		}
		#calendar_options .inside,
		#event_options .inside {
			padding:0;
		}
		
		
		.date-sheet {
			position:relative;
			float:left;
			background:#ffffff;
			box-shadow:1px 1px 5px rgba(0,0,0,0.5);
			padding:0;
			text-align:center;
			width:4em;
		}
		.date-sheet .month {
			padding:0.125em;
			font-size:0.8em;
			color:#fff;
			background-color:#990000;
			font-weight:700;
		}
		.date-sheet .day {
			padding:0.125em;
			font-size:1.5em;
			color:#000;
			font-weight:700;
		}
		.date-sheet .year {
			padding:0.125em;
			font-size:1.0em;
			color:#666;
			background-color:#dfdfdf;
		}
		.date-col .time {
			position:relative;
			float:left;
			margin-left:0.5em;
			padding:0.5em;
			font-size:1.0em;
			color:#666;
			background:#ffffff;
			box-shadow:1px 1px 5px rgba(0,0,0,0.5);
		}
		
		</style><?php
	}
	public static function enqueue_date_picker(){
		wp_enqueue_script( 'modernizr' , plugins_url('/js/modernizr.custom.85851.js' , dirname(__FILE__) ) );
		
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'jquery-ui-slider' );
		
		wp_enqueue_style( 'jquery-ui-all' , plugins_url('/css/jquery-ui-1.10.3.custom.min.css' , dirname(__FILE__) ) );
		wp_enqueue_script( 'jquery-ui-timepicker' , plugins_url('/js/jquery-ui-timepicker-addon.min.js' , dirname(__FILE__) ) , array( 'jquery-ui-datepicker' ) );
	}
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
	
	public static function update_calendar_meta( $post_ID , $post ) {
		if ( $post->post_type == 'calendar' && isset( $_POST['calendar'] ) ) {
			$calendar = wp_parse_args($_POST['calendar'] , array(
				'sync'						=> false,
				'_calendar_type'			=> 'local',
				'_calendar_remote_url'		=> '',
				'_calendar_publish_feed'	=> false,
				'_calendar_is_networkwide'	=> false,
				'_calendar_remote_sync_interval'		=> 0,
			) );
			
			// detect changes in 
			
			foreach ( $calendar as $key => $value )
				update_post_meta( $post_ID , $key , $value );
			if ( 'remote' === $calendar['_calendar_type'] ) {
				$cal = new RemoteCalendar();
				$cal->init( $post , $calendar );
			} else {
				
			}
		} else if ( $post->post_type == 'event' && isset( $_POST['event'] ) ) {
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
			
			/*
				more checks for time:
				- start is valid date
				- start < end
			*/
			
			if ( ! $event['_event_full_day'] ) {
				$event['_event_start']	= trim( implode(' ' , $event['_event_start']));
				$event['_event_end']	= trim( implode(' ' , $event['_event_end']));
			} else {
				$event['_event_start']	= trim($event['_event_start']['date'] );
				$event['_event_end']	= trim($event['_event_end']['date'] );
			}
			
			/*
			if ( isset($event['_event_repeat_count']['enable']) )
				$event['_event_repeat_count'] = $event['_event_repeat_count']['count'];
			else 
				$event['_event_repeat_count'] = false;

			if ( isset($event['rrule']))
				;
			*/
			
			
			foreach ( $event as $key => $value ) {
				if ( $value === false )
					delete_post_meta( $post_ID , $key );
				else
					update_post_meta( $post_ID , $key , $value );
			}
			// calc event from icaldata
			
			
/*			
			$vcal_config = array(
				'unique_id' => get_permalink( $post->post_parent ),
			);
			$calendar = new vcalendar( $vcal_config );
			$vevent =& $calendar->newComponent( 'vevent' );
			$vevent->setProperty( 'uid' , preg_replace('@https?://@','',get_permalink( $post->ID ) ) );
//			$vevent->setProperty( 'name' , $post->post_title );
			if ( ! empty( $post->post_content ) )
				$vevent->setProperty( 'summary' , $post->post_content );
			else
				$vevent->setProperty( 'summary' , $post->post_title );
			
			$start_vdate = Calendar::sql_to_vcal_date( $event['_event_start'] );
			$end_vdate = Calendar::sql_to_vcal_date( $event['_event_end'] );
			
			$vevent->setProperty( 'dtstart' , $start_vdate );
			$vevent->setProperty( 'dtend'  , $end_vdate );
			if ( ! empty( $rrule ) )
				$vevent->setProperty( 'RRULE' , $rrule );


			
			update_post_meta( $post_ID , '_event_vcaldata' , $vevent->createComponent($vcalendar->xcaldecl) );
			*/
		}
	}
	
	
	public static function calendar_meta_boxes() {
		// type remote(url) / type local
		// on main blog: [|] networkwide

		add_meta_box( 'calendar_options' , __( 'Calendar','calendula' ) , array(__CLASS__, 'calendar_meta_box') , 'calendar' , 'side' , 'default' );
		add_meta_box( 'calendar_events' , __( 'Events','calendula' ) , array(__CLASS__, 'events_meta_box') , 'calendar' , 'normal' , 'default' );
	}
	public static function calendar_meta_box( $post ) {
		$type = get_post_meta( $post->ID , '_calendar_type' , true );
		if ( !$type )
			$type = 'local';

		// remote only
		$remote_url = get_post_meta( $post->ID , '_calendar_remote_url' , true );
		
		// local only
		$publish_feed = get_post_meta( $post->ID , '_calendar_publish_feed' , true );
		
		$is_networkwide = get_post_meta( $post->ID , '_calendar_is_networkwide' , true );
		
		
		$last_sync = get_post_meta( $post->ID ,'_calendar_remote_last_sync',true);
		$sync_interval = get_post_meta( $post->ID ,'_calendar_remote_sync_interval',true);
		
		?><div class="calendar-postmeta">
			<div class="misc-pub-section" id="calendar-settings">
			<p>
			<label for="calendar-type"><?php _e( 'Calendar Type' , 'calendula' ) ?></label>
			<select name="calendar[_calendar_type]" id="calendar-type-select">
				<option value="local" <?php selected($type,'local',true) ?>><?php _e('Local calendar','calendula') ?></option>
				<option value="remote"<?php selected($type,'remote',true) ?>><?php _e('Calendar subscription','calendula') ?></option>
			</select>
			</p>
				<p class="description"><?php _e( 'In a calendar subscription you specify an URL from where to load the events. In a local calendar you create events right on your blog' , 'calendular' ); ?></p>
			</div>
			<div class="calendar-type-settings remote" id="calendar-settings-remote">
				<div class="misc-pub-section" id="calendar-settings-remote">
					<h4><?php _e('Remote Settings','calendular') ?></h4>
					<p>
						<label for="calendar-remote-url"><?php _e( 'Remote URL:' , 'calendula' ) ?></label>
						<input type="text" name="calendar[_calendar_remote_url]" id="calendar-remote-url" value="<?php echo $remote_url ?>" />
					</p>
					<p class="description"><?php _e( 'Enter the URL of the calendar. The Application will only accept data in vCalendar format.' , 'calendular' ); ?></p>
				
				
				</div>
				<div class="misc-pub-section">
					<h4><?php _e('Syncing','calendular') ?></h4>
					<p>
						<label for="calendar-remote-sync-interval"><?php _e( 'Sync Calendar:' , 'calendula' ) ?></label>
						<select name="calendar[_calendar_remote_sync_interval]" id="calendar-remote-sync-interval">
							<option value="0" <?php selected($sync_interval,0,true) ?>><?php _e("Don't sync",'calendula'); ?></option>
							<option value="DAILY" <?php selected($sync_interval,'DAILY',true) ?>><?php _e("Daily",'calendula'); ?></option>
							<option value="WEEKLY" <?php selected($sync_interval,'WEEKLY',true) ?>><?php _e("Weekly",'calendula'); ?></option>
							<option value="MONTHLY" <?php selected($sync_interval,'MONTHLY',true) ?>><?php _e("Monthly",'calendula'); ?></option>
							<option value="YEARLY" <?php selected($sync_interval,'YEARLY',true) ?>><?php _e("Yearly",'calendula'); ?></option>
						</select>
						<button type="submit" name="calendar[sync]" value="1" class="button secondary"><?php _e( 'Sync now!' , 'calendula' ) ?></button>
					</p><?php
					?><p><?php 
						if ( $last_sync ) {
						printf( __( 'Last Sync: %1$s, %2$s' , 'calendula' ) , 
							date_i18n( get_option( 'date_format' ) , strtotime($last_sync) ,false) , 
							date_i18n( get_option( 'time_format' ) , strtotime($last_sync) ,false) 
						); 
					} else {
						_e( 'Never synced' );
					}
					?></p><?php
				
				?></div>
			</div>
			<div class="calendar-type-settings misc-pub-section local" id="calendar-settings-local"><?php
			_e( 'See this calandar in ' , 'calendular' );
				?><a href="<?php echo get_permalink(); ?>"><?php _e( 'HTML' , 'calendular' ) ?></a><?php
				?> | <?php
				?><a href="<?php echo add_query_arg('calendar_format','vcf',get_permalink()); ?>"><?php _e( 'vCal' , 'calendular' ) ?></a><?php
			// no local settings yet
			?></div>
			
			
			<?php  
			if ( is_multisite() && is_main_site() ) {
			?>
			<div class="misc-pub-section" id="calendar-settings">
				<h4><?php _e('Network','calendular') ?></h4>
				<p>
					<input type="checkbox" name="calendar[_calendar_is_networkwide]" value="1" id="calendar-networkwide" <?php checked( $is_networkwide , 1 , true ); ?> />
					<label for="calendar-networkwide"><?php _e( 'This is a Network wide calendar.' , 'calendula' ) ?></label>
				</p>
				<p class="description"><?php _e( 'Events in this Calendar will appear in all calendars in the Network.' , 'calendular' ); ?></p>
			</div>
			<?php  
			}
			?>
			<script type="text/javascript">
			(function($){
				$(document).ready(function(){
					$('#calendar-type-select').on('change',function(event){ 
						$('.calendar-type-settings').css('display','none');
						$('#calendar-settings-'+$(this).val()).css('display','block');
					}).trigger('change');
				});
			})(jQuery);
			</script>

		</div><?php
	}
	public static function events_meta_box( $post ) {
		$cal = new Calendar( $post->ID );
		$is_remote = get_post_meta( $post->ID , '_calendar_type' , true ) === 'remote';
//		$events = $cal->getEventPosts( );
		
		
		
		
		?><div class="calendar-events"><?php
			?><div class="calendar-events-head"><?php
				// some helptext
				if ( true || ! $is_remote && current_user_can( 'create_posts' ) ) {
					$create_url = 
					$create_url = add_query_arg( 'post_type' , 'event' , admin_url( 'post-new.php' ) );
					$create_url = add_query_arg( 'action' , 'edit' , $create_url );
					$create_url = add_query_arg( 'post_parent' , $post->ID , $create_url );
					?><a href="<?php echo $create_url ?>" class="button secondary"><?php _e('Create new Event','calendula') ?></a><?php
				}
			?></div><?php
			
			?><div class="calendar-events-list"><?php
			?><table class="wp-list-table widefat"><?php
				global $post;
				$query = $cal->scheduleEventPosts();
				while ( $query->have_posts() ) : $query->the_post();
					$start = strtotime(get_post_meta($query->post->ID , '_event_start' , true ));
					$full_day = get_post_meta($query->post->ID , '_event_full_day' , true )
					?><tr><?
						?><td class="date-col"><?php
							?><div class="date-sheet"><?php
								?><div class="month"><?php
									echo strftime('%b',$start);
								?></div><?php
								?><div class="day"><?php
									echo strftime('%e',$start);
								?></div><?php
								?><div class="year"><?php
									echo strftime('%Y',$start);
								?></div><?php
							?></div><?php
							
							if ( ! $full_day ) {
								?><div class="time">〈⃝ <?php
									echo strftime('%H:%M',$start);
								?></div><?php
						 	}
						?></td><?php
						?><td><?php 
							echo $query->post->post_title; 
							// consider post_status, draft, private, ...
						?></td><?php
						/*
						?><td><?php 
							// repeating, full_day
						
						?></td><?php
						*/
						if ( ! $is_remote ) {
							?><td><?php 
								if ( current_user_can( 'edit_post' , $query->post->ID ) ) {
									$edit_url = get_edit_post_link($query->post->ID);
									?><a href="<?php echo $edit_url ?>"><?php _e('Edit') ?></a><?php
								}
								if ( current_user_can( 'delete_post' , $query->post->ID ) ) {
									$nonce = wp_create_nonce( 'delete-post_' . $query->post->ID );
									$del_url = add_query_arg( 'action','delete' );
									$del_url = add_query_arg( 'post' , $query->post->ID , $del_url );
									$del_url = add_query_arg( '_wpnonce' , $nonce , $del_url );
									?><a href="<?php echo $del_url ?>"><?php _e('Delete') ?></a><?php
								}
							?></td><?php
						}
					?></tr><?php
					
				endwhile;
				/*
				foreach ( $events as $event ) {
					
					?><div class="calendar-event"><?php
						// + date, + edit-link + delete link
					?></div><?php
				}
				*/
				// some helptext
				?></table><?php
			?></div><?php
		?></div><?php
		
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
		$starttime = date('H:i:00',strtotime( $start ));
		$endtime = date('H:i:00',strtotime( $end ));
		
		
		if ( ! $repeat_interval )
			$repeat_interval = 1;
		
		//if ( ! $repeat_count )
		//	$repeat_count = 1;
		
		if ( ! $post->post_parent && isset( $_REQUEST['post_parent'] ) ) {
			$calendar = get_post( $_REQUEST['post_parent'] );
			?><input type="hidden" name="post_parent" value="<?php echo (int) $_REQUEST['post_parent'] ?>" /><?php
		} else {
			$calendar = get_post( $post->post_parent );
		}
		?><div class="event-options misc-pub-section"><?php
			?><p><?php
				_e( 'Calendar:' , 'calendula' );
				?> <?php
				$edit_url = get_edit_post_link($calendar->ID);
				?><a href="<?php echo $edit_url ?>"><?php echo $calendar->post_title; ?></a><?php
			?></p><?php

		?></div><?php

		?><div class="event-options misc-pub-section"><?php
			?><h4><?php _e( 'Date and Time' ) ?></h4><?php
			?><p><?php
				?><input type="checkbox" id="fullday" name="event[_event_full_day]" value="1" <?php checked( $full_day , 1 , true ) ?>><?php
				?><label for="fullday"><?php
					_e('This is a full day event','calendula');
				?></label><?php
			?></p><?php
			
			?><p><?php
				?><label for="start-date"><?php
					_e('Start Date','calendula');
				?></label><?php
				?><input type="date" id="start-date" name="event[_event_start][date]" value="<?php echo $startdate ?>"><?php
				
				?><span class="select-event-time"><?php
					?><br /><?php
					?><label for="start-date"><?php
						_e('Time','calendula');
					?></label><?php
					?><input type="time" id="start-time" name="event[_event_start][time]" value="<?php echo $starttime ?>"><?php
				?></span><?php
			?></p><?php
			
			?><p><?php
				?><label for="end-date"><?php
					_e('End Date','calendula');
				?></label><?php
				?><input type="date" id="end-date" name="event[_event_end][date]" value="<?php echo $enddate ?>"><?php
				
				?><span class="select-event-time"><?php
					?><br /><?php
					?><label for="end-date"><?php
						_e('Time','calendula');
					?></label><?php
					?><input type="time" id="end-time" name="event[_event_end][time]" value="<?php echo $endtime ?>"><?php
				?></span><?php
			?></p><?php
			
		?></div><?php
		
			/*
		?><div class="event-options misc-pub-section"><?php
			?><p><?php
				//checkbox [  ] repeat
				?><label for="repeat-freq"><?php
				//	$sel_itvl = '<input type="number" class="event-repeat-interval small-input" min="1" id="start-date" name="event[_event_repeat][interval]" value="'. $repeat_interval.'" />';
					$sel_freq = '<select id="repeat-freq" name="event[_event_repeat_freq]">'.
						'<option '.selected($repeat_freq,'',false).' value="">' .__("Don't repeat",'calendula') .'</option>'.
						'<option '.selected($repeat_freq,'DAILY',false).' value="DAILY">' .__("Daily",'calendula') .'</option>'.
						'<option '.selected($repeat_freq,'WEEKLY',false).' value="WEEKLY">' .__("Weekly",'calendula'). '</option>'.
						'<option '.selected($repeat_freq,'MONTHLY',false).' value="MONTHLY">' .__("Monthly",'calendula') .'</option>'.
						'<option '.selected($repeat_freq,'YEARLY',false).' value="YEARLY">' .__("Yearly",'calendula') .'</option>'.
						'</select>';
					
//					printf( __('Repeat %1$s every %2$s','calendula') , $sel_freq , $sel_itvl
					printf( __('Repeat %1$s','calendula') , $sel_freq 
					);

				?></label><?php
			?></p><?php
			?><p><?php
				?><input type="checkbox" value="1" id="repeat-count-enable" <?php checked( (bool)$repeat_count , true , true ) ?> name="event[_event_repeat_count][enable]" /><?php

				
				?><label for="repeat-count-enable"><?php
					_e( 'Repeat' , 'calendula' )
				?></label><?php

				?><input id="repeat-count" type="number" name="event[_event_repeat_count][count]"  class="event-repeat-interval small-input" min="1" value="<?php echo $repeat_count; ?>"  /><?php
				
				?><label for="repeat-count"><?php
					_e( 'Times' , 'calendula' )
				?></label><?php
				
			?></p><?php

			
		?></div><?php
			*/
	}
}

CalendulaAdminUI::init();

endif;