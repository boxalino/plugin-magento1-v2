<?php

class Boxalino_Intelligence_Model_Resource_Product_Collection extends Mage_Catalog_Model_Resource_Product_Collection{
    /**
     * @var int
     */
    protected $bxCurPage = null;

    /**
     * @var int
     */
    protected $bxLastPage = null;

    /**
     * @var int
     */
    protected $bxTotal = null;

    /**
     * @var int
     */
    protected $bxCount = null;


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
        if(is_null($this->bxTotal)){
            return parent::getSize();
        }
        return $this->bxTotal;
    }

    /**
     * @return int
     */
    public function count(){

        if(is_null($this->bxCount)){
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

        if(is_null($this->bxCurPage)){
            return parent::getCurPage();
        }
        return $this->bxCurPage + $displacement;
    }

    /**
     * @return int
     */
    public function getLastPageNumber() {

        if(is_null($this->bxLastPage)){
            return parent::getLastPageNumber();
        }
        return $this->bxLastPage;
    }

}
