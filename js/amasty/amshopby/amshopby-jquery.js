function amshopby_get_step( decimal ) {
    var step = 1;
    for (var i = 1; i <= decimal; i++) {
        step = step / 10;
    }
    return step;
}

function amshopby_slider_ui_update_values( prefix, values, decimal ) {
    if ($amQuery('#' + prefix + '-from')) {
        $amQuery('#' + prefix + '-from').val(values[0].toFixed(decimal));
        $amQuery('#' + prefix + '-to').val(values[1].toFixed(decimal));
    }

    if ($amQuery('#' + prefix + '-from-slider')) {
        $amQuery('#' + prefix + '-from-slider').html(values[0].toFixed(decimal));
        $amQuery('#' + prefix + '-to-slider').html(values[1].toFixed(decimal));
    }
}

function amshopby_slider_ui_apply_filter( evt, values, decimal ) {
    if (evt && evt.type == 'keypress' && 13 != evt.keyCode)
        return;

    var prefix = 'amshopby-price';

    if (typeof(evt) == 'string'){
        prefix = evt;
    }

    var a = prefix + '-from';
    var b = prefix + '-to';

    var numFrom = parseFloat($amQuery('#' + a).val()).toFixed(decimal);
    var numTo = parseFloat($amQuery('#' + b).val()).toFixed(decimal);

    if ( (numFrom < 0.01 && numTo < 0.01) || numFrom < 0 || numTo < 0 )
        return;

    var url =  $amQuery('#' + prefix + '-url').val().replace(a, values[0]).replace(b, values[1]);

    if (typeof amshopby_working != 'undefined' && !amshopby_ajax_fallback_mode()) {
        amshopby_ajax_push_state(url);
        return amshopby_ajax_request(url);
    } else {
        return setLocation(url);
    }
}

function amshopby_slider_ui_init(from, to, max, prefix, min, decimal) {

    var slider = $amQuery('#' + prefix + '-ui');
    var step = parseInt( decimal ) ? amshopby_get_step( decimal ) : 1;

    from = from ? from : min;
    to = to ? to : max;

    max = (step < 1) ? max + step : max;

    if (slider) {
        slider.slider({
            range: true,
            min: min,
            max: max,
            step: step,
            values: [from, to],
            slide: function (event, ui) {
                amshopby_slider_ui_update_values(prefix, ui.values, decimal);
            },
            change: function (event, ui) {
                if (ui.values[0] != from || ui.values[1] != to) {
                    amshopby_slider_ui_apply_filter(prefix, ui.values, decimal);
                }
            }
        });
    }
}

function amshopby_jquery_init () {
    $amQuery('.amshopby-slider-ui-param').each(function() {
        var params = this.value.split(',');
        amshopby_slider_ui_init( params[0], params[1], parseInt(params[2]), params[3], parseInt(params[4]), params[5] );
    });
}

(function ($) {
    $(function () {
        $('document').ready(function () {
            amshopby_jquery_init();
        });
    });
})($amQuery);