<?php
/**
 * Include configurable parent options for SCP extension.
 */
class OrganicInternet_SimpleConfigurableProducts_Adminhtml_Block_Sales_Items_Column_Name extends Mage_Adminhtml_Block_Sales_Items_Column_Name
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

    public function getOrderOptions()
    {
        $result = parent::getOrderOptions();

        if (Mage::getStoreConfig('SCP_options/cart/show_config_product_options')) {
            if ($this->getConfigurableProductParentId()) {
                $attributes = $this->getConfigurableProductParent()
                    ->getTypeInstance()
                    ->getUsedProductAttributes();
                foreach($attributes as $attribute) {
                    $configOptions[] = array(
                        'label' => $attribute->getFrontendLabel(),
                        'value' => $this->getProduct()->getAttributeText($attribute->getAttributeCode()),
                        'option_id' => $attribute->getId(),
                    );
                }
                $result = array_merge($result, $configOptions);
            }
        }

        return $result;
    }
}
