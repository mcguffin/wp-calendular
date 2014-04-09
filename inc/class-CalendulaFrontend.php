<?php
/**
* @package WPCalendula
* @version 0.1
*/
if ( !class_exists('CalendulaFrontend') ):

class CalendulaFrontend {
	static function init() {
		add_filter( 'query_vars', array(__CLASS__,'query_vars') );
		add_filter( 'template_redirect', array(__CLASS__,'template_redirect') );
		// enqueue calendar script
		add_action( 'init', array(__CLASS__,'enqueue_scripts') );
		add_shortcode('calendar',array(__CLASS__,'calendar_shortcode'));
	}
	
	static function calendar_shortcode( $atts , $content = null ) {
		extract( shortcode_atts( array(
			'scope' => 'month', // | week
			'range' => get_query_var('calendar_range'), // | week
			'calendar_id' => '',
			'id' => 'calendar-'.uniqid(),
		), $atts ) );
		
		if ( ! $range )
			$range = strftime( '%Y%m' );
		
		if ( $calendar_id )
			$calendar = new Calendar( $calendar_id );
		else 
			$calendar = new Calendar( );
		
		$result = '<section class="calendar">';
		$result .= self::print_calendar( $calendar , $range ,'html' );
		$result .= '</section>';
		return $result;
	}
	
	static function template_redirect(  ) {
		global $wp_query;
		$post_type = get_query_var('post_type');
		
		//wp_enqueue_style( 'calendular' , plugins_url('/css/calendular.css' , dirname(__FILE__) ) );
		if ( ($post_type  == 'calendar' || $post_type == 'event' ) ) {
			// enqueue calendar script
			
			if ( ! is_post_type_archive('calendar') ) {
				if ( $post_type  == 'calendar' ) {
					$calendar_id = $wp_query->post->ID;
					$calendar_title = get_post( $calendar_id )->post_title;
				} else {
					$calendar_id = $wp_query->post->post_parent;
					$calendar_title = get_post( $calendar_id )->post_title;
				}
				$calendar = new Calendar( $calendar_id);
				
			} else {
				$calendar = new Calendar( );
			}
			$calendar_format = get_query_var('calendar_format');
			$range_str = get_query_var('calendar_range');
			if ( $calendar_format ) {
				self::print_calendar( $calendar , $range_str , $calendar_format , true );
				exit();
			} else {
				add_post_type_support( 'event', 'post-formats' );
				add_filter( 'get_the_terms' , array(__CLASS__,'set_event_post_format') , 10,3 );

				if ( $post_type == 'calendar' &&  ! is_post_type_archive('calendar')  ) {
					$calendar->query_events( $range_str );
				} else if ( $post_type == 'event' && is_single() ) {
					add_filter( "get_next_post_where" , array( __CLASS__ , "get_adjacent_post_where" ) , 10, 3 );
					add_filter( "get_previous_post_where" , array( __CLASS__ , "get_adjacent_post_where" ) , 10, 3 );

					add_filter( "get_next_post_join" , array( __CLASS__ , "get_adjacent_post_join" ) , 10, 3 );
					add_filter( "get_previous_post_join" , array( __CLASS__ , "get_adjacent_post_join" ) , 10, 3 );

					add_filter( "get_next_post_sort" , array( __CLASS__ , "get_adjacent_post_sort" ) , 10, 3 );
					add_filter( "get_previous_post_sort" , array( __CLASS__ , "get_adjacent_post_sort" ) , 10, 3 );
				}
			}
		}
	}


	
	static function enqueue_scripts() {
		if ( ! is_admin() ) {
			wp_enqueue_style( 'calendular' , plugins_url('/css/calendular.css' , dirname(__FILE__) ) );
			wp_enqueue_script( 'jquery-address' , plugins_url('/js/jquery.address-1.5.min.js' , dirname(__FILE__) ) , array('jquery') );
			wp_enqueue_script( 'calendular' , plugins_url('/js/calendular.js' , dirname(__FILE__) ) , array('jquery','jquery-address') );
		}
	}
	static function query_vars( $qvs ){
		$qvs[]='calendar_format';
		$qvs[]='calendar_range';
		return $qvs;
	}
	
	static function print_calendar( &$calendar , $range_str , $format , $echo = false ) {
		if ( ! $range_str )
			$range_str = '000000';
		$start_of_week = get_option('start_of_week');
		switch ( $format ) {
			case 'ics':
				
				$calendar->query_events( $range_str );
				$calendar_title = $calendar->post()->post_title;
				
				$timezone_string = get_option( 'timezone_string' );
				$vcal_conf = array(
					'unique_id' => get_permalink( $calendar->ID() ),
					'TZID'		=> $timezone_string,
				);
				
				$vcalendar = new vcalendar( $vcal_conf );
				$vcalendar->setProperty( "method", "PUBLISH" );
				$vcalendar->setProperty( "X-WR-TIMEZONE", $timezone_string );
				
				iCalUtilityFunctions::createTimezone( $vcalendar , $timezone_string , array( "X-LIC-LOCATION" => $timezone_string ) );
		
				while ( have_posts() ) {
					the_post();
					self::addEventFromPost( $vcalendar , get_post() );
				}
				//*
				header( 'Content-Type: text/calendar' );
				header( 'Content-Disposition: attachment; filename='.sanitize_file_name($calendar_title).'.ics' );
				/*/
				header( 'Content-Type: text/plain' );
				//*/
				$vcalendar->setProperty( "X-WR-CALNAME", $calendar_title );
				$result = $vcalendar->createCalendar();
				break;

			case 'json':
				$range = Calendar::get_calendar_range( $range_str );
				extract( $range , EXTR_PREFIX_ALL , 'range' ); // $range_from,$range_to
				$events = $calendar->get_events( $range , false );
				
				
				header( 'Content-Type: application/json' );
				$result = json_encode($events); 
				break;

			case 'html':
				$range = Calendar::get_calendar_range( $range_str );
				$original_range = $range["from"];
				$range = Calendar::monthsheet_expand_range($range);
				extract( $range , EXTR_PREFIX_ALL , 'range' ); // $range_from,$range_to
				$events = $calendar->get_events( $range );
				if ( ! is_main_site() ) {
					$network_events = Calendar::get_network_events( $range );
					$calendar->merge_events( $events , $network_events );
				}
				
				$days = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday',);
				$months = array('January','February','March','April','May','June','July','August','September','October','November','December');

				$nav_tpl = '<span class="next month">%1$s.</span> <span class="next year">%2$s</span>';
				
				$original_range_time = strtotime($original_range);
				$pta_link = get_post_type_archive_link('calendar');
				$pta_link = str_replace( home_url() , '' , $pta_link );
				$time_prev = strtotime( "-1 $range_scope" , $original_range_time );
				$href_prev = add_query_arg( 'calendar_range' , strftime( "%Y%m" , $time_prev ) );
				$href_prev_html = add_query_arg( 'calendar_range' , strftime( "%Y%m" , $time_prev ) , $pta_link );
				$href_prev_html = add_query_arg( 'calendar_format','html',$href_prev_html );
				$name_prev = sprintf( $nav_tpl, 
					__( strftime('%b',$time_prev) ) , 
					strftime('%Y',$time_prev) 
				);

				$time_next = strtotime( "+1 $range_scope" , $original_range_time );
				$href_next = add_query_arg( 'calendar_range' , strftime( "%Y%m" , $time_next ) );
				$href_next_html = add_query_arg( 'calendar_range' , strftime( "%Y%m" , $time_next ) , $pta_link );
				$href_next_html = add_query_arg( 'calendar_format','html',$href_next_html );
				$name_next = sprintf( $nav_tpl , 
					__( strftime('%b',$time_next ) ) , 
					strftime('%Y',$time_next) 
				);
				
				
				$result = '';
				
				$result .= '<header class="calendar-header">';
				// add calendar nav
				$result .= sprintf('<a class="navigation prev-sheet" data-href-html="%3$s" href="%1$s">%2$s</a>' , $href_prev , $name_prev , $href_prev_html );
				
				$result .= '<h1>';
				// put some prev and next links
				$result .= '<span class="monthname">';
				$result .= $months[ intval($range_scopename[ 'month' ])-1 ];
				$href_today = add_query_arg( 'calendar_range' , '000000' );
				$result .= '</span>';

				$result .= '<span class="year">';
				$result .= $range_scopename[ 'year' ];
				$result .= '</span>';
				
				
				$result .= '</h1>';

				$result .= sprintf('<a class="navigation next-sheet" data-href-html="%3$s" href="%1$s">%2$s</a>', $href_next , $name_next, $href_next_html );

				$result .= '</header>';
				
				// which one is current month?
				$result .= '<table class="calendar-sheet">';
				$result .= '<thead>';
				$result .= '<tr>';
				for ( $i=0 ; $i < 7; $i++ ) {
					$day = ($i+7+$start_of_week)%7;
					
					$result .= '<th class="day">'; 
					$result .= __( $days[$day]  );
					$result .= '</th>';
				}
				$result .= '</tr>';
				$result .= '</thead>';
				$result .= '<tbody>';
				$current_date = $range_from;
				while ( strcmp( $range_to , $current_date ) > 0 ) {
					$current_utime = $utime = strtotime($current_date);
					$week_of_year = intval(strftime( '%W' , $utime ));

					$result .= '<tr>';
					for ( $i=0 ; $i < 7; $i++ ) {
						$day_of_week = intval(strftime( '%w' , $utime ));
						$day_class = array( 'day' );
						$day_container_class = array( 'day-container' );
						if ( strftime('%Y-%m-%d') == strftime('%Y-%m-%d' , $utime ) )
							$day_class[] = 'today';
						if ( intval($range_scopename[ 'month' ]) == strftime('%m' , $utime ) )
							$day_class[] = 'in-monthsheet';

						$result .= '<td class="'. implode(' ',$day_class ) .'">';
						$result .= '<div class="'. implode(' ',$day_container_class ) .'">';
						$result .= '<time datetime="'. strftime( '%Y-%m-%d' , $utime ). '" class="day-number">'. strftime( '%d' , $utime ) . '</time>'; 
						
						$result .= '<div class="calendar-events">';
						if ( isset( $events['events'][$week_of_year] ) && isset( $events['events'][$week_of_year][$day_of_week] ) ) {
							$days_events = $events['events'][$week_of_year][$day_of_week];
							foreach ( $days_events as $event ) {
								$event_class = array('event');
								$event_class[] = 'calendar-'.$event->calendar_slug;
								$event_class[] = 'event-'.$event->post_name;
								if ( $event->full_day ) 
									$event_class[] = 'full-day';

								$result .= '<article class="'. implode(' ',$event_class ) .'">';
								$result .= '<header class="event-header">';
								$result .= '<h1>';
								$result .= '<a class="event-link" href="'.$event->permalink .'">';
								$result .= $event->post_title;
								$result .= '</a>';
								$result .= '</h1>';
								if ( ! $event->full_day ) {
									$result .= '<time datetime="'. $event->start .'" class="event-start">' .  strftime( '%H:%M' , strtotime( $event->start ) ) .'</time>'; 
								}
								$result .= '</header>';
								$result .= '<div class="event-content">';
								$result .= $event->post_content;
								$result .= '</div>';
								
								$result .= '<footer class="event-meta">';
								$date_fmt = get_option('date_format');
								if ( ! $event->full_day ) 
									 $date_fmt .= ' '.get_option('time_format');;

								$result .= '<p><time datetime="'. $event->start.'" class="event-start">'. date( $date_fmt , strtotime( $event->start ) ).'</time> '.__('to','calendular').' <br />'; 
								$result .= '<time datetime="'. $event->end.'" class="event-end">'. date( $date_fmt , strtotime( $event->end ) ).'</time></p>'; 
								$result .= '</footer>';
								$result .= '</article>';
							}
						}
						$result .= '</div>';
						$result .= '</td>';
						$utime += 60*60*24;
					}
					$result .= '</tr>';
						
					$current_date = strftime( "%Y-%m-%d %H:%M:%S" , strtotime( "+1 week" , $current_utime ) );
				}
				$result .= '</tbody>';
				
				
				$result .= '</table>';
				
				
				break;
		}
		
		if ( $echo )
			echo $result;
		else
			return $result;
	}
	
	// move to frontend!
	static function addEventFromPost( &$vcal , $post ) {
		$vevent =& $vcal->newComponent( 'VEVENT' );
		
		
		$vcal_config = array(
			'unique_id' => get_permalink( $post->ID ),
		);


		$vevent->setProperty( 'summary' , $post->post_title );
		if ( ! empty( $post->post_content ) )
			$vevent->setProperty( 'description' , $post->post_content );

		$start_vdate = Calendar::sql_to_vcal_date( get_post_meta( $post->ID , '_event_start', true ) );
		$end_vdate = Calendar::sql_to_vcal_date( get_post_meta( $post->ID , '_event_end', true ) );
		
		if ( get_post_meta( $post->ID , '_event_full_day', true ) ) {
			$vevent->setProperty( 'dtstart' , $start_vdate , array("VALUE" => "DATE") );
			$vevent->setProperty( 'dtend'  , $end_vdate , array("VALUE" => "DATE") );
		} else {
			$vevent->setProperty( 'dtstart' , $start_vdate );
			$vevent->setProperty( 'dtend'  , $end_vdate );
		}
		/*
		// do RRULE
		if ( ! empty( $rrule ) )
			$vevent->setProperty( 'RRULE' , $rrule );
		*/

	}


	

	public static function get_adjacent_post_where( $where , $in_same_cat, $excluded_categories ) {
		global $wpdb;
		$post = get_post();
		$calendar_id = $post->post_parent;
		$compare_date = get_post_meta( $post->ID , '_event_start'  ,true );
		$op = strpos( $where , '<' ) ? '<' : '>';
		$where = $wpdb->prepare("WHERE m.meta_value $op %s AND p.post_type = %s AND p.post_status = 'publish'  AND p.post_parent = %d ", $compare_date , $post->post_type , $calendar_id );
		return $where;
	}
	public static function get_adjacent_post_join( $join , $in_same_cat, $excluded_categories ){
		global $wpdb;
		$join = " INNER JOIN $wpdb->postmeta AS m ON p.ID = m.post_id AND m.meta_key='_event_start' ";
		return $join;
	}
	public static function get_adjacent_post_sort( $sort ){
		$order = strpos( $sort , 'DESC' ) ? 'DESC' : 'ASC';
		$sort = " ORDER BY m.meta_value $order LIMIT 1";
		return $sort;
	}
	
	public static function set_event_post_format( $terms, $post_id, $taxonomy ) {
		if ( $taxonomy == 'post_format' ) {
			$terms[] = (object) array(
				'term_id' => 0,
				'name' => __('Event','calendular'),
				'slug' => 'post-format-event',
				'term_group' => '0',
				'term_taxonomy_id' => '0',
				'taxonomy' => 'post_format',
				'description' => '',
				'parent' => '0',
				'count' => '0',
				'object_id' => $post_id,
			);
		}
		return $terms;	
	}
		
}

CalendulaFrontend::init();

endif;