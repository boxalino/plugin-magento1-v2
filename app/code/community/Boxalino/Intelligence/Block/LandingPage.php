<?php

/**
 * Class Boxalino_Intelligence_Block_LandingPage
 */
Class Boxalino_Intelligence_Block_LandingPage extends Mage_Core_Block_Template{

  protected $bxHelperData;

  protected $p13nHelper;

  public function _construct(){

    $this->bxHelperData = Mage::helper('boxalino_intelligence');
    $this->p13nHelper = $this->bxHelperData->getAdapter();

    parent::_construct();

  }

  public function isPluginActive(){
    $this->setLandingPageChoiceId();
    return $this->bxHelperData->isPluginEnabled();
  }

  public function getResponse(){

    return $bxResponse = $this->p13nHelper->getResponse();

  }

  public function setLandingPageChoiceId($choice = 'landingpage'){

    $this->p13nHelper->setLandingPageChoiceId($choice);

  }

}

?>
