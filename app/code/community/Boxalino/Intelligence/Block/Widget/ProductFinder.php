<?php

class Boxalino_Intelligence_Block_Widget_ProductFinder extends Mage_Core_Block_Template implements Mage_Widget_Block_Interface
{
    protected function _toHtml(){
        return $this->getLayout()
            ->createBlock('boxalino_intelligence/productfinder', 'productfinder', ['finder_url' => $this->getData('finder_url')])
            ->setTemplate($this->getData('template'))->toHtml();
    }
}
?>