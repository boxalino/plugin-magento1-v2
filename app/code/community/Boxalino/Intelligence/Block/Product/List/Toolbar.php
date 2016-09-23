<?php

class Boxalino_Intelligence_Block_Product_List_Toolbar extends Mage_Catalog_Block_Product_List_Toolbar
{

    public function getModeUrl($mode)
    {
        $url = $this->getPagerUrl(array($this->getModeVarName() => $mode, $this->getPageVarName() => null));
        // remove limit parameter when switching between view mode, it should always set the limit to
        // default when view mode is switched
        $urlHelper = Mage::helper('core/url');
        $url = $urlHelper->removeRequestParam(htmlspecialchars_decode($url), 'limit');
        return $url;
    }
}