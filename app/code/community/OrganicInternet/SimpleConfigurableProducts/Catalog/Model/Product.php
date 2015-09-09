<?php
class OrganicInternet_SimpleConfigurableProducts_Catalog_Model_Product
    extends Mage_Catalog_Model_Product
{
    protected $_configurableParentProduct = false;

    public function getMaxPossibleFinalPrice()
    {
        if(is_callable(array($this->getPriceModel(), 'getMaxPossibleFinalPrice'))) {
            return $this->getPriceModel()->getMaxPossibleFinalPrice($this);
        } else {
            #return $this->_getData('minimal_price');
            return parent::getMaxPrice();
        }
    }

    public function isVisibleInSiteVisibility()
    {
        #Force visible any simple products which have a parent conf product.
        #this will only apply to products which have been added to the cart
        if(is_callable(array($this->getTypeInstance(), 'hasConfigurableProductParentId'))
            && $this->getTypeInstance()->hasConfigurableProductParentId()) {
           return true;
        } else {
            return parent::isVisibleInSiteVisibility();
        }
    }

    public function getProductUrl($useSid = null)
    {
        if(is_callable(array($this->getTypeInstance(), 'hasConfigurableProductParentId'))
            && $this->getTypeInstance()->hasConfigurableProductParentId()) {

            $confProdId = $this->getTypeInstance()->getConfigurableProductParentId();
            return Mage::getModel('catalog/product')->load($confProdId)->getProductUrl();

        } else {
            return parent::getProductUrl($useSid);
        }
    }

    /**
     * Retrieves the configurable parent instance if it exists.
     *
     * @return product
     */
    public function getConfigurableParentProduct()
    {
        //Configurables don't have parents.
        if($this->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
            return false;

        if($this->_configurableParentProduct)
            return $this->_configurableParentProduct;


        $cpid = (int) $this->getCpid();

        if(!$cpid)
        {
            //Get cpid from saved custom option.
            if($this->getCustomOption('cpid'))
            {
                $cpid = (int) $this->getCustomOption('cpid')->getValue();
            }
            else
            {
                //retrive it from the current product, assuming it's configurable.
                $current_product = Mage::registry('current_product');
                if($current_product && $current_product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
                {
                    $cpid = $current_product->getId();
                }
                else
                {
                    $cpid = false;
                }
            }
        }
        if($cpid) {
            $this->_configurableParentProduct = Mage::getModel('catalog/product')->load($cpid);
            return $this->_configurableParentProduct;
        }

        return false;
    }

    /**
     * Checks to see if the parent has options.  If it does, then so does the child product.
     *
     * @return product
     */
    public function getHasOptions()
    {
        //Use the parent as the basis for "has options".
        if ($parent = $this->getConfigurableParentProduct()) {
            return $parent->getHasOptions();
        }
        return parent::getHasOptions();
    }


    /**
     * Get all options of product and the parent configurable.
     *
     * @return array
     */
    public function getOptions()
    {
        //Inject parent options to simples.
        if($parent = $this->getConfigurableParentProduct())
        {
            if ($parent->getHasOptions()) {
                foreach ($parent->getProductOptionsCollection() as $option) {
                    if(!$this->getOptionById($option->getId())) {
                        $option->setProduct($parent);
                        $option->setData('scp_is_option_from_parent', true);
                        $this->addOption($option);
                    }
                }
            }
        }
        return $this->_options;
    }
}
