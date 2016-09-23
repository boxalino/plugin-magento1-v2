<?php

class Boxalino_Intelligence_Helper_Autocomplete{
    
    public function getListValues($products){
        $values = array();
        foreach($products as $product){
            $value = array();
            $value['name'] = $product->getName();
            $value['url'] = $product->getProductUrl();
            $value['price'] = strip_tags($product->getFormatedPrice());
            $value['image'] = $product->getThumbnailUrl();
            $values[$product->getId()] = $value;
        }
        return $values;
    }
    
}