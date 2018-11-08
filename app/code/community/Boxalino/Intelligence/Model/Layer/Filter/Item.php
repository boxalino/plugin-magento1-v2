<?php

/**
 * Class Boxalino_Intelligence_Model_Layer_Filter_Attribute
 */
class Boxalino_Intelligence_Model_Layer_Filter_Item extends Mage_Catalog_Model_Layer_Filter_Item
{

    /**
     * Check the module is enabled on store before main action
     * @return string
     */
    public function getRemoveUrl()
    {
        if($this->checkIfPluginToBeUsed())
        {
            return $this->getBxRemoveUrl();
        }

        return parent::getRemoveUrl();
    }

    protected function getBxRemoveUrl()
    {
        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($bxHelperData->isEnabledOnLayer($this->getFilter()->getLayer())){

            $removeParams = $bxHelperData->getRemoveParams();
            $addParams = $bxHelperData->getSystemParams();
            $requestVar = $this->getFilter()->getRequestVar();
            $query = array($requestVar =>$this->getValue());
            if($this->getType() == 'changeQuery') {
                $addParams = array_merge($addParams, $bxHelperData->getIncludedParams());
                $addParams['bx_cq'] = [$bxHelperData->getAdapter()->getResponse()->getCorrectedQuery()];
            }

            foreach ($removeParams as $remove) {
                $query[$remove] = null;
            }
            foreach ($addParams as $param => $add) {
                if($requestVar != $param){
                    $query = array_merge($query, [$param => is_array($add) ? implode($bxHelperData->getSeparator(), $add) : $add]);
                }
            }
            $params['_current']     = true;
            $params['_use_rewrite'] = true;
            $params['_query']       = $query;
            $params['_escape']      = true;
            return Mage::getUrl('*/*/*', $params);
        }
    }

    /**
     * Check the module is enabled on store before main action
     * @return string
     */
    public function getUrl()
    {
        if($this->checkIfPluginToBeUsed())
        {
            return $this->getBxUrl();
        }

        return parent::getUrl();
    }

    protected function getBxUrl()
    {
        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($bxHelperData->isEnabledOnLayer($this->getFilter()->getLayer())){

            $removeParams = $bxHelperData->getRemoveParams();
            $addParams = $bxHelperData->getSystemParams();
            $requestVar =  $this->getFilter()->getRequestVar();
            $query = array(
                $requestVar=>$this->getValue(),
                Mage::getBlockSingleton('page/html_pager')->getPageVarName() => null // exclude current page from urls
            );
            foreach ($addParams as $param => $values) {
                if($requestVar != $param) {
                    $add = [$param => is_array($values) ? implode($bxHelperData->getSeparator(), $values) : $values];
                    $query = array_merge($query, $add);
                }
            }
            foreach ($removeParams as $remove) {
                $query[$remove] = null;
            }
            return Mage::getUrl('*/*/*', array('_current'=>true, '_use_rewrite'=>true, '_query'=>$query));
        }
        return parent::getUrl();
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