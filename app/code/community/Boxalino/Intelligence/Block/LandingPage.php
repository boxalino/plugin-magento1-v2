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
    $this->getOtherParams();
    $this->setLandingPageChoiceId();
    return $this->bxHelperData->isPluginEnabled();
  }

  public function getResponse(){

    return $bxResponse = $this->p13nHelper->getResponse();

  }

  public function getOtherParams(){

    if (!empty($this->getData('bxOtherParams'))) {

      $params = explode(',', $this->getData('bxOtherParams'));

      foreach ($params as $param) {

        $kv = explode('=', $param);

        $extraParams[$kv[0]] = $kv[1];

      }

    $this->p13nHelper->getLandingpageContextParameters($extraParams);

    }

  }

  public function setLandingPageChoiceId(){

    if (!empty($this->getData('choiceID'))) {
      $choice = $this->getData('choiceID');
    } else {
      $choice = 'landingpage';
    }

    $this->p13nHelper->setLandingPageChoiceId($choice);

  }

}

?>
