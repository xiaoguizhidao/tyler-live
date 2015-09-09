<?php
class OrganicInternet_SimpleConfigurableProducts_Catalog_Model_Product_Type_Simple
    extends Mage_Catalog_Model_Product_Type_Simple
{
    #Later this should be refactored to live elsewhere probably,
    #but it's ok here for the time being
    private function getCpid()
    {
        $cpid = $this->getProduct()->getCustomOption('cpid');
        if ($cpid) {
            return $cpid;
        }

        $br = $this->getProduct()->getCustomOption('info_buyRequest');
        if ($br) {
            $brData = unserialize($br->getValue());
            if(!empty($brData['cpid'])) {
                return $brData['cpid'];
            }
        }

        return false;
    }

    protected function _prepareProduct(Varien_Object $buyRequest, $product, $processMode)
    {
        $product = $this->getProduct($product);
        parent::_prepareProduct($buyRequest, $product, $processMode);

        if ($buyRequest->getCpid()) {
            $product->addCustomOption('cpid', $buyRequest->getCpid());
        }
        return array($product);
    }

    public function hasConfigurableProductParentId()
    {
        $cpid = $this->getCpid();
        //Mage::log("cpid: ". $cpid, null, 'cpid.log');
        return !empty($cpid);
    }

    public function getConfigurableProductParentId()
    {
        return $this->getCpid();
    }
}
