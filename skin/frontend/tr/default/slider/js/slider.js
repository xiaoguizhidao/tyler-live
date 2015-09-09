jQuery(function($){ // Use jQuery with $(...)
	//*Slider--------*/	

	//Controls Hover
	$(".slider").hover(function() {
    	$(this).children('.slide_control').stop().animate({opacity: 1});
  	},
  		function() {
    	$(this).children('.slide_control').stop().animate({opacity: 0});
  	});

});