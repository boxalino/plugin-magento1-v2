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
     * @param $bxFacets
     */
    public function setFacets($bxFacets) {

        $this->bxFacets = $bxFacets;
        return $this;
    }

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

        $bxDataHelper = Mage::helper('intelligence');
        if($bxDataHelper->isFilterLayoutEnabled($this->getLayer() instanceof Mage_Catalog_Model_Layer)){
   
            $data = $this->_getItemsData();
            $items = [];
            foreach ($data as $itemData) {
                $selected = isset($itemData['selected']) ? $itemData['selected'] : null;
                $type = isset($itemData['type']) ? $itemData['type'] : null;
                $items[] = $this->_createItem($itemData['label'], $itemData['value'], $itemData['count'], $selected, $type);
            }
            $this->_items = $items;
            return $this;
        }
        return parent::_initItems();
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

        $data = [];
        $bxDataHelper = Mage::helper('intelligence');
        $this->_requestVar = $this->bxFacets->getFacetParameterName($this->fieldName);
        if (!$bxDataHelper->isHierarchical($this->fieldName)) {
            foreach ($this->bxFacets->getFacetValues($this->fieldName) as $facetValue) {
                if ($this->bxFacets->getSelectedValues($this->fieldName) && $this->bxFacets->getSelectedValues($this->fieldName)[0] == $facetValue) {
                    $value = $this->bxFacets->getSelectedValues($this->fieldName)[0] == $facetValue ? true : false;
                    $data[] = array(
                        'label' => strip_tags($this->bxFacets->getFacetValueLabel($this->fieldName, $facetValue)),
                        'value' => 0,
                        'count' => $this->bxFacets->getFacetValueCount($this->fieldName, $facetValue),
                        'selected' => $value,
                        'type' => 'flat'
                    );
                } else {
                    $value = false;
                    $data[] = array(
                        'label' => strip_tags($this->bxFacets->getFacetValueLabel($this->fieldName, $facetValue)),
                        'value' => $this->bxFacets->getFacetValueParameterValue($this->fieldName, $facetValue),
                        'count' => $this->bxFacets->getFacetValueCount($this->fieldName, $facetValue),
                        'selected' => $value,
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
            $sortOrder = $bxDataHelper->getCategoriesSortOrder();
            if($sortOrder == 2){
                $facetLabels = $this->bxFacets->getCategoriesKeyLabels();
                $childId = explode('/',end($facetLabels))[0];
                $childParentId = Mage::getModel('catalog/category')->load($childId)->getParentId();
                end($parentCategories);
                $parentId = key($parentCategories);
                $id = (($parentId == null) ? 2 : (($parentId == $childParentId) ? $parentId : $childParentId));

                $cat = $this->categoryFactory->create()->load($id);
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