<?php
/**
 * @copyright   Copyright (c) 2010 Amasty (http://www.amasty.com)
 */

/**
 * @method string getCmsBlockId()
 * @method string getCmsBlockBottomId()
 * @method string getDescr()
 * @method int getFilterId()
 * @method string getImgBig()
 * @method string getImgMedium()
 * @method string getImgSmall()
 * @method string getImgSmallHover()
 * @method string getMetaDescr()
 * @method string getMetaKw()
 * @method string getMetaTitle()
 * @method int getOptionId()
 * @method boolean getShowOnList()
 * @method boolean setShowOnList()
 * @method int getSortOrder()
 * @method string getTitle()
 * @method string getUrlAlias()
 * @method setTitle(string $title)
 */
class Amasty_Shopby_Model_Value extends Mage_Core_Model_Abstract
{
    public function _construct()
    {    
        $this->_init('amshopby/value');
    }
}