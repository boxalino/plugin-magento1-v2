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
        foreach($products as $product){
            $value = array();
            $value['name'] = $product->getName();
            $value['url'] = $product->getProductUrl();
            // FIXME Because we do not need this for now we do not want to load it.
//            $value['price'] = strip_tags($product->getFormatedPrice());
            $value['image'] = $product->getThumbnailUrl();
            $values[$product->getId()] = $value;
        }
        return $values;
    }
    
}
