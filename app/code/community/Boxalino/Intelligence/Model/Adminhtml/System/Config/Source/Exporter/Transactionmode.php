<?php

/**
 * Class Boxalino_Intelligence_Model_Adminhtml_System_Config_Source_Exporter_Transactionmode
 *
 * Exporter transaction modes options
 */
class Boxalino_Intelligence_Model_Adminhtml_System_Config_Source_Exporter_Transactionmode
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(){
        return array(
            array('value' => 1, 'label'=> 'Full'),
            array('value' => 0, 'label'=>'Incremental')
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray(){
        return array(
            0 => 'Incremental',
            1 => 'Full'
        );
    }
}
