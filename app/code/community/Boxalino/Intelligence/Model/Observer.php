<?php

class Boxalino_Intelligence_Model_Observer
{

    public function onProductAddedToCart(Varien_Event_Observer $event)
    {
        try {
            if(Mage::helper("boxalino_intelligence")->isNarrativeTrackerEnabled())
            {
                return;
            }
            $session = Mage::getSingleton('boxalino_intelligence/session');
            $script = Mage::helper('boxalino_intelligence')->reportAddToBasket(
                $event->getProduct()->getId(),
                $event->getQuoteItem()->getQty(),
                $event->getProduct()->getSpecialPrice() > 0 ? $event->getProduct()->getSpecialPrice() : $event->getProduct()->getPrice(),
                Mage::app()->getStore()->getCurrentCurrencyCode()
            );
            $session->addScript($script);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function onOrderSuccessPageView(Varien_Event_Observer $event)
    {
        try {
            $orderIds = $event->getData("order_ids");
            foreach($orderIds as $lastOrderId) {
                $order = Mage::getModel('sales/order')->load($lastOrderId);
                $orderData = $order->getData();
                $transactionId = $orderData['entity_id'];
                $products = array();
                $fullPrice = 0;
                foreach ($order->getAllItems() as $item) {
                    if ($item->getPrice() > 0) {
                        $products[] = array(
                            'product' => $item->getProduct()->getId(),
                            'quantity' => $item->getData('qty_ordered'),
                            'price' => $item->getPrice()
                        );
                        $fullPrice += $item->getPrice() * $item->getData('qty_ordered');
                    }
                }

                $script = Mage::helper('boxalino_intelligence')->reportPurchase($products, $transactionId, $fullPrice, Mage::app()->getStore()->getCurrentCurrencyCode());
                $session = Mage::getSingleton('boxalino_intelligence/session');
                $session->addScript($script);
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    
    public function onProductPageView(Varien_Event_Observer $event)
    {
        try {
            $productId = $event['product']->getId();
            $script = Mage::helper('boxalino_intelligence')->reportProductView($productId);

            $session = Mage::getSingleton('boxalino_intelligence/session');
            $session->addScript($script);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function onCategoryPageView(Varien_Event_Observer $event)
    {

        try {
            $categoryId = $event['category']['entity_id'];
            $script = Mage::helper('boxalino_intelligence')->reportCategoryView($categoryId);

            $session = Mage::getSingleton('boxalino_intelligence/session');
            $session->addScript($script);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function onLogin(Varien_Event_Observer $event)
    {
        try {
            $userId = $event['customer']['entity_id'];
            $script = Mage::helper('boxalino_intelligence')->reportLogin($userId);

            $session = Mage::getSingleton('boxalino_intelligence/session');
            $session->addScript($script);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}
