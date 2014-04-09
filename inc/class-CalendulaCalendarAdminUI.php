<?php
/**
* @package WPCalendula
* @version 0.1
*/
if ( !class_exists('CalendulaCalendarAdminUI') ):

class CalendulaCalendarAdminUI {
	private static $_current_calendar = 0;
	
	public static function init() {
		if ( is_admin() ) {
			// add meta boxes...
			add_action( 'save_post' , array(__CLASS__,'update_calendar_meta') , 10 , 2 );
			add_action( 'load-post.php' , array( __CLASS__ , 'change_redirect_after_delete' ) );

			add_filter( 'manage_calendar_posts_custom_column' , array( __CLASS__ , 'print_custom_column' ),10,2);
			add_filter( 'manage_calendar_posts_columns' , array( __CLASS__ , 'add_custom_columns' ));
		//	add_action( '' );
		}
	}
	
	static function add_custom_columns( $columns ) {
		$columns['calendar_type'] = __('Calendar Type','calendular');
		return $columns;
	}
	static function print_custom_column( $column , $post_ID ) {
		switch ( $column ) {
			case 'calendar_type':
				$cal_type = get_post_meta( $post_ID , '_calendar_type' , true );
				if ($cal_type == 'local' )
					_e('Local calendar','calendular');
				else
					printf( '<a href="%s">%s</a>' , get_post_meta( $post_ID , '_calendar_remote_url' , true ) , __('Calendar subscription','calendular') );
		}
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
				'_calendar_remote_sync_interval'	=> 0,
			) );
			
			// detect changes in 
			
			foreach ( $calendar as $key => $value )
				update_post_meta( $post_ID , $key , $value );
				
			if ( 'remote' === $calendar['_calendar_type'] ) {
				$cal = new RemoteCalendar();
				$cal->init( $post , $calendar );
			}
		}
	}
	
	
	public static function calendar_meta_boxes() {
		// type remote(url) / type local
		// on main blog: [|] networkwide

		add_meta_box( 'calendar_options' , __( 'Calendar','calendular' ) , array(__CLASS__, 'calendar_meta_box') , 'calendar' , 'side' , 'default' );
		add_meta_box( 'calendar_events' , __( 'Events','calendular' ) , array(__CLASS__, 'events_meta_box') , 'calendar' , 'normal' , 'default' );
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
			<label for="calendar-type"><?php _e( 'Calendar Type' , 'calendular' ) ?></label>
			<select name="calendar[_calendar_type]" id="calendar-type-select">
				<option value="local" <?php selected($type,'local',true) ?>><?php  _e('Local calendar','calendular') ?></option>
				<option value="remote"<?php selected($type,'remote',true) ?>><?php _e('Calendar subscription','calendular') ?></option>
			</select>
			</p>
				<p class="description"><?php _e( 'In a calendar subscription you specify an URL from where to load the events. In a local calendar you create events right on your blog' , 'calendular' ); ?></p>
			</div>
			<div class="calendar-type-settings remote" id="calendar-settings-remote">
				<div class="misc-pub-section" id="calendar-settings-remote">
					<h4><?php _e('Remote Settings','calendular') ?></h4>
					<p>
						<label for="calendar-remote-url"><?php _e( 'Remote URL:' , 'calendular' ) ?></label>
						<input type="text" name="calendar[_calendar_remote_url]" id="calendar-remote-url" value="<?php echo $remote_url ?>" />
					</p>
					<p class="description"><?php _e( 'Enter the URL of the calendar. The Application will only accept data in vCalendar format.' , 'calendular' ); ?></p>
				
				
				</div>
				<div class="misc-pub-section">
					<h4><?php _e('Syncing','calendular') ?></h4>
					<p>
						<label for="calendar-remote-sync-interval"><?php _e( 'Sync Calendar:' , 'calendular' ) ?></label>
						<select name="calendar[_calendar_remote_sync_interval]" id="calendar-remote-sync-interval">
							<option value="0" <?php selected($sync_interval,0,true) ?>><?php _e("Don't sync",'calendular'); ?></option>
							<option value="DAILY" <?php selected($sync_interval,'DAILY',true) ?>><?php _e("Daily",'calendular'); ?></option>
							<option value="WEEKLY" <?php selected($sync_interval,'WEEKLY',true) ?>><?php _e("Once Weekly",'calendular'); ?></option>
							<option value="MONTHLY" <?php selected($sync_interval,'MONTHLY',true) ?>><?php _e( 'Every 30 Days','calendular' ); ?></option>
							<option value="YEARLY" <?php selected($sync_interval,'YEARLY',true) ?>><?php _e( 'Once a Year', 'calendular' ); ?></option>
						</select>
						<button type="submit" name="calendar[sync]" value="1" class="button secondary"><?php _e( 'Sync now!' , 'calendular' ) ?></button>
					</p><?php
					?><p><?php 
						if ( $last_sync ) {
						printf( __( 'Last Sync: %1$s, %2$s' , 'calendular' ) , 
							date_i18n( get_option( 'date_format' ) , strtotime($last_sync) ,false) , 
							date_i18n( get_option( 'time_format' ) , strtotime($last_sync) ,false) 
						); 
					} else {
						_e( 'Never synced' , 'calendular' );
					}
					?></p><?php
				
				?></div>
			</div>
			<?php if ($post->post_status == 'publish' ) : ?>
				<div class="calendar-type-settings misc-pub-section local" id="calendar-settings-local"><?php
				_e( 'See this calandar in ' , 'calendular' );
					?><a href="<?php echo get_permalink(); ?>"><?php _e( 'HTML' , 'calendular' ) ?></a><?php
					?> | <?php
					?><a href="<?php echo add_query_arg('calendar_format','ics',get_permalink()); ?>"><?php _e( 'iCal' , 'calendular' ) ?></a><?php
				// no local settings yet
				?></div><?php
			endif;
			
			?><?php  
			if ( is_multisite() && is_main_site() && is_calendula_active_for_network() ) {
			?>
			<div class="misc-pub-section" id="calendar-settings">
				<h4><?php _e('Network','calendular') ?></h4>
				<p>
					<input type="checkbox" name="calendar[_calendar_is_networkwide]" value="1" id="calendar-networkwide" <?php checked( $is_networkwide , 1 , true ); ?> />
					<label for="calendar-networkwide"><?php _e( 'This is a Network wide calendar.' , 'calendular' ) ?></label>
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
					?><a href="<?php echo $create_url ?>" class="button secondary"><?php _e('Create new Event','calendular') ?></a><?php
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
	
	
	
	

}

CalendulaCalendarAdminUI::init();

endif;