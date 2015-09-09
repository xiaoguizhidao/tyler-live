<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Sales
 * @copyright   Copyright (c) 2011 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Sales Order Email items default renderer
 *
 * @category   Mage
 * @package    Mage_Sales
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class OrganicInternet_SimpleConfigurableProducts_Sales_Block_Order_Email_Items_Order_Default extends Mage_Sales_Block_Order_Email_Items_Order_Default
{

    protected function getConfigurableProductParentId()
    {
        if ($this->getItem()->getProductOptionByCode('cpid')) {
            return $this->getItem()->getProductOptionByCode('cpid')->getValue();
        }
        #No idea why in 1.5 the stuff in buyRequest isn't auto-decoded from info_buyRequest
        #but then it's Magento we're talking about, so I've not a clue what's *meant* to happen.
        try {
            $buyRequest = $this->getItem()->getBuyRequest();			
            if($buyRequest->getData('cpid')) {
                return $buyRequest->getData('cpid');
            }
        } catch (Exception $e) {
        }
        return null;

    }

    protected function getConfigurableProductParent()
    {
        return Mage::getModel('catalog/product')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($this->getConfigurableProductParentId());
    }
	
    public function getProduct()
    {
        return Mage::getModel('catalog/product')
           ->setStoreId(Mage::app()->getStore()->getId())
                ->load($this->getItem()->getProductId());
    }

    public function getItemOptions()
	{
		$options = false;
		if (Mage::getStoreConfig('SCP_options/cart/show_custom_options')) {
			$options = parent::getItemOptions();
		}	
	
		if (Mage::getStoreConfig('SCP_options/cart/show_config_product_options')) {
			if ($this->getConfigurableProductParentId()) {
				$attributes = $this->getConfigurableProductParent()
					->getTypeInstance()
					->getUsedProductAttributes();
				foreach($attributes as $attribute) {			
					$options[] = array(
						'label' => $attribute->getFrontendLabel(),
						'value' => $this->getProduct()->getAttributeText($attribute->getAttributeCode()),
						'option_id' => $attribute->getId(),
					);
				}
			}
		}
	
		return $options;
	}


}
