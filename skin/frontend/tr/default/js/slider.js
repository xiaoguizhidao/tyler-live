jQuery(function($){ // Use jQuery with $(...)
	//*Slider--------*/	
	//Insert Controls	
	$(".feature_slider").prepend('<a href="#" class="slide_control prev_control"><span>Previous</span></a><a href="#" class="slide_control next_control"><span>Next</span></a><div class="feature_slider_pager"></div>');	
	
	//Setup Cycle
	$(".feature_slider ul").cycle( { 
		timeout : 6000,
		speed : 1000,
		sync : true,
		fit : true,
		pause : true,
		delay : 0,
		next: $(this).find('.next_control'),
		prev: $(this).find('.prev_control'),
		slideResize: false,
		pager: $(this).find('.feature_slider_pager'),
		pagerAnchorBuilder: pagerFactory
	});

    function pagerFactory(idx, slide) {
        return '<a href="#"><span></span></a>';
    };

});