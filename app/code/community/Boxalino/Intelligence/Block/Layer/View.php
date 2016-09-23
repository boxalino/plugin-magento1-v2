<?php 
class Boxalino_Intelligence_Block_Layer_View extends Mage_Catalog_Block_Layer_View {

    protected $bxFacets;

    protected $bxFilters;

    public function setTemplate($template)
    {
        $this->_template = 'boxalino/catalog/layer/view.phtml';
        return $this;
    }

    protected function _prepareLayout(){

        $stateBlock = $this->getLayout()->createBlock($this->_stateBlockName)
            ->setLayer($this->getLayer())->setBxFilters($this->getActiveFilters());

        $categoryBlock = $this->getLayout()->createBlock($this->_categoryBlockName)
            ->setLayer($this->getLayer())
            ->init();

        $this->setChild('layer_state', $stateBlock);
        $this->setChild('category_filter', $categoryBlock);

        $filterableAttributes = $this->_getFilterableAttributes();
        foreach ($filterableAttributes as $attribute) {
            if ($attribute->getAttributeCode() == 'price') {
                $filterBlockName = $this->_priceFilterBlockName;
            } elseif ($attribute->getBackendType() == 'decimal') {
                $filterBlockName = $this->_decimalFilterBlockName;
            } else {
                $filterBlockName = $this->_attributeFilterBlockName;
            }

            $this->setChild($attribute->getAttributeCode() . '_filter',
                $this->getLayout()->createBlock($filterBlockName)
                    ->setLayer($this->getLayer())
                    ->setAttributeModel($attribute)
                    ->init());
        }

        $this->getLayer()->apply();
        return $this;
    }

    protected function _prepareFilters(){

        $bxHelperData = Mage::helper('intelligence');
        $filters = array();
        $facets = $this->getBxFacets();
        if ($facets) {
            $attr = array(array_keys($bxHelperData->getLeftFacetConfig())[0],$bxHelperData->getTopFacetFieldName());

            foreach ($attr as $fieldName) {
                $filter = $this->getLayout()->createBlock('boxalino/layer_filter_attribute')
                    ->setLayer($this->getLayer())
                    ->setAttributeModel(Mage::getResourceModel('catalog/eav_attribute'))
                    ->setFieldName($fieldName)
                    ->setFacets($facets)
                    ->setValue($facets->getSelectedValues($fieldName))
                    ->init();
                $filters[] = $filter;
            }
        }
        $this->bxFilters = $filters;
        return $this;
    }

    public function getFilters(){

        $bxHelperData = Mage::helper('intelligence');
        if($bxHelperData->isFilterLayoutEnabled($this->getLayer() instanceof Mage_Catalog_Model_Layer) && $bxHelperData->isLeftFilterEnabled()){
            if(!$this->bxFilters){
                $this->_prepareFilters();
            }
            return $this->bxFilters;
        }
        return parent::getFilters();
    }

    protected function getActiveFilters(){

        $activeFilters = array();
        foreach ($this->getFilters() as $filter){
            if($this->getBxFacets()->isSelected($filter->getFieldName())){
                $activeFilters[] = Mage::getModel('catalog/layer_filter_item')
                    ->setFilter($filter)
                    ->setValue($this->getBxFacets()->getSelectedValueLabel($filter->getFieldName()));
            }
        }
        return $activeFilters;
    }

    protected function getBxFacets(){

        if($this->bxFacets == null){
            $bxHelperData = Mage::helper('intelligence');
            $this->bxFacets = $bxHelperData->getAdapter()->getFacets();
        }
        return $this->bxFacets;
    }
}