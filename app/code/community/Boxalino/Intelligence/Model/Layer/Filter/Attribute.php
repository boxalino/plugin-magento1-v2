<?php

/**
 * Class Boxalino_Intelligence_Model_Layer_Filter_Attribute
 */
class Boxalino_Intelligence_Model_Layer_Filter_Attribute extends Mage_Catalog_Model_Layer_Filter_Attribute{

    /**
     * @var null
     */
    private $bxFacets = null;

    /**
     * @var array
     */
    private $fieldName = array();

    /**
     * @var
     */
    private $is_bx_attribute;

    /**
     * @param $bxFacets
     */
    public function setFacets($bxFacets) {

        $this->bxFacets = $bxFacets;
        return $this;
    }

    /**
     * @return null
     */
    public function getFacets(){
        return $this->bxFacets;
    }
    
    /**
     * @param $fieldName
     */
    public function setFieldName($fieldName) {

        $this->fieldName = $fieldName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getName(){

        return $this->bxFacets->getFacetLabel($this->fieldName);
    }

    /**
     * @return array
     */
    public function getFieldName(){

        return $this->fieldName;
    }

    /**
     *
     */
    public function _initItems(){

        $bxHelperData =  Mage::helper('boxalino_intelligence');
        if(!$bxHelperData->getAdapter()->areThereSubPhrases()){
            $this->is_bx_attribute = $bxHelperData->isBxAttribute($this->fieldName);
            $data = $this->_getItemsData();
            $items = [];
            foreach ($data as $itemData) {
                $selected = isset($itemData['selected']) ? $itemData['selected'] : null;
                $type = isset($itemData['type']) ? $itemData['type'] : null;
                $items[] = $this->_createItem($itemData['label'], $itemData['value'], $itemData['count'], $selected, $type);
            }
            $this->_items = $items;
        }
        return $this;
    }

    /**
     * @param string $label
     * @param mixed $value
     * @param int $count
     * @param null $selected
     * @param null $type
     * @return mixed
     */
    public function _createItem($label, $value, $count = 0, $selected = null, $type = null){
        
        return Mage::getModel('catalog/layer_filter_item')
            ->setFilter($this)
            ->setLabel($label)
            ->setValue($value)
            ->setCount($count)
            ->setSelected($selected)
            ->setType($type);
    }

    /**
     * @return array
     */
    protected function _getItemsData(){

        if($this->fieldName == 'discountedPrice'){
            return array('label' => null, 'value' => null, 'count' => null, 'selected' => null, 'type' => null);
        }
        $data = [];
        $bxDataHelper = Mage::helper('boxalino_intelligence');
        $this->_requestVar = str_replace('bx_products_', '', $this->bxFacets->getFacetParameterName($this->fieldName));
        if (!$bxDataHelper->isHierarchical($this->fieldName)) {
            $attributeModel = Mage::getModel('eav/config')->getAttribute('catalog_product', substr($this->fieldName,9))->getSource();
            $order = $bxDataHelper->getFieldSortOrder($this->fieldName);
            if($order == 2){
                $values = $attributeModel->getAllOptions();
                $responseValues = $bxDataHelper->useValuesAsKeys($this->bxFacets->getFacetValues($this->fieldName));
                $selectedValues = $bxDataHelper->useValuesAsKeys($this->bxFacets->getSelectedValues($this->fieldName));
                foreach($values as $value){

                    $label = is_array($value) ? $value['label'] : $value;
                    if(isset($responseValues[$label])){
                        $facetValue = $responseValues[$label];
                        $selected = isset($selectedValues[$facetValue]) ? true : false;
                        $paramValue = $this->is_bx_attribute ? $this->bxFacets->getFacetValueParameterValue($this->fieldName, $facetValue): $attributeModel->getOptionId($this->bxFacets->getFacetValueParameterValue($this->fieldName, $facetValue));
                        $data[] = array(
                            'label' => strip_tags($this->bxFacets->getFacetValueLabel($this->fieldName, $facetValue)),
                            'value' => $selected ? 0 : $paramValue,
                            'count' => $this->bxFacets->getFacetValueCount($this->fieldName, $facetValue),
                            'selected' => $selected,
                            'type' => 'flat'
                        );
                    }
                }
            }else{
                $selectedValues = $bxDataHelper->useValuesAsKeys($this->bxFacets->getSelectedValues($this->fieldName));
                $responseValues = $this->bxFacets->getFacetValues($this->fieldName);

                foreach ($responseValues as $facetValue){

                    $selected = isset($selectedValues[$facetValue]) ? true : false;
                    $paramValue = $this->is_bx_attribute ? $this->bxFacets->getFacetValueParameterValue($this->fieldName, $facetValue): $attributeModel->getOptionId($this->bxFacets->getFacetValueParameterValue($this->fieldName, $facetValue));
                    $data[] = array(
                        'label' => strip_tags($this->bxFacets->getFacetValueLabel($this->fieldName, $facetValue)),
                        'value' => $selected ? 0 : $paramValue,
                        'count' => $this->bxFacets->getFacetValueCount($this->fieldName, $facetValue),
                        'selected' => $selected,
                        'type' => 'flat'
                    );
                }
            }
        } else {
            $count = 1;
            $facetValues = array();
            $parentCategories = $this->bxFacets->getParentCategories();
            $parentCount = count($parentCategories);
            $value = false;
            foreach ($parentCategories as $key => $parentCategory) {
                if ($count == 1) {
                    $count++;
                    $homeLabel = __("All Categories");
                    $data[] = array(
                        'label' => strip_tags($homeLabel),
                        'value' => 2,
                        'count' => $this->bxFacets->getParentCategoriesHitCount($key),
                        'selected' => $value,
                        'type' => 'home parent'
                    );
                    continue;
                }
                if ($parentCount == $count++) {
                    $value = true;
                }
                $data[] = array(
                    'label' => strip_tags($parentCategory),
                    'value' => $key,
                    'count' => $this->bxFacets->getParentCategoriesHitCount($key),
                    'selected' => $value,
                    'type' => 'parent'
                );
            }
            $sortOrder = $bxDataHelper->getFieldSortOrder($this->fieldName);
            if($sortOrder == 2){
                $facetLabels = $this->bxFacets->getCategoriesKeyLabels();
                $childId = explode('/',end($facetLabels))[0];
                $category_model = Mage::getModel('catalog/category');
                $childParentId = $category_model->load($childId)->getParentId();
                end($parentCategories);
                $parentId = key($parentCategories);
                $id = (($parentId == null) ? 2 : (($parentId == $childParentId) ? $parentId : $childParentId));

                $cat = $category_model->load($id);
                foreach($cat->getChildrenCategories() as $category){
                    if(isset($facetLabels[$category->getName()])) {
                        $facetValues[] = $facetLabels[$category->getName()];
                    }
                }
            }
            if($facetValues == null){
                $facetValues = $this->bxFacets->getCategories();
            }

            foreach ($facetValues as $facetValue) {
                $id =  $this->bxFacets->getFacetValueParameterValue($this->fieldName, $facetValue);
                if ($sortOrder == 2 || Mage::helper('catalog/category')->canShow((int)$id)) {
                    $data[] = array(
                        'label' => strip_tags($this->bxFacets->getFacetValueLabel($this->fieldName, $facetValue)),
                        'value' => $id,
                        'count' => $this->bxFacets->getFacetValueCount($this->fieldName, $facetValue),
                        'selected' => false,
                        'type' => $value ? 'children' : 'home'
                    );
                }
            }
        }
        return $data;
    }
}
