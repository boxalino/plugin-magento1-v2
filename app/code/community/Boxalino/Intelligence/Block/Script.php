<?php

class Boxalino_Intelligence_Block_Script extends Mage_Core_Block_Template
{
    
    public function isEnabled()
    {
        return Mage::helper('intelligence')->isTrackerEnabled();
    }

    public function getScripts()
    {
        $html = '';
        $session = Mage::getSingleton('Boxalino_Intelligence_Model_Session');
        $scripts = $session->getScripts(false);

        foreach ($scripts as $script) {
            $html .= $script;
        }
        $session->clearScripts();

        return $html;
    }

    public function isSearch()
    {
        $current = $this->getRequest()->getRouteName() . '/' . $this->getRequest()->getControllerName();
        return $current == 'catalogsearch/result';
    }
}
