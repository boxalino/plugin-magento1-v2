<?php

/**
 * Class Boxalino_Intelligence_Block_Facets
 */
class Boxalino_Intelligence_Block_Facets extends Mage_Core_Block_Template{

    /**
     * @var null
     */
    protected $bxFacets = null;

    /**
     * @var null
     */
    protected $_layer = null;

    /**
     * @return array
     */
    public function getTopFilter(){
        $filter = [];
        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($bxHelperData->isEnabledOnLayer($this->getLayer())) {
            try {
                $facets = $this->getBxFacets();
                if ($facets) {
                    $fieldName = reset($facets->getTopFacets());
                    $filter = $this->getLayout()->createBlock('boxalino_intelligence/layer_filter_attribute')
                        ->setLayer($this->getLayer())
                        ->setAttributeModel(Mage::getResourceModel('catalog/eav_attribute'))
                        ->setFieldName($fieldName)
                        ->setFacets($facets)
                        ->init();
                    return $filter;
                }
            } catch (\Exception $e) {
                Mage::logException($e);
            }
        }
        return $filter;
    }

    /**
     * @return com\boxalino\bxclient\v1\BxFacets
     */
    public function getBxFacets(){
        if(is_null($this->bxFacets)) {
            $bxHelperData = Mage::helper('boxalino_intelligence');
            $this->bxFacets = $bxHelperData->getAdapter()->getFacets();
        }
        return $this->bxFacets;
    }

    /**
     * @return null
     */
    protected function getLayer(){
        if(is_null($this->_layer)){
            $this->_layer = Mage::registry('current_layer');
        }
        return $this->_layer;
    }
}
