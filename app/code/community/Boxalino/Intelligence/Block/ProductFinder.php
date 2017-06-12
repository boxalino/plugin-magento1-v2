<?php

/**
 * Class Boxalino_Intelligence_Block_ProductFinder
 */
class Boxalino_Intelligence_Block_ProductFinder extends Mage_Core_Block_Template {

    protected function _construct()
    {
        parent::_construct();
        Mage::helper('boxalino_intelligence')->setIsProductFinderActive(true);
    }

    /**
     * @return array|mixed
     */
    public function getFieldNames() {
        return $this->getBxFacets()->getGiftFinderFacets();
    }

    /**
     * @return com\boxalino\bxclient\v1\BxFacets
     */
    public function getBxFacets() {
        $dataHelper = Mage::helper('boxalino_intelligence');
        $adapter = $dataHelper->getAdapter();
        return $adapter->getFacets();
    }

    /**
     * @return string
     */
    public function getParametersPrefix() {
        $dataHelper = Mage::helper('boxalino_intelligence');
        $adapter = $dataHelper->getAdapter();
        return $adapter->getPrefixContextParameter();
    }

    /**
     * @return mixed
     */
    public function getChoiceId() {
        return $this->getData('choice_id');
    }

    /**
     * @return string
     */
    public function getJSONData() {
        $json = [];
        $fieldNames = $this->getFieldNames();
        Mage::log(json_encode($fieldNames));
        if(!empty($fieldNames)) {
            $bxFacets = $this->getBxFacets();
            $facet_info = ['label', 'icon', 'iconMap', 'visualisation', 'jsonDependencies', 'position', 'isSoftFacet', 'isQuickSearch', 'order'];
            foreach ($fieldNames as $fieldName) {
                if($fieldName == ''){
                    continue;
                }
                $extraInfo = [];
                $json['facets'][$fieldName]['facetValues'] = $bxFacets->getFacetValues($fieldName);
                $json['facets'][$fieldName]['label'] = $bxFacets->getFacetLabel($fieldName);
                foreach ($facet_info as $info_key) {
                    $info = $bxFacets->getFacetExtraInfo($fieldName, $info_key);
                    if($info_key == 'isSoftFacet' && $info == null){
                        $facetMapping = [];
                        $attributeName = substr($fieldName, 9);
                        $json['facets'][$fieldNames]['parameterName'] = $attributeName;
                        $attributeModel = Mage::getModel('eav/config')->getAttribute('catalog_product', $attributeName)->getSource();
                        $options = $attributeModel->getAllOptions();
                        $responseValues =  Mage::helper('boxalino_intelligence')->useValuesAsKeys($json['facets'][$fieldName]['facetValues']);
                        foreach ($options as $option){
                            $label = is_array($option) ? $option['label'] : $option;
                            if(isset($responseValues[$label])){
                                $facetMapping[$label] = $option['value'];
                            }
                        }
                        $json['facets'][$fieldName]['facetMapping'] = $facetMapping;
                    }
                    if($info_key == 'jsonDependencies' || $info_key == 'label' ||$info_key == 'iconMap') {
                        $info = json_decode($info);
                        if($info_key == 'jsonDependencies') {
                            if(!is_null($info)) {
                                if(isset($info[0]) && isset($info[0]->values[0])) {
                                    $check = $info[0]->values[0];
                                    if(strpos($check, ',') !== false) {
                                        $info[0]->values = explode(',', $check);
                                    }
                                }
                            }
                        }
                    }
                    $extraInfo[$info_key] = $info;
                }
                $json['facets'][$fieldName]['facetExtraInfo'] = $extraInfo;
            }
            $json['parametersPrefix'] = $this->getParametersPrefix();
        }
        return json_encode($json);
    }
}
