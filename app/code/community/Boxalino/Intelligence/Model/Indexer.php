<?php

/**
 * Class Boxalino_Intelligence_Model_Indexer
 */
class Boxalino_Intelligence_Model_Indexer extends Mage_Index_Model_Indexer_Abstract{

    /**
     * @return string
     */
    public function getName()
    {
        return "Boxalino Full Data Export";
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return "Send all data to Boxalino";
    }

    /**
     *
     */
    protected function _construct()
    {
        $this->_init('boxalino_intelligence/exporter_indexer');
    }

    /**
     * @param Mage_Index_Model_Event $event
     */
    protected function _registerEvent(Mage_Index_Model_Event $event)
    {
    }

    /**
     * @param Mage_Index_Model_Event $event
     */
    protected function _processEvent(Mage_Index_Model_Event $event)
    {
    }
}
