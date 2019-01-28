<?php

/**
 * Class Boxalino_Intelligence_Model_Session
 */
class Boxalino_Intelligence_Model_Session extends Mage_Core_Model_Session_Abstract
{

    /**
     * Boxalino_Intelligence_Model_Session constructor.
     */
    public function __construct()
    {
        $this->init('checkout');
    }

    /**
     * @param $script
     */
    public function addScript($script)
    {
        if (!isset($this->_data['scipts']) || !is_array($this->_data['scipts'])) {
            $this->_data['scipts'] = array();
        }
        $this->_data['scipts'][] = $script;
    }

    /**
     * @return array
     */
    public function getScripts()
    {
        $scripts = array();
        if (isset($this->_data['scipts']) && is_array($this->_data['scipts'])) {
            $scripts = $this->_data['scipts'];
        }
        return $scripts;
    }

    /**
     * clear tracker scripts
     */
    public function clearScripts()
    {
        $this->_data['scipts'] = array();
    }
}
