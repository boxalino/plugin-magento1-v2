<?php

/**
 * Class Boxalino_Intelligence_Block_Layer_Filter_Attribute
 */
class Boxalino_Intelligence_Block_Layer_Filter_Attribute extends Mage_Catalog_Block_Layer_Filter_Abstract{

    /**
     * Boxalino_Intelligence_Block_Layer_Filter_Attribute constructor.
     */
    public function __construct(){
        parent::__construct();
        $this->_filterModelName = 'boxalino_intelligence/layer_filter_attribute';
    }

    /**
     * @param string $template
     * @return $this
     */
    public function setTemplate($template){
        $this->_template = 'boxalino/catalog/layer/filter.phtml';
        return $this;
    }

    /**
     * @return Mage_Catalog_Model_Layer_Filter_Attribute
     */
    public function getFacet(){
        return $this->_filter;
    }

    /**
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _initFilter(){
        if (!$this->_filterModelName) {
            Mage::throwException(Mage::helper('catalog')->__('Filter model name must be declared.'));
        }
        $this->_filter = Mage::getModel($this->_filterModelName)
            ->setLayer($this->getLayer())
            ->setFacets($this->getFacets())
            ->setFieldName($this->getFieldName())
            ->_initItems();
        return $this;
    }
}
