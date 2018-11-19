<?php

/**
 * Class Boxalino_Intelligence_Block_Journey_General
 */
class Boxalino_Intelligence_Block_Journey_General extends Mage_Core_Block_Template
    implements Boxalino_Intelligence_Block_Journey_CPOJourney
{

    protected $renderer;
    protected $bxHelperData;
    protected $p13nHelper;

    public function _construct()
    {
        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        $this->p13nHelper = $this->bxHelperData->getAdapter();
        $this->renderer = Mage::getSingleton('boxalino_intelligence/visualElement_renderer');
        parent::_construct();
    }

    public function getSubRenderings()
    {
        return $this->renderer->getSubRenderingsByVisualElement($this->getData('bxVisualElement'));
    }

    public function renderVisualElement($element, $additional_parameter = null)
    {
        return $this->renderer->createVisualElement($element, $additional_parameter)->toHtml();
    }

    public function getLocalizedValue($values)
    {
        return $this->p13nHelper->getResponse()->getLocalizedValue($values);
    }

    /**
     * The localized questions must be retrieved for the given store view (en, de, fr, it, etc)
     *
     * @return bool|string
     */
    public function getLocale() {
        return substr(Mage::getStoreConfig('general/locale/code'), 0, 2);
    }
}
