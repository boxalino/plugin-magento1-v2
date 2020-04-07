<?php

/**
 * Class Boxalino_Intelligence_Model_Session
 */
class Boxalino_Intelligence_Model_Session extends Mage_Core_Model_Session_Abstract
{

    protected $avoidDuplicateEvents = ["categoryView", "productView"];

    /**
     * Boxalino_Intelligence_Model_Session constructor.
     */
    public function __construct()
    {
        $this->init('checkout');
    }

    /**
     * Update to ensure that some events are not saved multiple times in the session
     * (ex: in case of events re-use within client project)
     *
     * @param $script
     * @param null | string $type
     */
    public function addScript($script, $type = null)
    {
        if (!isset($this->_data['scripts']) || !is_array($this->_data['scripts'])) {
            $this->_data['scripts'] = [];
        }
        if(!is_null($type))
        {
            if(in_array($type, $this->avoidDuplicateEvents))
            {
                if(isset($this->_data['scripts'][$type]))
                {
                    return;
                }

                $this->_data['scripts'][$type] = $type;
            }
        }

        $this->_data['scripts'][] = $script;
    }

    /**
     * @return array
     */
    public function getScripts()
    {
        $scripts = [];
        if (isset($this->_data['scripts']) && is_array($this->_data['scripts']))
        {
            foreach($this->avoidDuplicateEvents as $event)
            {
                if(isset($this->_data['scripts'][$event]))
                {
                    unset($this->_data['scripts'][$event]);
                }
            }

            $scripts = $this->_data['scripts'];
        }

        return $scripts;
    }

    /**
     * clear tracker scripts
     */
    public function clearScripts()
    {
        $this->_data['scripts'] = [];
    }
}
