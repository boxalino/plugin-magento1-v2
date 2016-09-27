<?php

/**
 * Class Boxalino_Intelligence_Block_Layer
 */
class Boxalino_Intelligence_Block_Layer extends Mage_CatalogSearch_Block_Layer{

    /**
     * @var array Collection of Boxalino_Intelligence_Block_Layer_Filter_Attribute
     */
    protected $bxFilters = array();

    /**
     * @param string $template
     * @return $this
     */
    public function setTemplate($template){
        
        $this->_template = 'boxalino/catalog/layer/view.phtml';
        return $this;
    }

    /**
     * @return $this
     */
    protected function _prepareFilters(){

        $bxHelperData = Mage::helper('intelligence');
        $filters = array();
        $facets = $bxHelperData->getAdapter()->getFacets();
        if ($facets) {
            foreach ($bxHelperData->getLeftFacetFieldNames() as $fieldName) {
                $filter = $this->getLayout()->createBlock('boxalino/layer_filter_attribute')
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
        
        $bxHelperData = Mage::helper('intelligence');
        if($bxHelperData->isFilterLayoutEnabled($this->getLayer() instanceof Mage_Catalog_Model_Layer) && $bxHelperData->isLeftFilterEnabled()){
            if(empty($this->bxFilters)){
                $this->_prepareFilters();
            }
            return $this->bxFilters;
        }
        return parent::getFilters();
    }
}