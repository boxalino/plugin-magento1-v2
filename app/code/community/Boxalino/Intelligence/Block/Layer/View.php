<?php

/**
 * Class Boxalino_Intelligence_Block_Layer_View
 */
class Boxalino_Intelligence_Block_Layer_View extends Mage_Catalog_Block_Layer_View {

    /**
     * @var array Collection of Boxalino_Intelligence_Block_Layer_Filter_Attribute
     */
    protected $bxFilters = array();

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

        $bxHelperData = Mage::helper('boxalino_intelligence');
        $filters = array();
        $facets = $bxHelperData->getAdapter()->getFacets();
        if ($facets) {
            foreach ($bxHelperData->getLeftFacetFieldNames() as $fieldName) {
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

        echo "view";exit;
        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($bxHelperData->isFilterLayoutEnabled($this->getLayer()) && $bxHelperData->isLeftFilterEnabled() && !$bxHelperData->getAdapter()->areThereSubPhrases()){
            if(empty($this->bxFilters)){
                $this->_prepareFilters();
            }
            return $this->bxFilters;
        }
        return parent::getFilters();
    }
}
