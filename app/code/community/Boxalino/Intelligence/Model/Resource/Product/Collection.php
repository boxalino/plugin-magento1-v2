<?php

class Boxalino_Intelligence_Model_Resource_Product_Collection extends Mage_Catalog_Model_Resource_Product_Collection{
    /**
     * @var int
     */
    protected $bxCurPage = 0;

    /**
     * @var int
     */
    protected $bxLastPage = 0;

    /**
     * @var int
     */
    protected $bxTotal = 0;

    /**
     * @var int
     */
    protected $bxCount = 0;

    /**
     * @var bool
     */
    protected $fallback = false;

    /**
     * 
     */
    public function _construct(){

        $bxHelperData = Mage::helper('boxalino_intelligence');
        $this->fallback = $bxHelperData->getFallback();
        $layer = $this->getLayer();
        if(Mage::app()->getStore()->isAdmin() || !$bxHelperData->isEnabledOnLayer($layer)){
            $this->fallback = true;
        }
        parent::_construct();
    }

    /**
     * @return Mage_Core_Model_Abstract|mixed
     */
    private function getLayer()
    {
        $layer = Mage::registry('current_layer');
        if ($layer) {
            return $layer;
        }
        return Mage::getSingleton('catalog/layer');
    }

    /**
     * @param $bxCurPage
     */
    public function setCurBxPage($bxCurPage) {
        
        $this->bxCurPage = $bxCurPage;
        return $this;
    }

    /**
     * @param $bxLastPage
     */
    public function setLastBxPage($bxLastPage) {
        
        $this->bxLastPage = $bxLastPage;
        return $this;
    }

    /**
     * @param $bxTotal
     */
    public function setBxTotal($bxTotal) {
        
        $this->bxTotal = $bxTotal;
        return $this;
    }

    /**
     * @return int
     */
    public function getSize() {

        if($this->fallback){
            return parent::getSize();
        }
        return $this->bxTotal;
    }

    /**
     * @return int
     */
    public function count(){

        if($this->fallback){
            return parent::count();
        }
        return $this->bxCount;
    }

    /**
     * @param $count
     * @return $this
     */
    public function setBxCount($count){

        $this->bxCount = $count;
        return $this;
    }

    /**
     * @param int $displacement
     * @return int
     */
    public function getCurPage($displacement = 0) {

        if($this->fallback){
            return parent::getCurPage();
        }
        return $this->bxCurPage + $displacement;
    }

    /**
     * @return int
     */
    public function getLastPageNumber() {

        if($this->fallback){
            return parent::getLastPageNumber();
        }
        return $this->bxLastPage;
    }

}
