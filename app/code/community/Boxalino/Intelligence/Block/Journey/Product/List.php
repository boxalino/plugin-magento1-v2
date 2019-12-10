<?php

/**
 * Class Boxalino_Intelligence_Block_Journey_Product_ProductView
 */
class Boxalino_Intelligence_Block_Journey_Product_List extends Boxalino_Intelligence_Block_Journey_General
    implements Boxalino_Intelligence_Block_Journey_CPOJourney
{

    protected $bxResourceManager;

    public function _construct()
    {
        $this->bxResourceManager = Mage::helper('boxalino_intelligence/resourceManager');
        parent::_construct();
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

    public function prepareCollection()
    {
        $variant_index = $this->getVariantIndex();
        $collection = $this->bxResourceManager->getResource($variant_index, 'collection');
        if(is_null($collection)) {
            $collection = $this->createCollection($variant_index);
            $this->bxResourceManager->setResource($collection, $variant_index, 'collection');
        }
    }

    public function createCollection($variant_index)
    {
        $entity_ids = $this->p13nHelper->getEntitiesIds($variant_index);
        $collection = $this->bxHelperData->prepareProductCollection($entity_ids);
        $collection->setStore($this->getLayer()->getCurrentStore())
            ->addAttributeToSelect('*');
        $collection->load();

        $page = is_null($this->getRequest()->getParam('p')) ? 1 : $this->getRequest()->getParam('p');
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
        $limit = $this->getRequest()->getParam('limit');
        if (!empty($limit) & is_numeric($limit)) {
            return $limit;
        }

        return $this->p13nHelper->getMagentoStoreConfigPageSize();
    }

    public function checkVisualElementParam($visualElement, $key, $value)
    {
        $parameters = $visualElement['parameters'];
        foreach ($parameters as $parameter) {
            if($parameter['name'] == $key){
                if(in_array($value, $parameter['values'])) {
                    return true;
                }
            }
        }
        return false;
    }

}
