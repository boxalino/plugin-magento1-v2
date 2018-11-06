<?php

/**
 * Class Boxalino_Intelligence_Block_Journey_General
 */
Class Boxalino_Intelligence_Block_Journey_General extends Mage_Core_Block_Template
    implements Boxalino_Intelligence_Block_Journey_CPOJourney
{

    protected $bxJourney;

    protected $p13nHelper;

    public function _construct()
    {
        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        $this->p13nHelper = $this->bxHelperData->getAdapter();
        $this->bxJourney = Mage::getBlockSingleton('boxalino_intelligence/journey');
        parent::_construct();
    }

    public function getSubRenderings()
    {
        $elements = array();
        $element = $this->getData('bxVisualElement');
        if(isset($element['subRenderings'][0]['rendering']['visualElements'])) {
            $elements = $element['subRenderings'][0]['rendering']['visualElements'];
        }
        return $elements;
    }

    public function renderVisualElement($element, $additional_parameter = null)
    {
        return $this->bxJourney->createVisualElement($element, $additional_parameter)->toHtml();
    }

    public function getLocalizedValue($values)
    {
        return $this->p13nHelper->getResponse()->getLocalizedValue($values);
    }
}
