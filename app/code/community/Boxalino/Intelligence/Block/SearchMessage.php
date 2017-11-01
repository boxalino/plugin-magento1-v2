<?php

/**
 * Class Boxalino_Intelligence_Block_SearchMessage
 */
class Boxalino_Intelligence_Block_SearchMessage extends Mage_Core_Block_Template{

  protected $bxHelperData;

  protected $p13nHelper;

  public function _construct(){

    $this->bxHelperData = Mage::helper('boxalino_intelligence');
    $this->p13nHelper = $this->bxHelperData->getAdapter();

    parent::_construct();

  }

  public function isPluginActive(){
    return Mage::helper('boxalino_intelligence')->isPluginEnabled();
  }

  public function getResponse(){

    return $bxResponse = $this->p13nHelper->getResponse();

  }


}

?>
