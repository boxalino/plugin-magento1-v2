<?php

class Boxalino_Intelligence_Model_Observer{

    public function onProductAddedToCart(Varien_Event_Observer $event)
    {

        try {
            $session = Mage::getSingleton('Boxalino_Intelligence_Model_Session');
            $script = Mage::helper('intelligence')->reportAddToBasket(
                $event->getProduct()->getId(),
                $event->getQuoteItem()->getQty(),
                $event->getProduct()->getSpecialPrice() > 0 ? $event->getProduct()->getSpecialPrice() : $event->getProduct()->getPrice(),
                Mage::app()->getStore()->getCurrentCurrencyCode()
            );
            $session->addScript($script);
        } catch (Exception $e) {
            if (Mage::helper('intelligence')->isDebugEnabled()) {
                echo($e);
                exit;
            }
            
        }
    }

    public function onOrderSuccessPageView(Varien_Event_Observer $event)
    {
        try {
            $orders = Mage::getModel('sales/order')->getCollection()
                ->setOrder('entity_id', 'DESC')
                ->setPageSize(1)
                ->setCurPage(1);
            $order = $orders->getFirstItem();
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
            $script = Mage::helper('intelligence')->reportPurchase($products, $transactionId, $fullPrice, Mage::app()->getStore()->getCurrentCurrencyCode());

            $session = Mage::getSingleton('Boxalino_Intelligence_Model_Session');
            $session->addScript($script);
        } catch (Exception $e) {
            if (Mage::helper('intelligence')->isDebugEnabled()) {
                echo($e);
                exit;
            }
        }
    }

    
    public function onProductPageView(Varien_Event_Observer $event)
    {
        try {
            $productId = $event['product']->getId();
            $script = Mage::helper('intelligence')->reportProductView($productId);

            $session = Mage::getSingleton('Boxalino_Intelligence_Model_Session');
            $session->addScript($script);
        } catch (Exception $e) {
            if (Mage::helper('intelligence')->isDebugEnabled()) {
                echo($e);
                exit;
            }
        }
    }

    public function onCategoryPageView(Varien_Event_Observer $event)
    {

        try {
            $categoryId = $event['category']['entity_id'];
            $script = Mage::helper('intelligence')->reportCategoryView($categoryId);

            $session = Mage::getSingleton('Boxalino_Intelligence_Model_Session');
            $session->addScript($script);
        } catch (Exception $e) {
            if (Mage::helper('intelligence')->isDebugEnabled()) {
                echo($e);
                exit;
            }
        }
    }

    public function onLogin(Varien_Event_Observer $event)
    {
        try {
            $userId = $event['customer']['entity_id'];
            $script = Mage::helper('intelligence')->reportLogin($userId);

            $session = Mage::getSingleton('Boxalino_Intelligence_Model_Session');
            $session->addScript($script);
        } catch (Exception $e) {
            if (Mage::helper('intelligence')->isDebugEnabled()) {
                echo($e);
                exit;
            }
        }
    }
}