<?php

class OrganicInternet_SimpleConfigurableProducts_Model_Observer {

    /* Add cpid to product instance */
    public function catalogProductLoadBefore(Varien_Event_Observer $observer) {

        $action = Mage::app()->getFrontController()->getAction();
        if ($cpid = $action->getRequest()->getParam('cpid'))
        {
            $product = $observer->getProduct();
            $product->setCpid($cpid);
            $product->addCustomOption('cpid', $cpid);
        }
    }

}
