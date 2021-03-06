<?php

/**
 * Class Boxalino_Intelligence_Model_Template_Filter
 */
class Boxalino_Intelligence_Model_Template_Filter extends Mage_Widget_Model_Template_Filter{

    /**
     * @param string $value
     * @return string
     */
    public function filter($value)
    {
        if($this->checkIfPluginToBeUsed())
        {
            if(strpos($value,'boxalino_intelligence/recommendation')){
                Mage::helper('boxalino_intelligence')->setCmsBlock($value);
            }

        }

        return parent::filter($value);
    }

    /**
     * Before rewriting globally, check if the plugin is to be used
     * @return bool
     */
    public function checkIfPluginToBeUsed()
    {
        $boxalinoGlobalPluginStatus = Mage::helper('core')->isModuleOutputEnabled('Boxalino_Intelligence');
        if($boxalinoGlobalPluginStatus)
        {
            if(Mage::helper('boxalino_intelligence')->isPluginEnabled())
            {
                return true;
            }
        }

        return false;
    }
}
