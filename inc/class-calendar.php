<?php
/**
* @package WPCalendula
* @version 0.1
*/

interface ICalendar {

}

class Calendar {
	protected $vcal_config;
	protected $post_ID;
	protected $post;
	protected $calendars;
	
	function __construct( $post_ID = 0 ) {
		$this->post_ID = $post_ID;
	}
	function ID(){
		return $this->post_ID;
	}
	function post(){
		if ( ! isset($this->post) && $this->post_ID )
			$this->post = get_post($this->post_ID);
		return $this->post;
	}
	function scheduleEventPosts(  ) {
		$local_query = new WP_Query();
		$local_query->query( array(
				'posts_per_page' => -1,
				'post_parent' => $this->post_ID,
				'post_type' => 'event',
				'post_status' => 'any',
			));
		return $local_query;
	}

	// --------------------------------------
	//	Getting events ...?
	// --------------------------------------
	static function get_network_events( $range , $use_post = true ) {
		// switch to main blog
		switch_to_blog( BLOG_ID_CURRENT_SITE );
		// get network calendar IDs
		$calendars = get_posts( array(
			'posts_per_page' => -1,
			'post_type' => 'calendar',
			'meta_key' => '_calendar_is_networkwide',
			'meta_value' => 1,
			'suppress_filters' => false,
		) );
		$cal_ids = array();
		foreach ( $calendars as $cal )
			$cal_ids[] = $cal->ID;
		$cal = new Calendar( );
		$ret = $cal->get_events( $range , $use_post , $cal_ids  );
		
		// switch back
		restore_current_blog();
		return $ret;
	}

	function get_events( $range , $use_post = true , $calandars = null ) {
		if ( ! is_array( $range ) )
			$range = self::get_calendar_range( $range );
		extract( $range , EXTR_PREFIX_ALL , 'range' ); // $range_from,$range_to
		
		// new query
		$e_query = $this->query_events( $range , true , $calandars );
		
		$events = array(
			'scope' => $range_scope,
			'events' => array(),
		);
		
		$cbid = get_current_blog_id();
		$this->calendars = array();
		$this->calendars[ $cbid ] = array();
		
		while ( $e_query->have_posts() ) {
			$e_query->next_post();
			$post = $e_query->post;
//			$event->calendar_color = ?????;
			$event = $this->get_event( $post , $use_post );
			
			// restrict events to range
			$event->start = max( $range_from , $event->start );
			$event->end   = min( $range_to , $event->end );
			if ( $range_scope == 'month' ) {
				$this->merge_event( $events , $event );
			} else {
				$events['events'][] = $event;
			}
		}
		$events['start'] = $range_from;
		$events['end'] = $range_to;
		return $events;
	}
	
	function get_event( $post , $use_post = true ) {
		$cbid = get_current_blog_id();
		if ( ! isset( $this->calendars[ $cbid ][ $post->post_parent ] ) )
			$current_calendar = $this->calendars[ $cbid ][ $post->post_parent ] = get_post($post->post_parent);
		else 
			$current_calendar = $this->calendars[ $cbid ][ $post->post_parent ];
		
		$event_start = get_post_meta($post->ID,'_event_start',true);
		$event_end = get_post_meta($post->ID,'_event_end',true);
		
		
		if ( $use_post ) {
			$event = $post;
		} else {
			$event = (object) array(
				'ID'			=> $post->ID,
				'post_title'	=> $post->post_title,
				'post_content'	=> $post->post_content,
			);
		}
		$event->start		= $event_start;
		$event->end 		= $event_end;
		$event->full_day 	= (bool) get_post_meta($post->ID,'_event_full_day',true);
		$event->permalink	= get_permalink($event->ID);
		
		// calendar
		$event->calendar_slug		= $current_calendar->post_name;
		$event->calendar_blog_id	= $cbid;
		$event->calendar_permalink	= get_permalink( $current_calendar->ID );

		return $event;
	}
	
	function merge_event( &$events , $event ) {
		$event_start_utime = strtotime($event->start);
		$event_end_utime = strtotime($event->end);
		if ( $event->full_day )
			$event_end_utime-=1;
		$count_days = floor(($event_end_utime-$event_start_utime) / (3600*24) );
		
		for ( $i=0;$i<=$count_days;$i++) {
			$week_of_year = intval(strftime( '%W' , $event_start_utime ));
			$day_of_week = intval(strftime( '%w' , $event_start_utime ));

			if ( ! isset( $events['events'][$week_of_year] ) )
				$events['events'][$week_of_year] = array();
			if ( ! isset( $events['events'][$week_of_year][$day_of_week] ) )
				$events['events'][$week_of_year][$day_of_week] = array();
		
			$events['events'][$week_of_year][$day_of_week][] = $event;
			$event_start_utime += 3600*24;
			$event_start_utime = min($event_start_utime,$event_end_utime);
		}
	}
	function merge_events( &$events , $other_events ) {
		foreach ( $other_events['events'] as $i => $item ) {
			if ( is_object($item) ) { // other events flat
				$this->merge_event( $events , $item );
			} else if ( is_array($item) ) {
				// $i ist , 
				$week_of_year = $i;
				if ( ! isset( $events['events'][$week_of_year] ) )
					$events['events'][$week_of_year] = array();
				foreach ( $item as $day_of_week => $events_of_day ) {
					if ( ! isset( $events['events'][$week_of_year][$day_of_week] ) )
						$events['events'][$week_of_year][$day_of_week] = array();
					$events['events'][$week_of_year][$day_of_week] = array_merge( $events_of_day , $events['events'][$week_of_year][$day_of_week] );
				}
			}
		}
	}
	function query_events( $range , $create_new_query = false , $calandars = null ) {
		global $wp_query;
		
		if ( $create_new_query )
			$e_query = new WP_Query();
		else 
			$e_query =& $wp_query;
		
		// convert range string to range assoc
		if ( ! is_array( $range ) )
			$range = Calendar::get_calendar_range( $range );

		$query_args = $this->get_query_args( $range , $calandars );

		$e_query->query( $query_args );
		return $e_query;
	}
	
	function monthsheet_expand_range( $range , $return_array = true ) {
		if ( ! is_array( $range ) )
			$range = Calendar::get_calendar_range( $range );
		extract( $range , EXTR_PREFIX_ALL , 'range' ); // $range_from,$range_to

		$range_from_time = strtotime($range_from);
		$range_to_time = strtotime($range_to);
		
		$start_of_week = get_option('start_of_week');
	
		while( $start_of_week != intval(strftime('%w',$range_from_time)) )
			$range_from_time = strtotime( "-1 day" , $range_from_time );
	
		while( $start_of_week != intval(strftime('%w',$range_to_time)) )
			$range_to_time = strtotime( "+1 day" , $range_to_time );

		$range_from = strftime('%Y-%m-%d %H:%M:%S' , $range_from_time);
		$range_to   = strftime('%Y-%m-%d %H:%M:%S' , $range_to_time - 1);
		
		if ( $return_array )
			return array(
				'from' => $range_from,
				'to' => $range_to,
				'scope' => 'month',
				'scopename' => $range_scopename,
			);
		else 
			return strftime('%Y%m%d',$range_from_time) . '|' .strftime('%Y%m%d',$range_to_time);
	}
	
	
	
	private function get_query_args( $range_array , $calendars = null ) {
		extract( $range_array , EXTR_PREFIX_ALL , 'range' ); // $range_from,$range_to

		$query_args = array(
			'posts_per_page' => -1,
			'post_type' => 'event',
			'post_status' => 'any', // shoiuldnt that be 'publish'....?
			/*
			'meta_key' => '_event_start', 
			'order'	=> 'ASC',
			'orderby' => 'meta_value',
			//*/
		);
		if ( ! is_null( $calendars ) )
			$query_args['post_parent__in'] = $calendars;
		else if ( $this->post_ID )
			$query_args['post_parent'] = $this->post_ID;
		
		
		$range_to   = strftime('%Y-%m-%d %H:%M:%S' , strtotime( $range_to ));
		$query_args['meta_query'] = array(
			'relation' => 'OR',
			array(
				'key' => '_event_start',
				'value' => array( $range_from , $range_to ),
				'compare' => 'BETWEEN',
				'type' => 'DATETIME',
			),
			//*
			array(
				'key' => '_event_end',
				'value' => array( $range_from , $range_to ),
				'compare' => 'BETWEEN',
				'type' => 'DATETIME',
			),
			//*/
		);
		return $query_args;
	}
	
	
	
	
	
	// --------------------------------------
	//	Date format conversion
	// --------------------------------------
	static function vcal_to_sql_date( $vcal_date ) {
		extract($vcal_date);
		$sql_date = sprintf('%d-%s-%s' ,
			$year,
			str_pad( $month , 2 , '0' , STR_PAD_LEFT ),
			str_pad( $day , 2 , '0' , STR_PAD_LEFT )
		);
		if ( isset( $hour , $min , $sec ) )
			$sql_date .= sprintf(' %s:%s:%s' ,
				str_pad( $hour , 2 , '0' , STR_PAD_LEFT ),
				str_pad( $min , 2 , '0' , STR_PAD_LEFT ),
				str_pad( $sec , 2 , '0' , STR_PAD_LEFT )
			);
		return $sql_date;
	}
	
	static function sql_to_vcal_date( $sql_date ) {
		$unixtime = strtotime( $sql_date );
		$vcal_date = array(
			'year' => date('Y',$unixtime),
			'month' => date('m',$unixtime),
			'day' => date('d',$unixtime),
		);
		if ( preg_match( '/\d\d:\d\d:\d\d$/' , $sql_date ) ) {
			$vcal_date['hour'] = date('H',$unixtime);
			$vcal_date['min'] = date('i',$unixtime);
			$vcal_date['sec'] = date('s',$unixtime);
		}
		return $vcal_date;
	}
	
	// --------------------------------------
	//	Parsing range strings
	// --------------------------------------
	static function get_calendar_range( $range_string = null ) {
		// range format: 
		// 		000000DD -> day DD in this month
		// 		0000MM -> month MM in this year
		// 		0000MMDD -> day DD in month MM of this year
		// 		YYYYMM -> month MM in year YYYY
		// 		YYYY -> year YYYY
		// 		0000 -> this year
		// 		000000 -> this month
		// 		00000000 -> this day
		// 		YYYYMMDD -> day DD in month MM of year YYYY
		//		YYYYMMDD|YYYYMMDD	-> from date to date
		//		[empty] This Month

		if ( is_null( $range_string ) ) {
			// first of this month ...
			$from = strftime( '%Y-%m-01 00:00:00' );
			$to_scope = 'month';
		} else {
			$ranges = explode( '|' , $range_string );
			list( $from , $from_scope ) = self::get_date_scope_from_range( $ranges[0] ); // fmt: YYYY-MM-DD HH:MM:SS
			
			if ( count($ranges) > 1 ) {
				list( $to , $to_scope ) = self::get_date_scope_from_range( $ranges[1] ); // fmt: YYYY-MM-DD HH:MM:SS
			} else {
				$to_scope = $from_scope;
			}
		}
		if ( ! isset( $to ) )
			$to = strftime( "%Y-%m-%d 00:00:00" , strtotime( "+1 $to_scope" , strtotime($from) ) );
		
		
		$scope = $to_scope;
		$scope_name = array(  );
		
		switch ( $scope ) {
			case 'day':
				$scopename['day'] = strftime('%d', strtotime($from));
			case 'month':
				$scopename['month'] = strftime('%m', strtotime($from));
			case 'year':
				$scopename['year'] = strftime('%Y', strtotime($from));
		}

		return array( 'from' => $from , 'to' => $to , 'scope' => $scope , 'scopename' => $scopename ); // use >= $from AND < $to in MySQL
	}
	
	// --------------------------------------
	//	Parsing range strings
	// --------------------------------------
	static function get_date_scope_from_range( $str ) {
		$year = substr( $str , 0 , 4 );
		$month = false;
		$day = false;
		$scope = 'year';
		switch ( strlen($str) ) {
			case 8:
				$day = substr( $str , 6 , 2 );
				$scope = 'day';
			case 6:
				$month = substr( $str , 4 , 2 );
				$scope = 'month';
		}
		if ( intval( $year ) === 0 ) {
			$year = strftime( '%Y' );
			$scope = 'year';
		}
		if ( $month === false ) {
			$month = '01';
		} else if ( intval( $month ) === 0 ) {
			$month = strftime( '%m' );
			$scope = 'month';
		} else {
			$scope = 'month';
		}
		if ( $day === false ) {
			$day = '01';
		} else if ( intval( $day ) === 0 ) {
			$day = strftime( '%d' );
			$scope = 'day';
		} else {
			$scope = 'day';
		}
		
		
		return array( "$year-$month-$day 00:00:00" , $scope  );
	}
	
}

class LocalCalendar extends Calendar implements ICalendar {
	function __construct( ) {
		parent::__construct();
	}
	function init( $post , $calendar_data ) {
	}
	
}




class RemoteCalendar extends Calendar implements ICalendar {
	
	function __construct( ) {
		parent::__construct();
	}
	
	function init( $post , $calendar_data ) {
		// get transient or download 
		$remote_url = $calendar_data['_calendar_remote_url'];
		$remote_url = str_replace( 'https://','http://',$remote_url );
		$transient_key = "calendar_{$post->ID}";
		$transient_callback = $calendar_data['_calendar_is_networkwide'] ? 'site_transient' : 'transient';
		
//		call_user_func( "delete_{$transient_callback}" , $transient_key );
		
		//call_user_func( "delete_{$transient_callback}" , $transient_key ) ;
		if ( $calendar_data['sync'] || false === ($remote_data = call_user_func( "get_{$transient_callback}" , $transient_key ) ) ) {
			// set $remote_data
			
			$vcal_config = array(
				'unique_id' => $remote_url,
				'url'		=> $remote_url,
			);
			
			$remote_data = new vcalendar( $vcal_config );
			$remote_data->parse();
			$remote_data->sort();
			$this->sync( $remote_data , $post );
			
			$schedules = wp_get_schedules();
			$sync_interval = strtolower($calendar_data['_calendar_remote_sync_interval']);

			$expires = $schedules[$sync_interval]['interval']; // refresh dayly
			call_user_func( "set_{$transient_callback}" , $transient_key , $remote_data , $expires );
		}

		if ( $sync_interval = strtolower($calendar_data['_calendar_remote_sync_interval'] ) ) {
			$cron_task_hook = "calendar_cron_{$sync_interval}";
			
			if ( ! wp_next_scheduled( $cron_task_hook ) )
				$res = wp_schedule_event( time(), $sync_interval , $cron_task_hook );
		}
		
		// delete everything afterwards.
		
	}
	
	function delete_calendar_post( $post_ID ) {
		// remove transient
		$transient_key = "calendar_{$post->ID}";
		$transient_callback = get_post_meta($post_ID,'_calendar_is_networkwide' , true ) ? 'site_transient' : 'transient';
		call_user_func( "delete_{$transient_callback}" , $transient_key );
		
		// delete child posts
		$this->delete_events( $post_ID );
	}
	
	public static function synchronize_calendar( $calendar_ID ) {
		$remote_url = get_post_meta( $calendar_ID , '_calendar_remote_url' , 'true' );
		$remote_url = str_replace( 'https://','http://',$remote_url );
		$transient_key = "calendar_{$calendar_ID}";
		$transient_callback = get_post_meta( $calendar_ID , '_calendar_is_networkwide' , 'true' ) ? 'site_transient' : 'transient';		
		
		$calendar = get_post( $calendar_ID );
		$rm_cl = new RemoteCalendar();
		$vcal_config = array(
			'unique_id' => $remote_url,
			'url'		=> $remote_url,
		);
		
		$remote_data = new vcalendar( $vcal_config );
		$remote_data->parse();
		$remote_data->sort();
		$rm_cl->sync( $remote_data , $calendar );
		$expires = 60*60*24; // refresh dayly
		call_user_func( "set_{$transient_callback}" , $transient_key , $remote_data , $expires );
	}
	
	private function sync( $vcalendar , $parent_post ) {
		// starting sync
		$this->delete_events( $parent_post->ID );
		while ( $event = $vcalendar->getComponent( 'vevent' ) ) {
			$uid = $event->getProperty( 'uid' );
			$name = $event->getProperty( 'name' , 1 );
			$desc = $event->getProperty( 'summary' , 1 );
			$start = $event->getProperty( 'dtstart' );
			$end = $event->getProperty( 'dtend' );
			$repeat = $event->getProperty( 'RRULE' );
			/*
			RRULE:FREQ=YEARLY;INTERVAL=1
			RRULE:FREQ=YEARLY;INTERVAL=1;COUNT=50
			RRULE:FREQ=YEARLY;INTERVAL=1;UNTIL=20500210
			*/
			
			$post_desc = false != $name ? $desc : '';
			$post_name = false != $name ? $name : $desc;
			// ... dtstart, 
			
			$post_data = array(
				'comment_status'	=> 'closed',
				'ping_status'		=> 'closed',
				'post_author'		=> 0,
				'post_content'		=> $post_desc,
				'post_title'		=> $post_name,
				'post_name'			=> sanitize_title_with_dashes( $name ),
				'post_parent'		=> $parent_post->ID,
				'post_status'		=> 'publish',
				'post_type'			=> 'event',
			);
			if ( $new_post_ID = wp_insert_post( $post_data , false ) ) {
				$sql_start = Calendar::vcal_to_sql_date( $start );
				
				$sql_end = Calendar::vcal_to_sql_date( $end );
				update_post_meta( $new_post_ID , '_event_start' , $sql_start );
				update_post_meta( $new_post_ID , '_event_end' , $sql_end );
				update_post_meta( $new_post_ID , '_event_full_day' , !isset( $start['hour'],$start['min'],$start['sec'] ) );
				if ( $repeat ) {
					if ( $repeat['INTERVAL'] != 1 && isset( $repeat['BYMONTH'] ) ) {
						update_post_meta( $new_post_ID , '_event_repeat_bymonth' , $repeat['BYMONTH'] );
					} else {
						$repeat['INTERVAL'] = 1; // not implemented other rules than "every XXX Months/years/days"
					}
					update_post_meta( $new_post_ID , '_event_repeat_freq' , $repeat['FREQ'] );
					update_post_meta( $new_post_ID , '_event_repeat_interval' , $repeat['INTERVAL'] );
					if ( isset( $repeat['COUNT'] ) ) {
						update_post_meta( $new_post_ID , '_event_repeat_count' , $repeat['COUNT'] );
					} else if ( isset( $repeat['until'] ) ) {
						update_post_meta( $new_post_ID , '_event_repeat_until' , $repeat['UNTIL'] );
					}
				}
				$event->setConfig( $vcalendar->getConfig() , false , true );
				update_post_meta( $new_post_ID , '_event_vcaldata' , $event->createComponent($vcalendar->xcaldecl) );
			}
		}
		
		update_post_meta( $parent_post->ID , '_calendar_remote_last_sync' , strftime( '%Y-%m-%d %H:%M:%S' , time() ) );
	}
	private function delete_events( $parent_post_ID ) {
		$del_posts = get_posts( 'posts_per_page=-1&post_type=event&post_status=any&post_parent='.$parent_post_ID );
		foreach ( $del_posts as $post )
			wp_delete_post( $post->ID );
	}
	
}

?>