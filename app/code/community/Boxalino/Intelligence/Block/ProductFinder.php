<?php

/**
 * Class Boxalino_Intelligence_Block_ProductFinder
 */
class Boxalino_Intelligence_Block_ProductFinder extends Mage_Core_Block_Template {

    /**
     * @return array|mixed
     */
    public function getFieldNames() {
        return $this->getBxFacets()->getGiftFinderFacets();
    }

    /**
     * @return mixed
     */
    public function getP13nAdapter(){
        $dataHelper = Mage::helper('boxalino_intelligence');
        $adapter = $dataHelper->getAdapter();
        return $adapter;
    }

    /**
     * @return com\boxalino\bxclient\v1\BxFacets
     */
    public function getBxFacets() {
        return $this->getP13nAdapter()->getFacets(true);
    }

    /**
     *
     */
    public function getUrlParameterPrefix() {
        $this->getP13nAdapter()->getUrlParameterPrefix();
    }

    /**
     * @return string
     */
    public function getParametersPrefix() {
        return $this->getP13nAdapter()->getPrefixContextParameter();
    }

    /**
     *
     */
    protected function checkMode() {
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        $url = Mage::getSingleton('core/url')->parseUrl($currentUrl);
        $path = $url->getPath();
        $parts = explode('/', $path);
        if(end($parts) == $this->getData('finder_url')){
            Mage::helper('boxalino_intelligence')->setIsFinder(true);
        }
    }

    /**
     * @return string
     */
    public function getJSONData() {
        $this->checkMode();
        $json = [];
        $fieldNames = $this->getFieldNames();
        if(!empty($fieldNames)) {
            $bxFacets = $this->getBxFacets();
            foreach ($fieldNames as $fieldName) {
                if($fieldName == ''){
                    continue;
                }
                $facetExtraInfo = $bxFacets->getAllFacetExtraInfo($fieldName);
                $extraInfo = [];
                $facetValues = $bxFacets->getFacetValues($fieldName);
                $json['facets'][$fieldName]['facetValues'] = $facetValues;
                foreach ($facetValues as $value) {
                    if($bxFacets->isFacetValueHidden($fieldName, $value)) {
                        $json['facets'][$fieldName]['hidden_values'][] = $value;
                    }
                }
                $json['facets'][$fieldName]['label'] = $bxFacets->getFacetLabel($fieldName, $this->getLocale());
                foreach ($facetExtraInfo as $info_key => $info) {
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
                    if($info_key == 'jsonDependencies' || $info_key == 'label' || $info_key == 'iconMap' || $info_key == 'facetValueExtraInfo') {
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
            $json['parametersPrefix'] = $this->getUrlParameterPrefix();
            $json['contextParameterPrefix'] = $this->getParametersPrefix();
        }
        return json_encode($json);
    }

    /**
     * @return bool|string
     */
    public function getLocale() {
        return substr(Mage::getStoreConfig('general/locale/code'), 0, 2);
    }

    /**
     * @return string
     */
    public function getFinderUrl() {
        return Mage::getBaseUrl() . $this->getData('finder_url');
    }
}
