<?php
require_once "Mage/CatalogSearch/controllers/ResultController.php";

class Boxalino_Intelligence_ResultController extends Mage_CatalogSearch_ResultController
{
    public function indexAction()
    {
        Mage::helper('intelligence')->getAdapter()->areResultsCorrected();
        return parent::indexAction();
    }
}