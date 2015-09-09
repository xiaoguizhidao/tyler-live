/**
* @author Amasty Team
* @copyright Copyright (c) 2010-2011 Amasty (http://www.amasty.com)
* @package Amasty_Pgrid
*/

var amDefConf = new Class.create();

amDefConf.prototype = {
    initialize: function()
    {
        
    },
    
    select: function()
    {
        var args = $A(arguments);
        $$('.product-options .super-attribute-select').each(function(select, i){
            if (args[i])
            {
                select.value = args[i];
                spConfig.configureElement(select);
            }
        });
    }
};