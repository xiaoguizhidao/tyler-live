jQuery(function($){
// Use jQuery with $(...)
    $('#nav-drop-down .shop-categories-link').click(function(e) {
        e.preventDefault();
    });

    //Jump To - Select Field that jumps to a URL when an option is selected
    $('select.jump_to').change(function() {
        url = $(this).val();
        location.href = url;
    });
});