<?php

class Boxalino_Intelligence_Block_Widget_ProductFinder extends Mage_Core_Block_Template implements Mage_Widget_Block_Interface
{
    protected function _toHtml(){
        return $this->getLayout()
            ->createBlock('boxalino_intelligence/productfinder', 'productfinder', ['choice_id' => $this->getData('choice_id')])
            ->setTemplate($this->getData('template'))->toHtml();
    }
}
?>