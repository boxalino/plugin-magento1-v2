<?php
class Boxalino_Intelligence_Block_Layer_State extends Mage_Catalog_Block_Layer_State{
    
    protected $bxFilters;
    
    public function getActiveFilters(){
        
        $bxHelperData = Mage::helper('intelligence');
        if ($bxHelperData->isFilterLayoutEnabled($this->getLayer() instanceof Mage_Catalog_Model_Layer)) {
            return $this->getBxFilters();
        }
        return parent::getActiveFilters();
    }
}