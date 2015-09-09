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
 * @package     Mage_Catalog
 * @copyright   Copyright (c) 2011 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Catalog product items in same category.
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Mage_Catalog_Block_Product_Tagsfilter extends Mage_Catalog_Block_Product_Abstract
{
    /**
     * Default MAP renderer type
     *
     * @var string
     */
    protected $_mapRenderer = 'msrp_noform';
    protected $_itemCollection = NULL;
	public $_limit = 4;
	protected $_filteredTags = array();

    protected function _prepareData()
    {
		$this->_itemCollection = $this->getAdditionalCollection();
		
        return $this;
    }
	
	public function setLimit($limit = 4)
	{
		$this->_limit = $limit;	
	}
	
	protected function getAdditionalCollection() {
		
		$collection = Mage::getResourceModel('catalog/product_collection');
		Mage::getModel('catalog/layer')->prepareProductCollection($collection);
		
		if($filteredTags = $this->getFilteredTags())
		{	
			$attributeId = Mage::getResourceModel('eav/entity_attribute')->getIdByCode('catalog_product','tags');				
			$attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
			$attributeOptions = $attribute->getSource()->getAllOptions();
			$filteredTagIds = array();
					
			foreach($attributeOptions as $option)
			{
				if(in_array(trim(strtolower($option['label'])), $filteredTags))
				{
					$filteredTagIds[] = $option['value'];
				}	
			}
			
			if(count($filteredTagIds) > 0)
			{
				$collection->addAttributeToFilter('tags', array('in' => $filteredTagIds));
			}
		
		}

		$collection->getSelect()->order('rand()');
		$collection->addStoreFilter();
		$collection->setPage(1, $this->_limit);

		return $collection;
	}
	

    protected function _beforeToHtml()
    {
        $this->_prepareData();
        return parent::_beforeToHtml();
    }

    public function getItems()
    {
		$this->_itemCollection = $this->getAdditionalCollection();
        return $this->_itemCollection;
    }
	
    protected function setFilteredTags($tags = array())
    {
		foreach($tags as $tag)
		{
			$this->_filteredTags[] = trim(strtolower($tag));
		}
		
        return true;
    }
	
	
    protected function getFilteredTags()
    {
		if(count($this->_filteredTags) > 0)		
        	return $this->_filteredTags;
		
		return false;
    }
	
}
