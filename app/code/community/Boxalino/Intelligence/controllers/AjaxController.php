<?php
require_once "Mage/CatalogSearch/controllers/AjaxController.php";
class Boxalino_Intelligence_AjaxController extends Mage_CatalogSearch_AjaxController{

    public function suggestAction()
    {
        if (!$this->getRequest()->getParam('q', false)) {
            $this->getResponse()->setRedirect(Mage::getSingleton('core/url')->getBaseUrl());
        }

        $this->getResponse()->setBody($this->getLayout()->createBlock('catalogsearch/autocomplete')->toHtml());
    }
}
