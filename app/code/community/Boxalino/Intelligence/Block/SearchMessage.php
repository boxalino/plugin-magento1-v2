<?php

/**
 * Class Boxalino_Intelligence_Block_SearchMessage
 */
class Boxalino_Intelligence_Block_SearchMessage extends Boxalino_Intelligence_Block_PluginConfig
{

    protected $bxHelperData;

    protected $p13nHelper;

    protected $response = null;

    protected $fallback = false;

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
        if($this->getBxRewriteAllowed())
        {
            $this->getResponse();
            if($this->fallback)
            {
                return false;
            }

            return true;
        }

        return false;
    }

    public function getResponse()
    {
        try {
            if(is_null($this->response))
            {
                $this->response = $this->p13nHelper->getResponse();
            }

            return $this->response;
        } catch(\Exception $e){
            $this->fallback = true;
            Mage::logException($e);
        }
    }
}