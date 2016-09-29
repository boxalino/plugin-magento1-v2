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
        if(Mage::helper('intelligence')->isPluginEnabled()){
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
        $bxHelperData = Mage::helper('intelligence');

        if($bxHelperData->isFilterLayoutEnabled($layer) && $bxHelperData->isTopFilterEnabled()) {

            try {
                $facets = $bxHelperData->getAdapter()->getFacets();
                if ($facets) {
                    $fieldName = $bxHelperData->getTopFacetFieldName();
                    $filter = $this->getLayout()->createBlock('boxalino/layer_filter_attribute')
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