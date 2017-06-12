<?php

/**
 * Class Boxalino_Intelligence_Block_Layer_View
 */
class Boxalino_Intelligence_Block_Layer_View extends Mage_Catalog_Block_Layer_View {

    /**
     * @var array Collection of Boxalino_Intelligence_Block_Layer_Filter_Attribute
     */
    protected $bxFilters = null;

    /**
     * @var null
     */
    protected $bxFacets = null;

    /**
     * @param string $template
     * @return $this
     */
    public function setTemplate($template){
        
        if(!Mage::helper('boxalino_intelligence')->isPluginEnabled()){
            return parent::setTemplate($template);
        }
        $this->_template = 'boxalino/catalog/layer/view.phtml';
        return $this;
    }

    /**
     * @return $this
     */
    protected function _prepareFilters(){

        $facets = $this->getBxFacets();
        $filters = array();
        if ($facets) {
            foreach ($facets->getLeftFacets() as $fieldName) {
                $filter = $this->getLayout()->createBlock('boxalino_intelligence/layer_filter_attribute')
                    ->setLayer($this->getLayer())
                    ->setFacets($facets)
                    ->setFieldName($fieldName)
                    ->setAttributeModel(Mage::getResourceModel('catalog/eav_attribute'))
                    ->init();
                $filters[] = $filter;
            }
        }
        $this->bxFilters = $filters;
        return $this;
    }

    /**
     * @return array
     */
    public function getFilters(){

        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($bxHelperData->isEnabledOnLayer($this->getLayer()) && !$bxHelperData->getAdapter()->areThereSubPhrases()){
            if(is_null($this->bxFilters)){
                $this->_prepareFilters();
            }
            return $this->bxFilters;
        }
        return parent::getFilters();
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
}
