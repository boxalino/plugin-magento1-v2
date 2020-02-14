<?php

/**
 * Class Boxalino_Intelligence_Block_Journey_Product_ProductView
 */
class Boxalino_Intelligence_Block_Journey_Product_View extends Mage_Catalog_Block_Product_View
    implements Boxalino_Intelligence_Block_Journey_CPOJourney
{

    protected $renderer;
    protected $bxHelperData;
    protected $p13nHelper;
    protected $bxResourceManager;

    public function _construct()
    {
        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        $this->bxResourceManager = Mage::helper('boxalino_intelligence/resourceManager');
        $this->p13nHelper = $this->bxHelperData->getAdapter();
        $this->renderer = Mage::getSingleton('boxalino_intelligence/visualElement_renderer');
        parent::_construct();
    }


    public function getSubRenderings()
    {
        return $this->renderer->getSubRenderingsByVisualElement($this->getData('bxVisualElement'));
    }

    public function renderVisualElement($element, $additional_parameter = null)
    {
        return $this->renderer->createVisualElement($element, $additional_parameter)->toHtml();
    }

    public function getLocalizedValue($values) {
        return $this->renderer->getLocalizedValue($values);
    }

    public function getElementIndex() {
        return $this->getData('bx_index');
    }

    public function getProduct() {
        return $this->bxGetProduct();
    }

    public function bxGetProduct() {
        $visualElement = $this->getData('bxVisualElement');

        $variant_index = 0;
        $index = $this->getElementIndex();
        foreach ($visualElement['parameters'] as $parameter) {
            if($parameter['name'] == 'variant') {
                $variant_index = reset($parameter['values']);
            } else if($parameter['name'] == 'index') {
                $index = reset($parameter['values']);
            }
        }

        $ids = $this->p13nHelper->getEntitiesIds($variant_index);
        $entity_id = $ids[$index];
        $collection = $this->bxResourceManager->getResource($variant_index, 'collection');
        if(!is_null($collection)) {
            foreach ($collection as $product) {
                if($product->getId() == $entity_id){
                    return $product;
                }
            }
        }

        $product = $this->bxResourceManager->getResource($entity_id, 'product');
        if(is_null($product)) {
            $product = $this->loadProduct($entity_id);
            $this->bxResourceManager->setResource($product, $entity_id, 'product');
        }
        return $product;
    }

    protected function loadProduct($product_id) {
        $product = Mage::getModel('catalog/product')->load($product_id);
        return $product;
    }

    public function getRequestUuid()
    {
        return $this->p13nHelper->getRequestUuid();
    }

    public function getRequestGroupBy()
    {
        return $this->p13nHelper->getRequestGroupBy();
    }
}
