<?php

/**
 * Class Boxalino_Intelligence_Block_Facets
 */
class Boxalino_Intelligence_Block_Facets extends Mage_Core_Block_Template{

    /**
     * @param string $template
     * @return $this|Mage_Core_Block_Template
     */
    public function setTemplate($template)
    {
        if(!Mage::helper('boxalino_intelligence')->isPluginEnabled()){
            return $this;
        }
        return parent::setTemplate($template);
    }

    /**
     * @return array
     */
    public function getTopFilter(){
        $filter = [];
        $layer = Mage::getSingleton('catalog/layer');
        $bxHelperData = Mage::helper('boxalino_intelligence');

        if($bxHelperData->isEnabledOnLayer($layer)) {
            try {
                $facets = $bxHelperData->getAdapter()->getFacets();
                if ($facets) {
                    $fieldName = reset($facets->getTopFacets());
                    $filter = $this->getLayout()->createBlock('boxalino_intelligence/layer_filter_attribute')
                        ->setLayer($this->getLayer())
                        ->setAttributeModel(Mage::getResourceModel('catalog/eav_attribute'))
                        ->setFieldName($fieldName)
                        ->setFacets($facets)
                        ->init();
                    return $filter;
                }
            } catch (\Exception $e) {
                Mage::logException($e);
            }
        }
        return $filter;
    }
}
