(function($){ 

	$.fn.calendar = function( ) {
		return this.each(function() {
			$(this).on('click','.prev-sheet, .next-sheet' , null , function(){
				var src = $(this).data('href-html');
				$(this).closest('.calendar').load(src);
				return false;
			});
		});
	}



	$(document).ready(function(){
		$('.calendar').calendar();
	})


})(jQuery);