<?php

/**
 * Class Boxalino_Intelligence_Model_Layer_Filter_Attribute
 */
class Boxalino_Intelligence_Model_Layer_Filter_Item extends Mage_Catalog_Model_Layer_Filter_Item {

    public function getRemoveUrl() {

        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($bxHelperData->isEnabledOnLayer($this->getFilter()->getLayer())){

            $removeParams = $bxHelperData->getRemoveParams();
            $addParams = $bxHelperData->getSystemParams();
            $requestVar = $this->getFilter()->getRequestVar();
            $query = array($requestVar =>$this->getValue());
            foreach ($removeParams as $remove) {
                $query[$remove] = null;
            }
            foreach ($addParams as $param => $add) {
                    if($requestVar != $param){
                        $query = array_merge($query, [$param => implode($bxHelperData->getSeparator(), $add)]);
                    }
            }
            $params['_current']     = true;
            $params['_use_rewrite'] = true;
            $params['_query']       = $query;
            $params['_escape']      = true;
            return Mage::getUrl('*/*/*', $params);
        }
        return parent::getRemoveUrl();
    }

    public function getUrl(){

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
                    $add = [$param => implode($bxHelperData->getSeparator(), $values)];
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
}