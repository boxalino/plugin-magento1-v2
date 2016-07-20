<?php

class Boxalino_Intelligence_Model_Indexer extends Mage_Index_Model_Indexer_Abstract{
    
    public function getName()
    {
        return "Boxalino Full Data Export";
    }

    public function getDescription()
    {
        return "Send all data to Boxalino";
    }

    protected function _construct()
    {
        $this->_init('intelligence/exporter_indexer');
    }

    protected function _registerEvent(Mage_Index_Model_Event $event)
    {
    }

    protected function _processEvent(Mage_Index_Model_Event $event)
    {
    }
}