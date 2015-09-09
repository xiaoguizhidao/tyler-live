<?php

if (!Mage::getStoreConfig('custom_menu/general/enabled') ||
   (Mage::getStoreConfig('custom_menu/general/ie6_ignore') && Mage::helper('custommenu')->isIE6()))
{
    class Peerforest_CustomMenu_Block_Topmenu extends Mage_Page_Block_Html_Topmenu
    {
        protected function _getHtml(Varien_Data_Tree_Node $menuTree, $childrenWrapClass)
        {
            $html = '';
    
            $children = $menuTree->getChildren();
            $parentLevel = $menuTree->getLevel();
            $childLevel = is_null($parentLevel) ? 0 : $parentLevel + 1;
    
            $counter = 1;
            $childrenCount = $children->count();
    
            $parentPositionClass = $menuTree->getPositionClass();
            $itemPositionClassPrefix = $parentPositionClass ? $parentPositionClass . '-' : 'nav-';
    
            foreach ($children as $child) {
    
                $child->setLevel($childLevel);
                $child->setIsFirst($counter == 1);
                $child->setIsLast($counter == $childrenCount);
                $child->setPositionClass($itemPositionClassPrefix . $counter);
    
                $outermostClassCode = '';
                $outermostClass = $menuTree->getOutermostClass();
    
                if ($childLevel == 0 && $outermostClass) {
                    $outermostClassCode = ' class="' . $outermostClass . '" ';
                    $child->setClass($outermostClass);
                }
    
                $html .= '<li ' . $this->_getRenderedMenuItemAttributes($child) . '>';
                $html .= '<a href="' . $child->getUrl() . '" ' . $outermostClassCode . '>';
                if($child->getData('category_label')){
                        $html .= '<span class="category-label">' . $child->getData('category_label') . '</span>';
                    }
                $html .=  '<span>' . $this->escapeHtml($child->getName()) . '</span><div class="drop-active visible-onhover"></div>';
                $html .= '</a>';
                if ($child->hasChildren()) {
                    if (!empty($childrenWrapClass)) {
                        $html .= '<div class="' . $childrenWrapClass . '">';
                    }
                    $html .= '<ul class="level' . $childLevel . '">';
                    $html .= $this->_getHtml($child, $childrenWrapClass);
                    $html .= '</ul>';
    
                    if (!empty($childrenWrapClass)) {
                        $html .= '</div>';
                    }
                }
                $html .= '</li>';
    
                $counter++;
            }
    
            return $html;
        }
    }
    return;
}

class Peerforest_CustomMenu_Block_Topmenu extends Peerforest_CustomMenu_Block_Navigation
{

}
