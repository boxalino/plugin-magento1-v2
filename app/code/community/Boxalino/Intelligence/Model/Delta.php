<?php

/**
 * Class Boxalino_Intelligence_Model_Delta
 */
class Boxalino_Intelligence_Model_Delta extends Mage_Index_Model_Indexer_Abstract{

    /**
     * @return string
     */
    public function getName()
    {
        return "Boxalino Delta Data Export";
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return "Send latest data to Boxalino";
    }

    /**
     * 
     */
    protected function _construct()
    {
        $this->_init('intelligence/exporter_delta');
    }

    /**
     * @param Mage_Index_Model_Event $event
     */
    public function _registerEvent(Mage_Index_Model_Event $event)
    {
    }

    /**
     * @param Mage_Index_Model_Event $event
     */
    public function _processEvent(Mage_Index_Model_Event $event)
    {
    }
}