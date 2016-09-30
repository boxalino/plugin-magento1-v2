<?php

/**
 * Class Boxalino_Intelligence_Model_Template_Filter
 */
class Boxalino_Intelligence_Model_Template_Filter extends Mage_Widget_Model_Template_Filter{

    /**
     * @param string $value
     * @return string
     */
    public function filter($value){
        
        if(strpos($value,'boxalino/recommendation')){
            Mage::helper('intelligence')->setCmsBlock($value);
        }
        return parent::filter($value);
    }
}