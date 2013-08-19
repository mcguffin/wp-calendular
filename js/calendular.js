(function($){ 

	$.fn.calendar = function( ) {
		
		var $current_calendar;
		
		function nice_cal_url(ugly) {
			var mtch = ugly.match(/(.*)\?calendar_range=([0-9]{6})/);
			var nice = '/'+mtch[1].replace(/^\//,'').replace(/\/$/,'')+'/'+mtch[2];
			return nice;
		}
		function ugly_cal_url(nice) {
			if ( nice.indexOf('?') != -1 )
				return nice;
			nices = nice.split('/');
			if ( nices.length < 3)
				return '';
			var ugly = '/'+nices[1]+'/?calendar_range='+nices[2]+'&calendar_format=html';
			return ugly;
		}
		
		$.address.change(function( event ) {
			var url = $.address.value();
			if ( url == '' || url == '/' )
				return;
			url = ugly_cal_url(url);
			if ( $current_calendar )
				$current_calendar.load(url);
			else
				$('.calendar').load(url);
		});
		return this.each(function() {
			$(this).on('click','.prev-sheet, .next-sheet' , null , function() {
				var url =  nice_cal_url( $(this).data('href-html') );
				$.address.value(url);
				$current_calendar = $(this).closest('.calendar');
				return false;
			});
		});
	}


	$(document).ready(function(){
		$('.calendar').calendar();
	});


})(jQuery);