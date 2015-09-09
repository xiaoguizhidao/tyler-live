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
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
 
/**
 * Catalog product random items block
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author      Magento Core Team <core@magentocommerce.com>
 */
 
class Mage_Catalog_Block_Product_List_Special extends Mage_Catalog_Block_Product_List
{
    protected function _getProductCollection()
    {
        if (is_null($this->_productCollection)) {
            $categoryID = $this->getCategoryId();
            if($categoryID)
            {
              $category = new Mage_Catalog_Model_Category();
              $category->load($categoryID); // this is category id
              $collection = $category->getProductCollection();
            } else
            {
              $collection = Mage::getResourceModel('catalog/product_collection');
            }
             
            $todayDate = date('m/d/y');
            $tomorrow = mktime(0, 0, 0, date('m'), date('d')+1, date('y'));
            $tomorrowDate = date('m/d/y', $tomorrow);
             
            Mage::getModel('catalog/layer')->prepareProductCollection($collection);
            //$collection->getSelect()->order('rand()');
            $collection->addAttributeToSort('created_at', 'desc');
            $collection->addStoreFilter();
             
            $collection->addAttributeToFilter('special_from_date', array('date' => true, 'to' => $todayDate))
                ->addAttributeToFilter('special_to_date', array('or'=> array(
                0 => array('date' => true, 'from' => $tomorrowDate),
                1 => array('is' => new Zend_Db_Expr('null')))
                ), 'left');
             
             
            $numProducts = $this->getNumProducts() ? $this->getNumProducts() : 0;
            $collection->setPage(1, $numProducts)->load();
  
            $this->_productCollection = $collection;
        }
        return $this->_productCollection;
    }
}