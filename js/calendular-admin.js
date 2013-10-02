
jQuery(document).ready(function($){
	if ( ! Modernizr.inputtypes.date )
		jQuery('input[type="date"]').datepicker({ dateFormat: "yy-mm-dd" });
	if ( ! Modernizr.inputtypes.time )
		jQuery('input[type="time"]').timepicker();
		
	
	$('#fullday').change(function(){
		$('.select-event-time').css( 'display' , $(this).attr('checked') ? 'none' : 'inline' );
	}).trigger('change');
	
});
