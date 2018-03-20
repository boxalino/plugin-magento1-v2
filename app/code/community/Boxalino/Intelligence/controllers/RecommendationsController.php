<?php

class Boxalino_Intelligence_RecommendationsController extends Mage_Core_Controller_Front_Action
{

    public function indexAction(){

      $this->loadLayout();
      $block = $this->getLayout()->createBlock('Boxalino_Intelligence_Block_Product_List_Parametrized');
      $format = $block->getFormat();

      if ($format == 'json') {
        echo $this->getLayout()->createBlock('Boxalino_Intelligence_Block_Product_List_Parametrized')->setTemplate('boxalino\recommendation_json.phtml')->toHtml();
      }
      if ($format == 'html') {
        echo $this->getLayout()->createBlock('Boxalino_Intelligence_Block_Product_List_Parametrized')->setTemplate('boxalino\recommendation.phtml')->toHtml();
      }

    }

}
