<?php

/**
 * Class Boxalino_Intelligence_Block_ProductFinder
 */
class Boxalino_Intelligence_Block_ProductFinder extends Mage_Core_Block_Template
{

    /**
     * @var bool
     */
    protected $bxRewriteAllowed = false;

    /**
     * @return array|mixed
     */
    public function getFieldNames() {
        return $this->getBxFacets()->getFacetExtraInfoFacets('finderFacet', 'true', false, false, true);
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
     * @return string
     */
    public function getUrlParameterPrefix() {
        return $this->getP13nAdapter()->getUrlParameterPrefix();
    }

    /**
     * @return string
     */
    public function getParametersPrefix()
    {
        return $this->getP13nAdapter()->getPrefixContextParameter();
    }

    /**
     *
     */
    protected function checkMode()
    {
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        $url = Mage::getSingleton('core/url')->parseUrl($currentUrl);
        $path = $url->getPath();
        if(strpos($path, $this->getData('finder_url_pattern')) !== false){
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

            $json['separator'] = Mage::helper('boxalino_intelligence')->getSeparator();
            $json['level'] = $this->getFinderLevel();
            $json['parametersPrefix'] = $this->getUrlParameterPrefix();
            $json['contextParameterPrefix'] = $this->getParametersPrefix();
        }
        return json_encode($json);
    }

    public function getFinderLevel() {
        $adapter = $this->getP13nAdapter();
        $ids = $adapter->getEntitiesIds();
        $level = 10;
        $h = 0;
        foreach ($ids as $id) {
            if($adapter->getHitVariable($id, 'highlighted')){
                if($h++ >= 2){
                    $level = 5;
                    break;
                }
            }
            if($h == 0) {
                $level = 1;
                break;
            } else {
                break;
            }
        }
        return $level;
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

    /**
     * Used for narrative tracker
     * @return string|null
     */
    public function getRequestUuid()
    {
        if($this->getBoxalinoPluginInUse())
        {
            return $this->getP13nAdapter()->getRequestUuid();
        }

        return null;
    }

    /**
     * Used for narrative tracker
     * @return string|null
     */
    public function getRequestGroupBy()
    {
        if($this->getBoxalinoPluginInUse())
        {
            return $this->getP13nAdapter()->getRequestGroupBy();
        }

        return null;
    }

    public function getBoxalinoPluginInUse()
    {
        if(is_null($this->bxRewriteAllowed))
        {
            $boxalinoGlobalPluginStatus = Mage::helper('core')->isModuleOutputEnabled('Boxalino_Intelligence');
            if($boxalinoGlobalPluginStatus)
            {
                if(Mage::helper('boxalino_intelligence')->isPluginEnabled())
                {
                    $this->bxRewriteAllowed = true;
                }
            }

            $this->bxRewriteAllowed = false;
        }

        return $this->bxRewriteAllowed;
    }

}
