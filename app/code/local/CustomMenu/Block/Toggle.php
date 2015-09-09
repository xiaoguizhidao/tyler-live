<?php

class Peerforest_CustomMenu_Block_Toggle extends Mage_Core_Block_Template
{
    public function _prepareLayout()
    {
        if (!Mage::getStoreConfig('custom_menu/general/enabled')) return;
        if (Mage::getStoreConfig('custom_menu/general/ie6_ignore') && Mage::helper('custommenu')->isIE6()) return;
        $layout = $this->getLayout();
        $topnav = $layout->getBlock('catalog.topnav');
        if (is_object($topnav))
        {
            $topnav->setTemplate('webandpeople/custommenu/top.phtml');
            $head = $layout->getBlock('head');
            $head->addItem('skin_js', 'webandpeople/custommenu/custommenu.js');
            $head->addItem('skin_css', 'webandpeople/custommenu/custommenu.css');
        }
    }
}
