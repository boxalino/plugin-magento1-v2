<?php

/**
 * Class Boxalino_Intelligence_Block_Journey_Product_List_Toolbar
 *
 * To be used with the default Magento toolbar template
 * catalog/product/list/toolbar.phtml
 */
class Boxalino_Intelligence_Block_Journey_Product_List_Toolbar extends Mage_Catalog_Block_Product_List_Toolbar
    implements Boxalino_Intelligence_Block_Journey_CPOJourney
{
    protected $renderer;
    protected $bxHelperData;
    protected $p13nHelper;
    protected $bxResourceManager;

    public function _construct()
    {
        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        $this->p13nHelper = $this->bxHelperData->getAdapter();
        $this->renderer = Mage::getSingleton('boxalino_intelligence/visualElement_renderer');
        $this->bxResourceManager = Mage::helper('boxalino_intelligence/resourceManager');

        $this->prepareCollection();
        parent::_construct();
    }

    public function prepareCollection()
    {
        $variant_index = $this->getVariantIndex();
        $collection = $this->bxResourceManager->getResource($variant_index, 'collection');
        if(is_null($collection)) {
            $collection = $this->createCollection($variant_index);
            $this->bxResourceManager->setResource($collection, $variant_index, 'collection');
        }

        $this->setCollection($collection);
    }

    public function getSubRenderings()
    {
        return $this->renderer->getSubRenderingsByVisualElement($this->getData('bxVisualElement'));
    }

    public function renderVisualElement($element, $additional_parameter = null)
    {
        return $this->renderer->createVisualElement($element, $additional_parameter)->toHtml();
    }

    public function getLocalizedValue($values)
    {
        return $this->renderer->getLocalizedValue($values);
    }

    public function getVariantIndex()
    {
        $variant_index = 0;
        $visualElement = $this->getData('bxVisualElement');
        foreach ($visualElement['parameters'] as $parameter) {
            if($parameter['name'] == 'variant') {
                $variant_index = reset($parameter['values']);
                break;
            }
        }
        return $variant_index;
    }

    public function createCollection($variant_index)
    {
        $entity_ids = $this->p13nHelper->getEntitiesIds($variant_index);
        $collection = $this->bxHelperData->prepareProductCollection($entity_ids);
        $collection->setStore(Mage::app()->getStore()->getId())
            ->addAttributeToSelect('*');
        $collection->load();

        $page = is_null($this->getRequest()->getParam($this->getPageVarName())) ? 1 : $this->getRequest()->getParam($this->getPageVarName());
        $collection->setCurBxPage($page);
        $limit = $this->getLimitForProductCollection();
        $totalHitCount = $this->p13nHelper->getTotalHitCount($variant_index);
        $lastPage = ceil($totalHitCount /$limit);
        $collection->setLastBxPage($lastPage);
        $collection->setBxTotal($totalHitCount);

        return $collection;
    }

    protected function getLimitForProductCollection()
    {
        $limit = $this->getRequest()->getParam($this->getLimitVarName());
        if (!empty($limit) & is_numeric($limit)) {
            return $limit;
        }

        return $this->p13nHelper->getMagentoStoreConfigPageSize();
    }

}