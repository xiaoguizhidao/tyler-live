<?php
/**
* @author Amasty Team
* @copyright Copyright (c) 2008-2012 Amasty (http://www.amasty.com)
* @package Amasty_Shopby
*/
class Amasty_Shopby_Block_Top extends Mage_Core_Block_Template
{
    private $options = array();

    private function trim($str)
    {
        $str = strip_tags($str);
        $str = str_replace('"', '', $str);
        return trim($str, " -");
    }

    public function getBlockId()
    {
        return 'amshopby-filters-wrapper';
    }

    /**
     * @param Amasty_Shopby_Model_Page|null $page
     */
    protected function _handleCanonical($page = null)
    {
        if (!Mage::getStoreConfig('catalog/seo/category_canonical_tag')) {
            return;
        }

        if (is_object($page) && $page->getUrl()) {
            $url = $page->getUrl();
        } else {
            /** @var Amasty_Shopby_Helper_Url $urlHelper */
            $urlHelper = Mage::helper('amshopby/url');
            $url = $urlHelper->getCanonicalUrl();
        }

        if ($url) {
            $this->_replaceCanonical($url);
        }
    }

    protected function _replaceCanonical($url)
    {
        /** @var Mage_Page_Block_Html_Head $head */
        $head = Mage::app()->getLayout()->getBlock('head');

        foreach ($head->getData('items') as $item) {
            if (strpos($item['params'], 'canonical') !== false) {
                $head->removeItem('link_rel', $item['name']);
            };
        }

        $head->addLinkRel('canonical', $url);
    }

    /**
     * @param Mage_Catalog_Model_Category $category Will be updated according matched Page
     * @return bool
     */
    protected function _isPageHandled($category)
    {
        /** @var Amasty_Shopby_Helper_Page $pageHelper */
        $pageHelper = Mage::helper('amshopby/page');
        $page = $pageHelper->getCurrentMatchedPage();
        $this->_handleCanonical($page);
        if (is_null($page)) {
            return false;
        }

        /** @var Mage_Page_Block_Html_Head $head */
        $head = $this->getLayout()->getBlock('head');

        // metas
        $title = $head->getTitle();
        // trim prefix if any
        $prefix = Mage::getStoreConfig('design/head/title_prefix');
        $prefix = htmlspecialchars(html_entity_decode(trim($prefix), ENT_QUOTES, 'UTF-8'));
        if ($prefix){
            $title = substr($title, strlen($prefix));
        }
        $suffix = Mage::getStoreConfig('design/head/title_suffix');
        $suffix = htmlspecialchars(html_entity_decode(trim($suffix), ENT_QUOTES, 'UTF-8'));
        if ($suffix){
            $title = substr($title, 0, -1-strlen($suffix));
        }
        $descr = $head->getDescription();
        $kw = $head->getKeywords();

        $titleSeparator = Mage::getStoreConfig('amshopby/general/title_separator');
        $descrSeparator = Mage::getStoreConfig('amshopby/general/descr_separator');
        $kwSeparator = ',';

        if ($page->getUseCat()){
            $title = $title . $titleSeparator . $page->getMetaTitle();
            $descr = $descr . $descrSeparator . $page->getMetaDescr();
            $kw = $page->getMetaKw() . $kwSeparator . $kw;
        }
        else {
            $title = $page->getMetaTitle();
            $descr = $page->getMetaDescr();
            $kw = $page->getMetaKw();
        }

        $head->setTitle($this->trim($title));
        $head->setDescription($this->trim($descr));
        $head->setKeywords($this->trim($kw));

        // in-page description
        if ($page->getCmsBlockId()) {
            $this->setCategoryCmsBlock($category, $page->getCmsBlockId());
        }
        if ($page->getTitle()) {
            $category->setData('name', $page->getTitle());
        }

        return true;

    }

    protected function _prepareLayout()
    {
        /** @var Amasty_Shopby_Block_Catalog_Product_List_Toolbar $toolbar */
        $toolbar = $this->getLayout()->getBlock('product_list_toolbar');
        if ($toolbar instanceof Amasty_Shopby_Block_Catalog_Product_List_Toolbar) {
            $toolbar->replacePager();
        }

        /** @var Mage_Catalog_Model_Layer $layer */
        $layer = Mage::getSingleton('catalog/layer');
        $category = $layer->getCurrentCategory();

        if ($this->_isPageHandled($category)){
            $this->handleExtraAttributes();
            return parent::_prepareLayout();
        }

        $robotsIndex  = 'index';
        $robotsFollow = 'follow';


        $filters = Mage::getResourceModel('amshopby/filter_collection')
                ->addTitles()
                ->setOrder('position');
        $hash = array();

        /** @var Amasty_Shopby_Helper_Data $helper */
        $helper = Mage::helper('amshopby');

        foreach ($filters as $f){
            /** @var Amasty_Shopby_Model_Filter $f */
            $code = $f->getAttributeCode();
            $vals = $helper->getRequestValues($code);
            if ($vals){
                foreach($vals as $v){
                    $hash[$v] = $f->getShowOnList();
                }
                if ($f->getSeoNofollow()){
                    $robotsFollow = 'nofollow';
                }
                if ($f->getSeoNoindex()){
                    $robotsIndex = 'noindex';
                }
            }
        }

        $priceVals = Mage::app()->getRequest()->getParam('price');
        if ($priceVals) {
            if ($helper->getSeoPriceNofollow()){
                $robotsFollow = 'nofollow';
            }
            if ($helper->getSeoPriceNoindex()){
                $robotsIndex = 'noindex';
            }
        }

        /*
         * Check Category Settings
         */
        $currentCategoryId = $category->getId();
        $catNoIndex = Mage::getStoreConfig('amshopby/seo/cat_noindex');
        if ($catNoIndex != '') {
            $categoriesIds = array_flip(explode(",", $catNoIndex));
            if (isset($categoriesIds[$currentCategoryId])) {
                $robotsIndex = 'noindex';
            }
        }

        $catNoFollow = Mage::getStoreConfig('amshopby/seo/cat_nofollow');
        if ($catNoFollow != '') {
            $categoriesIds = array_flip(explode(",", $catNoFollow));
            if (isset($categoriesIds[$currentCategoryId])) {
                $robotsFollow = 'nofollow';
            }
        }
        $this->handleExtraAttributes();

        /** @var Mage_Page_Block_Html_Head $head */
        $head = $this->getLayout()->getBlock('head');
        if ($head){
            if ('noindex' == $robotsIndex || 'nofollow' == $robotsFollow){
                $head->setRobots($robotsIndex .', '. $robotsFollow);
            }
        }

        if (!$hash){
            return parent::_prepareLayout();
        }

        $options = Mage::getResourceModel('amshopby/value_collection')
            ->addFieldToFilter('option_id', array('in' => array_keys($hash)))
            ->load();

        $cnt = $options->count();
        if (!$cnt){
            return parent::_prepareLayout();
        }

        //some of the options value have wrong value;
        if ($cnt && $cnt < count($hash)){
            return parent::_prepareLayout();
            // or make 404 ?
        }

        // sort options by attribute ids and add "show_on_list" property
        foreach ($options as $opt){
            /** @var Amasty_Shopby_Model_Value $opt */
            $id = $opt->getOptionId();

            $opt->setShowOnList($hash[$id]);
            $hash[$id] = clone $opt;
        }

        // unset "fake"  options (not object)
        foreach ($hash as $id => $opt){
            if (!is_object($opt)){
                unset($hash[$id]);
            }
        }
        if (!$hash){
            return parent::_prepareLayout();
        }

        $this->options = $hash;

        if ($head){
            $this->changeMetaData($head);
        }

        $this->addBrandBreadcrumb();

        $this->addBottomCmsBlocks();

        $this->changeCategoryData($category);

        return parent::_prepareLayout();
    }

    protected function addBrandBreadcrumb()
    {
        $brandPageBrand = $this->getCurrentBrandPageBrand();
        if ($brandPageBrand) {
            /** @var Mage_Page_Block_Html_Breadcrumbs $breadcrumbs */
            $breadcrumbs = $this->getLayout()->getBlock('breadcrumbs');
            $breadcrumbs->addCrumb('amshopby-brand', array('label' => $brandPageBrand->getTitle(), 'title' => $brandPageBrand->getTitle()));
        }
    }

    protected function addBottomCmsBlocks()
    {
        foreach ($this->options as $opt) {
            /** @var Amasty_Shopby_Model_Value $opt */
            if (!$opt->getShowOnList()){
                continue;
            }

            $bottomBlockId = $opt->getCmsBlockBottomId();
            if ($bottomBlockId) {
                /** @var Mage_Cms_Block_Block $block */
                $block = $this->getLayout()->createBlock('cms/block');
                $block->setBlockId($bottomBlockId);
                $this->getLayout()->getBlock('content')->append($block);
            }
        }
    }

    protected function changeCategoryData(Mage_Catalog_Model_Category $category)
    {
        $brandPageBrand = $this->getCurrentBrandPageBrand();
        if ($brandPageBrand) {
            $category->setData('name', $brandPageBrand->getTitle());
            $category->setData('description', $brandPageBrand->getDescr());
            $category->setData('image', $brandPageBrand->getImgBig() ? '../../amshopby/' .$brandPageBrand->getImgBig() : null);
            $category->setData('landing_page', $brandPageBrand->getCmsBlockId());
        }

        $titles = array();
        $descriptions = array();
        $imageUrl = null;
        $cmsBlockId = null;

        foreach ($this->options as $opt){
            /** @var Amasty_Shopby_Model_Value $opt */

            if ($brandPageBrand && $brandPageBrand->getId() == $opt->getId()) {
                // Already applied
                continue;
            }

            if (!$opt->getShowOnList()){
                continue;
            }

            if ($opt->getTitle()) {
                $titles[] = $opt->getTitle();
            }

            if ($opt->getDescr()) {
                $descriptions[] = $opt->getDescr();
            }

            if ($opt->getCmsBlockId()) {
                $cmsBlockId = $opt->getCmsBlockId();
            }

            if ($opt->getImgBig()){
                $imageUrl = '../../amshopby/' . $opt->getImgBig();
            }
        }

        $position = Mage::getStoreConfig('amshopby/heading/add_title');
        $title = $this->insertContent($category->getName(), $titles, $position, Mage::getStoreConfig('amshopby/heading/h1_separator'));
        $category->setData('name', $title);

        $position = Mage::getStoreConfig('amshopby/heading/add_description');
        if ($descriptions && $position != Amasty_Shopby_Model_Source_Description_Position::DO_NOT_ADD) {
            $oldDescription = $category->getData('description');
            $description = '<span class="amshopby-descr">' . join('<br>', $descriptions) . '</span>';
            switch ($position) {
                case Amasty_Shopby_Model_Source_Description_Position::AFTER:
                    $description = $oldDescription ? $oldDescription . '<br>' . $description : $description;
                    break;
                case Amasty_Shopby_Model_Source_Description_Position::BEFORE:
                    $description = $oldDescription ? $description . '<br>' . $oldDescription : $description;
                    break;
                case Amasty_Shopby_Model_Source_Description_Position::REPLACE:
                    break;
            }
            $category->setData('description', $description);
        }

        if (isset($imageUrl) && Mage::getStoreConfig('amshopby/heading/add_image')) {
            $category->setData('image', $imageUrl);
        }

        if (isset($cmsBlockId) && Mage::getStoreConfig('amshopby/heading/add_cms_block')) {
            $this->setCategoryCmsBlock($category, $cmsBlockId);
        }
    }

    /**
     * @param Mage_Page_Block_Html_Head $head
     */
    protected function changeMetaData($head)
    {
        $brandPageBrand = $this->getCurrentBrandPageBrand();
        if ($brandPageBrand) {
            $head->setTitle($brandPageBrand->getMetaTitle());
            $head->setDescription($brandPageBrand->getMetaDescr());
            $head->setKeywords($brandPageBrand->getMetaKw());
        }

        $titles = array();
        $descriptions = array();
        $keywords = array();

        foreach ($this->options as $opt){
            /** @var Amasty_Shopby_Model_Value $opt */
            if ($brandPageBrand && $brandPageBrand->getId() == $opt->getId()) {
                // Was added above
                continue;
            }

            if ($opt->getMetaTitle())
                $titles[] = $opt->getMetaTitle();

            if ($opt->getMetaDescr())
                $descriptions[] = $opt->getMetaDescr();

            if ($opt->getMetaKw())
                $keywords[] = $opt->getMetaKw();
        }

        $oldTitle = $this->getOldMetaTitle($head);
        $titlePosition = Mage::getStoreConfig('amshopby/meta/add_title');
        $titleSeparator = Mage::getStoreConfig('amshopby/meta/title_separator');
        $title = $this->insertContent($oldTitle, $titles, $titlePosition, $titleSeparator);
        $head->setTitle($title);

        $oldDescription = $head->getDescription();
        $descriptionPosition = Mage::getStoreConfig('amshopby/meta/add_description');
        $descrSeparator = Mage::getStoreConfig('amshopby/meta/descr_separator');
        $description = $this->insertContent($oldDescription, $descriptions, $descriptionPosition, $descrSeparator);
        $head->setDescription($description);

        $keywordsPosition = Mage::getStoreConfig('amshopby/meta/add_keyword');
        $oldKeywords = $head->getKeywords();
        $kwSeparator = ', ';
        $keywords = $this->insertContent($oldKeywords, $keywords, $keywordsPosition, $kwSeparator);
        $head->setKeywords($keywords);
    }

    /**
     * @return Amasty_Shopby_Model_Value|null
     */
    protected function getCurrentBrandPageBrand()
    {
        /** @var Mage_Catalog_Model_Layer $layer */
        $layer = Mage::getSingleton('catalog/layer');
        $category = $layer->getCurrentCategory();

        /** @var Amasty_Shopby_Helper_Attributes $helper */
        $helper = Mage::helper('amshopby/attributes');

        $brand = $helper->getRequestedBrandOption();
        $isBrandPage = $brand && $category->getId() == Mage::app()->getStore()->getRootCategoryId();

        return $isBrandPage ? $brand : null;
    }

    /**
     * @param Mage_Page_Block_Html_Head $head
     * @return string
     */
    protected function getOldMetaTitle($head)
    {
        $title = $head->getTitle();
        // trim prefix if any
        $prefix = Mage::getStoreConfig('design/head/title_prefix');
        $prefix = htmlspecialchars(html_entity_decode(trim($prefix), ENT_QUOTES, 'UTF-8'));
        if ($prefix){
            $title = substr($title, strlen($prefix));
        }
        $suffix = Mage::getStoreConfig('design/head/title_suffix');
        $suffix = htmlspecialchars(html_entity_decode(trim($suffix), ENT_QUOTES, 'UTF-8'));
        if ($suffix){
            $title = substr($title, 0, -1-strlen($suffix));
        }
        return $title;
    }

    protected function insertContent($original, array $newParts, $position, $separator)
    {
        if ($newParts && $position != Amasty_Shopby_Model_Source_Description_Position::DO_NOT_ADD) {
            if ($original) {
                switch ($position) {
                    case Amasty_Shopby_Model_Source_Description_Position::AFTER:
                        array_unshift($newParts, $original);
                        break;
                    case Amasty_Shopby_Model_Source_Description_Position::BEFORE:
                        array_push($newParts, $original);
                        break;
                }
            }
            $result = join($separator, $newParts);
        }
        else {
            $result = $original;
        }
        $result = $this->trim($result);

        return $result;
    }

    /**
     * @param Mage_Catalog_Model_Category $category
     * @param int $cmsBlockId
     */
    protected function setCategoryCmsBlock($category, $cmsBlockId)
    {
        $category->setData('landing_page', $cmsBlockId);
        $mode = $category->getData('display_mode');
        if ($mode == Mage_Catalog_Model_Category::DM_PRODUCT) {
            $category->setData('display_mode', Mage_Catalog_Model_Category::DM_MIXED);
        }
    }

    /**
     * @deprecated
     * @return array
     */
    public function getOptions()
    {
        return array();
    }

/**
     * Handle price in urls.
     * If it noindex or nofollow tag is enabled - modify head tag
     */
    public function handleExtraAttributes()
    {
        $head = $this->getLayout()->getBlock('head');

        if ($head){

            $index = 'index';
            $follow = 'follow';

            /*
             * Set only if price is in request
             */
            if (Mage::app()->getRequest()->getParam('price')) {
                $robotsIndex = Mage::getStoreConfig('amshopby/general/price_tag_noindex');
                $robotsFollow = Mage::getStoreConfig('amshopby/general/price_tag_nofollow');

                if ($robotsIndex) {
                    $index = 'noindex';
                }

                if ($robotsFollow) {
                    $follow = 'nofollow';
                }

                $head->setRobots($index .', '. $follow);
            }
        }
    }

}