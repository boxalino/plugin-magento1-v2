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

    protected $bxCount = 0;
    /**
     * @param $bxCurPage
     */
    public function setCurBxPage($bxCurPage) {
        $this->bxCurPage = $bxCurPage;
    }

    /**
     * @param $bxLastPage
     */
    public function setLastBxPage($bxLastPage) {
        $this->bxLastPage = $bxLastPage;
    }

    /**
     * @param $bxTotal
     */
    public function setBxTotal($bxTotal) {
        $this->bxTotal = $bxTotal;
    }

    /**
     * @return int
     */
    public function getSize() {
        return $this->bxTotal;
    }

    /**
     * @return int
     */
    public function count(){
        return $this->bxCount;
    }
    
    public function setBxCount($count){
        $this->bxCount = $count;
    }
    
    /**
     * @param int $displacement
     * @return int
     */
    public function getCurPage($displacement = 0) {
        return $this->bxCurPage + $displacement;
    }

    /**
     * @return int
     */
    public function getLastPageNumber() {
        return $this->bxLastPage;
    }

}