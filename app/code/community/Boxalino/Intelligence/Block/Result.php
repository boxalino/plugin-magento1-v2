<?php

/**
 * Class Boxalino_Intelligence_Block_Result
 */
class Boxalino_Intelligence_Block_Result extends Mage_CatalogSearch_Block_Result{

    /**
     * @var bool
     */
    protected $fallback = false;

    /**
     * @var null
     */
    protected $subPhrases = null;

    /**
     * @var array
     */
    protected $queries = array();

    /**
     * Boxalino_Intelligence_Block_Result constructor
     */
    public function _construct(){

        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        try{
            if( $this->bxHelperData->isSearchEnabled()){
                if($this->hasSubPhrases()){
                    $this->queries =  $this->bxHelperData->getAdapter()->getSubPhrasesQueries();
                }
            }else{
                $this->fallback = true;
            }
        }catch(\Exception $e){
            $this->fallback = true;
            Mage::logException($e);
        }
        parent::_construct();
    }

    /**
     * @param $index
     * @return string
     */
    public function getSearchQueryLink($index){

        return Mage::getUrl('*/*', array('_query' => 'q=' . $this->queries[$index]));
    }

    /**
     * @return int|null
     */
    public function hasSubPhrases(){

        if($this->fallback){
            return 0;
        }

        try{
            return Mage::helper('boxalino_intelligence')->getAdapter()->areThereSubPhrases();
        }catch(\Exception $e){
            Mage::logException($e);
        }
        return null;
    }

    /**
     * @param string $template
     * @return $this|Mage_Core_Block_Template
     */
    public function setTemplate($template){

        if(!$this->hasSubPhrases()){
            return parent::setTemplate($template);
        }
        $this->_template = 'boxalino/catalogsearch/result.phtml';
        return $this;
    }
    /**
     * Retrieve search result count
     *
     * @return string
     */
    public function getResultCount(){

        if($this->fallback){
            return parent::getResultCount();
        }
        if (!$this->getData('result_count')) {
            $bxHelperData = Mage::helper('boxalino_intelligence');
            $query = $this->_getQuery();
            $size = $this->hasSubPhrases() ?
                $bxHelperData->getAdapter()->getSubPhraseTotalHitCount(
                    $this->queries[Boxalino_Intelligence_Block_Product_List::$number]) :
                $bxHelperData->getAdapter()->getTotalHitCount();
            $this->setResultCount($size);
            $query->setNumResults($size);
        }
        return $this->getData('result_count');
    }

    /**
     * @return string
     */
    public function getHeaderText(){

        $bxHelperData = Mage::helper('boxalino_intelligence');
        if(!$this->fallback && $bxHelperData->getAdapter()->areResultsCorrected()){
            return $this->__("Corrected search results for '%s'", $bxHelperData->getAdapter()->getCorrectedQuery());
        }
        return parent::getHeaderText();
    }

    /**
     * @return string
     */
    public function getProductListHtml(){

        if($this->fallback){
            return parent::getProductListHtml();
        }
        // We do not want to render toolbar.
        if ($this->hasSubPhrases()) {
            $listBlock = $this->getChild('search_result_list');
            $listBlock->setRenderWithoutToolbar(true);
        }
        return $this->getChildHtml('search_result_list', false);
    }

    /**
     * @return int
     */
    public function getSubPhrasesResultCount() {

        return sizeof($this->queries);
    }

    /**
     * @param $index
     * @return string
     */
    public function getSubPhrasesResultText($index){

        $query = $this->queries[$index];
        return $this->__("Search result for: '%s'", $query);
    }
}
