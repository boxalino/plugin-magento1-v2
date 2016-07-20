<?php

class Boxalino_Intelligence_IndexController extends Mage_Core_Controller_Front_Action{

    public function indexAction(){
        $adapter = new Boxalino_Intelligence_Helper_P13n_Adapter();
    }
}