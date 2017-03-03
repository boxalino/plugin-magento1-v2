<?php

/**
 * Class Boxalino_Intelligence_Helper_Autocomplete
 */
class Boxalino_Intelligence_Helper_Autocomplete{

    /**
     * @param $products
     * @return array
     */
    public function getListValues($products){
        $values = array();
        $show_price = Mage::getStoreConfig('bxSearch/autocomplete/show_price');
        foreach($products as $product){
            $value = array();
            $value['name'] = $product->getName();
            $value['url'] = $product->getProductUrl();
            if ($show_price) {
                $value['price'] = strip_tags($product->getFormatedPrice());
            }
            $value['image'] = $product->getThumbnailUrl();
            $values[$product->getId()] = $value;
        }
        return $values;
    }
    
}
