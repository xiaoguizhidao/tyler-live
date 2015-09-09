if(typeof GiftRegistry=='undefined') {
    var GiftRegistry = {};
}

GiftRegistry.AjaxModel = Class.create();
GiftRegistry.AjaxModel.prototype = {
    initialize: function(form_id){
        this.form       = false;
        if ($(form_id)) {
            this.form = new VarienForm(form_id);
        }
    }
}


GiftRegistry.AjaxModel.callAjax = function(button, url, form_id, call_params ) {
    this.form = false;
    var params = '';
	

    if (form_id && $(form_id)) {
        this.form = new VarienForm(form_id);

        if ( this.form && !this.form.validator.validate()){
            return;
        }
        if (this.form) {
            if(params != '') params += '&';
            params += Form.serialize(this.form.form);
        }
    }

    if (call_params) {
        if(params != '') params += '&';
        params += call_params;
    }
    
    if(params != '') params += '&';
    params += 'isAjax=1';
    
    //Call Ajax
    var request = new Ajax.Request(
        url,
        {
            method:'post',
            parameters:params,
            onSuccess: this.onSuccess.bind(this),
            onFailure: this.ajaxFailure.bind(this)
        }
    );
    
    //hide all modes if the loaging is on.
    this.hideModal();
    
    this.setLoadWaiting();

    
    if (button && button != 'undefined') {
        button.disabled = true;
        
        this.button = button;
    }
    
}.bind(GiftRegistry.AjaxModel);

GiftRegistry.AjaxModel.onSuccess = function(transport){
    
    this.resetLoadWaiting();
    
    if (transport && transport.responseText) {
        try{
            response = eval('(' + transport.responseText + ')');
        }
        catch (e) {
            response = {};
        }
        
        if (response.redirect) {
            location.href = response.redirect;
            return;
        }
        if (response.success) {
            this.isSuccess  = true;
        }
        
        if (response.update_section && $('tlgr-'+response.update_section.name+'-ajaxLoading')) {
            if(response.update_section.html) 
                $('tlgr-'+response.update_section.name+'-ajaxLoading').update(response.update_section.html);
                
            if(response.update_section.messages){
                $('tlgr-'+response.update_section.name+'-messages').update(response.update_section.messages);
            }    
            $('tlgr_overlay').show();
            $('tlgr-'+response.update_section.name+'-ajaxLoading').show();
            return;
        }
        
        if (response.update_sidebar && response.update_sidebar.html) {
            if($(response.update_sidebar.id)){
                $(response.update_sidebar.id).replace(response.update_sidebar.html);
            }else{
                $$('.block.block-cart')[0].insert({after: response.update_sidebar.html});
            }
        }
        
        if(response.notice_messages && $('tlgr-modal-notice-messages') && $('tlgr-modal-notice')){
            $('tlgr-modal-notice-messages').update(response.notice_messages);
            $('tlgr_overlay').show();
            $('tlgr-modal-notice').show();
            return;
        }

    }
};

GiftRegistry.AjaxModel.resetLoadWaiting = function(){
    $('tlgr_loader').hide();
    $('tlgr_overlay').hide();
    if(this.button && this.button != 'undefined'){
        this.button.disabled = false;
    }
};

GiftRegistry.AjaxModel.setLoadWaiting = function(){
    $('tlgr_loader').show();
    $('tlgr_overlay').show();
    
};

GiftRegistry.AjaxModel.ajaxFailure = function(){
    this.resetLoadWaiting();
    //TODO - show error
};

GiftRegistry.AjaxModel.hideModal = function(modal_id){
    if (modal_id && modal_id != 'undefined' && $(modal_id)) {
        //if we know the modal_id hide it
        $(modal_id).hide();
        $("tlgr_overlay").hide();
    }else{
        //else hide all models
        if ($$('.tlgr-modal')) {
            $$('.tlgr-modal').invoke('hide');
            $("tlgr_overlay").hide();
        }
    }
};

GiftRegistry.AjaxModel.showModal = function(modal_id){
    if (modal_id && modal_id != 'undefined' && $(modal_id)) {
        $(modal_id).show();
        $("tlgr_overlay").show();
        
    }
};

GiftRegistry.AjaxModel.submitUpdateDescriptionForm = function(button, form_id, mode, url, view_url){
    var params = mode+'=1';
    
    if (form_id && $(form_id)) {
        this.form = new VarienForm(form_id);

        if ( this.form && !this.form.validator.validate()){
            return;
        }
        if (this.form) {
            params += '&'+Form.serialize(this.form.form);
        }
    }
    
    this.callAjax(button, url, 'product_addtocart_form', params);
    
    return false;
}.bind(GiftRegistry.AjaxModel);

GiftRegistry.AjaxModel.showShareModal = function(gift_registry_name, gift_registry_id){
    if($('tlgr-share-popup-ajaxLoading')){
        $('tlgr-share-popup:title').update('"'+gift_registry_name+'"');
        $('tlgr-share-popup:gr_id').value = gift_registry_id;
        
        $('tlgr-share-popup-messages').update('');
        
        this.showModal('tlgr-share-popup-ajaxLoading');
    }
    return false;
}.bind(GiftRegistry.AjaxModel);