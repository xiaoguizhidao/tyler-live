<?php
/**
* @author Amasty Team
* @copyright Copyright (c) 2010-2011 Amasty (http://www.amasty.com)
* @package Amasty_Defconf
*/
class Amasty_Defconf_Block_Js extends Mage_Core_Block_Template
{
    protected function _prepareLayout()
    {
        $this->setTemplate('amdefconf/js.phtml');
        return parent::_prepareLayout();
    }
    
    protected function _toHtml()
    {
        $product = Mage::registry('current_product');
        if ($product && $product->isConfigurable())
        {
            return parent::_toHtml();
        }
        return '';
    }
    
    /**
    * will return a string like "3, 7, 1"
    * to be used to call JS function
    */
    public function getAttributeIdsString()
    {
        $product = Mage::registry('current_product');
        $selectedProductId = 0;
        $selected = (string) Mage::getStoreConfig('amdefconf/configurable/preselect');
        if ($selected)
        {
            $selectedIds = unserialize($selected);
            if (isset($selectedIds[$product->getId()]))
            {
                $selectedProductId = $selectedIds[$product->getId()];
            }
        }
        
        if (Mage::app()->getRequest()->getParam('sel'))
        {
            // can load both by ID or SKU
            $possibleSku       = Mage::app()->getRequest()->getParam('sel');
            $selectedProductId = Mage::getModel('catalog/product')->getIdBySku($possibleSku);
            if (!$selectedProductId)
            {
                $selectedProductId = Mage::app()->getRequest()->getParam('sel');
            }
        }
        
        if ($selectedProductId)
        {
            $selectedString    = '';
            $configurableBlock = Mage::app()->getLayout()->createBlock('catalog/product_view_type_configurable', 'configurable.block');
            
            foreach ($configurableBlock->getAllowProducts() as $product) {
                $productId  = $product->getId();
                if ($productId == $selectedProductId)
                {
                    foreach ($configurableBlock->getAllowAttributes() as $attribute) {
                        $productAttribute   = $attribute->getProductAttribute();
                        $productAttributeId = $productAttribute->getId();
                        $attributeValue     = $product->getData($productAttribute->getAttributeCode());
                        $selectedString .= $attributeValue . ', ';
                    }
                }
            }
            if ($selectedString)
            {
                $selectedString = substr($selectedString, 0, -2);
            }
            return $selectedString;
        }
        
        return '';
    }
}