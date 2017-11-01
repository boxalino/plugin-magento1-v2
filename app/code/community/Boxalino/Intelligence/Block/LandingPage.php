<?php

/**
 * Class Boxalino_Intelligence_Block_LandingPage
 */
Class Boxalino_Intelligence_Block_LandingPage extends Mage_Core_Block_Template{

  public function _prepareLayout()
{

    return parent::_prepareLayout();
}

public function isPluginActive(){
  return Mage::helper('boxalino_intelligence')->isPluginEnabled();
}

}

?>
