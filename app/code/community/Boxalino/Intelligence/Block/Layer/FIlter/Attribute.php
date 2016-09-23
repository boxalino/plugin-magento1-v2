<?php
class Boxalino_Intelligence_Block_Layer_Filter_Attribute extends Mage_Catalog_Block_Layer_Filter_Abstract{
    
    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'intelligence/layer_filter_attribute';
    }

    public function setTemplate($template)
    {
        $this->_template = 'boxalino/catalog/layer/filter.phtml';
        return $this;
    }

    /**
     * Init filter model object
     *
     * @return Mage_Catalog_Block_Layer_Filter_Abstract
     */
    protected function _initFilter()
    {
        if (!$this->_filterModelName) {
            Mage::throwException(Mage::helper('catalog')->__('Filter model name must be declared.'));
        }

        $this->_filter = Mage::getModel($this->_filterModelName)
            ->setLayer($this->getLayer())
            ->setFacets($this->getFacets())
            ->setFieldName($this->getFieldName())
            ->setRequestVar($this->getFieldName());

        return $this;
    }

}