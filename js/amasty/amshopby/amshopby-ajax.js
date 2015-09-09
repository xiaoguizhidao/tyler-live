var amshopby_working  = false;
var amshopby_blocks   = {};

function amshopby_ajax_fallback_mode() {
    var myNav = navigator.userAgent.toLowerCase();
    var isIE = (myNav.indexOf('msie') != -1) ? parseInt(myNav.split('msie')[1]) : false;
    return isIE == 7 || isIE == 8;
}

function amshopby_ajax_init(){
    if (amshopby_ajax_fallback_mode()) {
        return;
    }

    $$('div.block-layered-nav a', amshopby_toolbar_selector + ' a').
        each(function(e){
            var p = e.up();
            if (p.hasClassName('amshopby-cat') || p.hasClassName('amshopby-clearer')){
                return;
            }

            e.onclick = function(){
                if (this.hasClassName('checked')) {
                    this.removeClassName('checked');
                } else {
                    this.addClassName('checked');
                }

                var s = this.href;
                if (s.indexOf('#') > 0){
                    s = s.substring(0, s.indexOf('#'))
                }
                amshopby_ajax_push_state(s);
                amshopby_ajax_request(s);
                return false;
            };
        });

    $$('div.block-layered-nav select.amshopby-ajax-select', amshopby_toolbar_selector + ' select').
        each(function(e){
            e.onchange = 'return false';
            Event.observe(e, 'change', function(e){
                amshopby_ajax_push_state(this.value);
                amshopby_ajax_request(this.value);
                Event.stop(e);
            });
        });

    var isRwd = typeof enquire == 'object' && typeof ProductMediaManager == 'object' && typeof bp == 'object';
    if (isRwd) {
        amshopby_ajax_rwd_fix();
    }
}

function amshopby_ajax_rwd_fix() {
    if ($j('.col-left-first > .block').length && $j('.category-products').length) {
        var query = 'screen and (max-width: ' + bp.medium + 'px)';

        var register = function() {
//                enquire.unregister(query);
                enquire.register(query, {
                    match: function () {
                        setTimeout(function() {
                            $j('.col-left-first').insertBefore($j('#amshopby-page-container'));
                            $j('.col-left-first .toggle-content').toggleSingle();
                        }, 100);
                    },
                    unmatch: function () {
                        // Move layered nav back to left column
                        $j('.col-left-first').insertBefore($j('.col-main'))
                    }
                });
        }

        register();
    }
}

function amshopby_get_created_container()
{
    var elements = document.getElementsByClassName('amshopby-page-container');
    return (elements.length > 0) ? elements[0] : null;
}

function amshopby_get_container()
{
    var createdElement = amshopby_get_created_container();
    if (!createdElement) {
        var container_element = null;

        var elements = $$('div.category-products');
        if (elements.length == 0) {
            container_element = amshopby_get_empty_container();
        } else {
            container_element = elements[0];
        }

        if (!container_element) {
            console.debug('Please add the <div class="amshopby-page-container"> to the list template as per installtion guide. Enable template hints to find the right file if needed.');
        }

        container_element.wrap('div', { 'class': 'amshopby-page-container', 'id' : 'amshopby-page-container' });

        createdElement = amshopby_get_created_container();

        $(createdElement).insert({ bottom : '<div style="display:none" class="amshopby-overlay"><div></div></div>'});
    }
    return createdElement;
}

function amshopby_get_empty_container()
{
    var notes = document.getElementsByClassName('note-msg');
    if (notes.length == 1) {
        return notes[0];
    }
}

function amshopby_ajax_push_state(url) {
    if (typeof window.history.pushState === 'function') {
        window.history.pushState({url: url}, '', url);
    }
}

function amshopby_ajax_request(url){
    /*
     * Clean hash param to avoid scrolling page down
     */
    if (typeof amscroll_object != 'undefined') {
        amscroll_object.setHashParam('page', null);
        amscroll_object.setHashParam('top', null);

        amscroll_params.url = url;
        amscroll_object.setUrl(url);
    }

    var block = amshopby_get_container();

    if (block && amshopby_scroll_to_products) {
        block.scrollTo();
    }

    amshopby_working = true;

    $$('div.amshopby-overlay').each(function(e){
        e.show();
    });

    var request = new Ajax.Request(url,{
            method: 'get',
            parameters:{'is_ajax':1},
            onSuccess: function(response){
                try {
                    var data = response.responseText;
                    if(!data.isJSON()){
                        throw new EventException('Cannot convert response data to JSON');
                    }

                    data = data.evalJSON();
                    if (!data.page || !data.blocks){
                        throw new EventException('Invalid data structure in response');
                    }
                    amshopby_ajax_update(data);
                } catch (e) {
                    console.log(e.message);
                    setLocation(url);
                }
                amshopby_working = false;
                amshopby_skip_hash_change = false;
            },
            onFailure: function(){
                amshopby_working = false;
                setLocation(url);
            }
        }
    );
}

function amshopby_get_first_descendant(element) {

    var targetElement = element.firstChild;
    if(typeof element.firstDescendant != "undefined") {
        targetElement = element.firstDescendant();
    }
    return targetElement;
}

function amshopby_ajax_update(data){

    //update category (we need all category as some filters changes description)
    var tmp = document.createElement('div');
    tmp.innerHTML = data.page;

    var title = data.title;
    if (title) {
        $$('title')[0].update(title);
    }

    var block = amshopby_get_container();
    if (block) {
        var targetElement = amshopby_get_first_descendant(tmp);

        /*
         * If returned element is not HTML tag
         */
        if (targetElement == null) {
            tmp.innerHTML = '<p class="note-msg">' + data.page + '</p>';
            targetElement = amshopby_get_first_descendant(tmp);
        }
        block.parentNode.replaceChild(targetElement, block);
        if (typeof AmConfigurableData != 'undefined') {
            try{
                targetElement.innerHTML.evalScripts();
            }
            catch(ex){
                console.debug(ex);
            }
        }
    }


    var blocks = data.blocks;
    for (var id in blocks){

        var html   = blocks[id];
        if (html){
            tmp.innerHTML = html;
        }

        block = $$('div.'+id)[0];
        if (html){
            if (!block){
                block = amshopby_blocks[id]; // the block WAS in the structure a few requests ago
                amshopby_blocks[id] = null;
            }
            if (block){
                var targetElement = amshopby_get_first_descendant(tmp);
                block.parentNode.replaceChild(targetElement, block);
            }
        }
        else { // no filters returned, need to remove
            if (block){
                var empty = document.createTextNode('');
                amshopby_blocks[id] = empty; // remember the block in the DOM structure
                block.parentNode.replaceChild(empty, block);
            }
        }
    }

    if (typeof amshopby_jquery_init !== 'undefined') {
        amshopby_jquery_init();
    }
    amshopby_start();
    amshopby_ajax_init();
    try {
        amshopby_external();
    } catch (e) {
        console.debug(e);
    }
}

document.observe("dom:loaded", function(event) {
    amshopby_ajax_init();

    if (typeof window.history.replaceState === "function") {
        window.history.replaceState({url: document.URL}, document.title);

        window.onpopstate = function(e){
            if(e.state){
                amshopby_ajax_request(e.state.url);
            }
        };
    }

});

var amshopby_toolbar_selector = 'div.toolbar';
var amshopby_scroll_to_products = false;

function amshopby_external(){
    //add here all external scripts for page reloading
    // like igImgPreviewInit(); 
    if (typeof amscroll_object != 'undefined') {
        amscroll_object.init(amscroll_params);
        amscroll_object.bindClick();
    }

    if (typeof amshopby_demo != 'undefined') {
        amshopby_demo();
    }
    if (typeof AmAjaxObj != 'undefined') {
        AmAjaxShoppCartLoad('button.btn-cart');
    }

    //amfinder
    var amfinderScript = document.getElementById('amfinder_script');
    if (amfinderScript) {
        eval(amfinderScript.innerHTML);
    }

    if (typeof ProductMediaManager != 'undefined') {
        amshopby_external_rwd();
    }

    if (typeof amlabel_init == 'function') {
        amlabel_init();
    }

    /**
     * Third-party themes
     */

    if (typeof jQuery != 'undefined' && typeof calculateMenuItemsInRow == 'function') {
        amshopby_external_megatron();
    }

    //Ultimo fortis themes
    if (typeof setGridItemsEqualHeight != 'undefined') {
        setTimeout('setGridItemsEqualHeight(jQuery)', 100);
        setTimeout('setGridItemsEqualHeight(jQuery)', 300);
        setTimeout('setGridItemsEqualHeight(jQuery)', 800);
        setTimeout('setGridItemsEqualHeight(jQuery)', 2000);
    }

    // venedor/default
    if (typeof products_grid_resize == 'function') {
        products_grid_resize();
    }

    if (typeof jQuery != 'undefined' && typeof jQuery.resize == 'function') {
        jQuery.resize();
    }
}

function amshopby_external_rwd() {
    jQuery('.toggle-content').each(function () {
        var wrapper = jQuery(this);

        var hasTabs = wrapper.hasClass('tabs');
        var startOpen = wrapper.hasClass('open');

        var dl = wrapper.children('dl:first');
        var dts = dl.children('dt');
        var panes = dl.children('dd');
        var groups = new Array(dts, panes);

        //Create a ul for tabs if necessary.
        if (hasTabs) {
            var ul = jQuery('<ul class="toggle-tabs"></ul>');
            dts.each(function () {
                var dt = jQuery(this);
                var li = jQuery('<li></li>');
                li.html(dt.html());
                ul.append(li);
            });
            ul.insertBefore(dl);
            var lis = ul.children();
            groups.push(lis);
        }

        //Add "last" classes.
        var i;
        for (i = 0; i < groups.length; i++) {
            groups[i].filter(':last').addClass('last');
        }

        function toggleClasses(clickedItem, group) {
            var index = group.index(clickedItem);
            var i;
            for (i = 0; i < groups.length; i++) {
                groups[i].removeClass('current');
                groups[i].eq(index).addClass('current');
            }
        }

        //Toggle on tab (dt) click.
        dts.on('click', function (e) {
            //They clicked the current dt to close it. Restore the wrapper to unclicked state.
            if (jQuery(this).hasClass('current') && wrapper.hasClass('accordion-open')) {
                wrapper.removeClass('accordion-open');
            } else {
                //They're clicking something new. Reflect the explicit user interaction.
                wrapper.addClass('accordion-open');
            }
            toggleClasses(jQuery(this), dts);
        });

        //Toggle on tab (li) click.
        if (hasTabs) {
            lis.on('click', function (e) {
                toggleClasses(jQuery(this), lis);
            });
            //Open the first tab.
            lis.eq(0).trigger('click');
        }

        //Open the first accordion if desired.
        if (startOpen) {
            dts.eq(0).trigger('click');
        }
    });
}

function amshopby_external_megatron() {
    var windowWidth = window.innerWidth || document.documentElement.clientWidth;
    var animate = jQuery(".notouch .animate");
    var animateDelay = jQuery(".notouch .animate-delay-outer");
    var animateDelayItem = jQuery(".notouch .animate-delay");
    if (windowWidth > 767) {
        animate.bind("inview", function (event, visible) {
            if (visible && !jQuery(this).hasClass("animated")) jQuery(this).addClass("animated")
        });
        animateDelay.bind("inview", function (event, visible) {
            if (visible && !jQuery(this).hasClass("animated")) {
                var j = -1;
                var $this = jQuery(this).find(".animate-delay");
                $this.each(function () {
                    var $this = jQuery(this);
                    j++;
                    setTimeout(function () {
                        $this.addClass("animated")
                    }, 200 * j)
                });
                jQuery(this).addClass("animated")
            }
        })
    } else {
        animate.each(function () {
            jQuery(this).removeClass("animate")
        });
        animateDelayItem.each(function () {
            jQuery(this).removeClass("animate-delay")
        })
    }
}
