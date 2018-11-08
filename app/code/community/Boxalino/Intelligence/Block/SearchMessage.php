<?php

/**
 * Class Boxalino_Intelligence_Block_SearchMessage
 */
class Boxalino_Intelligence_Block_SearchMessage extends Boxalino_Intelligence_Block_PluginConfig
{

    protected $bxHelperData;

    protected $p13nHelper;

    public function _construct()
    {
        if($this->getBxRewriteAllowed())
        {
            $this->bxHelperData = Mage::helper('boxalino_intelligence');
            $this->p13nHelper = $this->bxHelperData->getAdapter();
        }

        return parent::_construct();
    }

    public function isPluginActive()
    {
        return $this->getBxRewriteAllowed();
    }

    public function getResponse()
    {
        return $bxResponse = $this->p13nHelper->getResponse();
    }
}