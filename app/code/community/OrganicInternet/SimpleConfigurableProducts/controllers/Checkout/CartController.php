<?php
require_once 'Mage/Checkout/controllers/CartController.php';
class OrganicInternet_SimpleConfigurableProducts_Checkout_CartController extends Mage_Checkout_CartController {

    /**
     * Action to reconfigure cart item
     * Modified to allow configuration of simple items assigned to configurables.
     */
    public function configureAction()
    {
        // Extract item and product to configure
        $id = (int) $this->getRequest()->getParam('id');
        $quoteItem = null;
        $cart = $this->_getCart();
        if ($id) {
            $quoteItem = $cart->getQuote()->getItemById($id);
        }

        $productId = $quoteItem->getProduct()->getId();

        //Check to see if this is a simple assigned to a configurable.
        if($quoteItem->getOptionByCode('cpid')) {
            $cpid = $quoteItem->getOptionByCode('cpid')->getValue();
            $productId = (int) $cpid;
        }

        if (!$quoteItem) {
            $this->_getSession()->addError($this->__('Quote item is not found.'));
            $this->_redirect('checkout/cart');
            return;
        }

        try {
            $params = new Varien_Object();
            $params->setCategoryId(false);
            $params->setConfigureMode(true);
            $params->setBuyRequest($quoteItem->getBuyRequest());

            Mage::helper('catalog/product_view')->prepareAndRender($productId, $this, $params);
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Cannot configure product: '.$quoteItem->getProduct()->getId()));
            Mage::logException($e);
            $this->_goBack();
            return;
        }
    }

    /**
     * Update product configuration for a cart item
     * Modified to allow the switching of product ids in the cart if selected from a configurable parent.
     */
    public function updateItemOptionsAction()
    {

        $cart   = $this->_getCart();
        $id = (int) $this->getRequest()->getParam('id');
        $params = $this->getRequest()->getParams();

        $quoteItem = $cart->getQuote()->getItemById($id);
        if (!$quoteItem) {
            Mage::throwException($this->__('Quote item is not found.'));
        }

        //Switch quote item with new select product id.
        if($params['product'] != $quoteItem->getProduct()->getId())
        {
            $newProduct = Mage::getSingleton('catalog/product')->load($params['product']);
            $quoteItem->setProduct($newProduct);
        }

        parent::updateItemOptionsAction();
    }
}