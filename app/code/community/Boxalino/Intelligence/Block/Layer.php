<?php

/**
 * Class Boxalino_Intelligence_Block_Layer
 */
class Boxalino_Intelligence_Block_Layer extends Mage_CatalogSearch_Block_Layer
{

    /**
     * @var array Collection of Boxalino_Intelligence_Block_Layer_Filter_Attribute
     */
    protected $bxFilters = null;

    /**
     * @var null
     */
    protected $bxFacets = null;

    /**
     * @var null
     */
    protected $bxRewriteAllowed = null;

    /**
     * @var null
     */
    protected $seoMapping = null;

    /**
     * @param string $template
     * @return $this
     */
    public function setTemplate($template)
    {
        if(!$this->getBxRewriteAllowed())
        {
            return parent::setTemplate($template);
        }

        if(!Mage::helper('boxalino_intelligence')->isEnabledOnLayer($this->getLayer())){
            return parent::setTemplate($template);
        }
        $this->_template = 'boxalino/catalog/layer/view.phtml';
        return $this;
    }

    /**
     * @return $this
     */
    protected function _prepareFilters()
    {
        $facetModel = Mage::getSingleton('boxalino_intelligence/facet');
        $facets = $this->getBxFacets();
        $filters = array();
        if ($facets) {
            foreach ($facets->getLeftFacets() as $fieldName) {
                if($fieldName == 'category_id') continue;
                $filter = $this->getLayout()->createBlock('boxalino_intelligence/layer_filter_attribute')
                    ->setLayer($this->getLayer())
                    ->setFacets($facets)
                    ->setFieldName($fieldName)
                    ->setSeoFieldName($this->getSeoFieldNameByField($fieldName))
                    ->setAttributeModel(Mage::getResourceModel('catalog/eav_attribute'))
                    ->init();
                $filters[] = $filter;
            }
        }
        $facetModel->setFacets($filters);
        $this->bxFilters = $filters;
        return $this;
    }

    public function getSeoFieldNameByField($fieldName)
    {
        if(is_null($this->seoMapping))
        {
            $this->seoMapping =  Mage::helper('boxalino_intelligence')->getSeoFilterMapping();
        }

        if($seoField = array_search($fieldName, $this->seoMapping))
        {
            return $seoField;
        }

        return null;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        if(!$this->getBxRewriteAllowed())
        {
            return parent::getFilters();
        }

        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($bxHelperData->isEnabledOnLayer($this->getLayer()))
        {
            $category = $this->getLayer()->getCurrentCategory();
            if(!is_null($category))
            {
                $bxHelperData->getAdapter()->setIsNavigation(true);
            }

            if(is_null($this->bxFilters))
            {
                $this->_prepareFilters();
            }

            return $this->bxFilters;
        }

        return parent::getFilters();
    }

    /**
     * @return com\boxalino\bxclient\v1\BxFacets
     */
    public function getBxFacets()
    {
        if(is_null($this->bxFacets)) {
            $bxHelperData = Mage::helper('boxalino_intelligence');
            $this->bxFacets = $bxHelperData->getAdapter()->getFacets();
        }

        return $this->bxFacets;
    }

    /**
     * @return bool
     */
    public function canShowBlock()
    {
        if(!$this->getBxRewriteAllowed())
        {
            return parent::canShowBlock();
        }

        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($bxHelperData->isEnabledOnLayer($this->getLayer())){
            if(!$bxHelperData->getAdapter()->areThereSubPhrases() && sizeof($this->getFilters()) > 0) {
                return true;
            }
            return false;
        }
        return parent::canShowBlock();
    }

    /**
     * Before rewriting globally, check if the plugin is to be used
     * @return bool
     */
    public function checkIfPluginToBeUsed()
    {
        $boxalinoGlobalPluginStatus = Mage::helper('core')->isModuleOutputEnabled('Boxalino_Intelligence');
        if($boxalinoGlobalPluginStatus)
        {
            if(Mage::helper('boxalino_intelligence')->isPluginEnabled())
            {

                return true;
            }
        }

        return false;
    }

    public function getBxRewriteAllowed()
    {
        if(is_null($this->bxRewriteAllowed))
        {
            $this->bxRewriteAllowed = $this->checkIfPluginToBeUsed();
        }

        return $this->bxRewriteAllowed;
    }
}
