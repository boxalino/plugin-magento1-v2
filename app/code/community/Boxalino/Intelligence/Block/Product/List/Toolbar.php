<?php

class Boxalino_Intelligence_Block_Product_List_Toolbar extends Mage_Catalog_Block_Product_List_Toolbar
{

    public function getModeUrl($mode)
    {
        if($this->checkIfPluginToBeUsed())
        {
            $url = $this->getPagerUrl(array($this->getModeVarName() => $mode, $this->getPageVarName() => null));
            // remove limit parameter when switching between view mode, it should always set the limit to
            // default when view mode is switched
            $urlHelper = Mage::helper('core/url');
            $url = $urlHelper->removeRequestParam(htmlspecialchars_decode($url), 'limit');
            return $url;
        }

        return parent::getModeUrl($mode);

    }

    /**
     * Before rewriting globally, check if the plugin is to be used
     * @return bool
     */
    public function checkIfPluginToBeUsed()
    {
        $boxalinoGlobalPluginStatus = Mage::helper('core')->isModuleOutputEnabled('Boxalino_Intelligence');
        if($boxalinoGlobalPluginStatus)
        {
            if(Mage::helper('boxalino_intelligence')->isPluginEnabled())
            {

                return true;
            }
        }

        return false;
    }
}