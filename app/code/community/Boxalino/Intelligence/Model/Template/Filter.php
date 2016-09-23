<?php

class Boxalino_Intelligence_Model_Template_Filter extends Mage_Widget_Model_Template_Filter{
    
    public function filter($value){
        Mage::helper('intelligence')->setCmsBlock($value);
        return parent::filter($value);
    }
}