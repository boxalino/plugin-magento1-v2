<?php

/**
 * Class Boxalino_Intelligence_Block_Result
 */
class Boxalino_Intelligence_Block_Result extends Mage_CatalogSearch_Block_Result {

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
     * @var null
     */
    protected $correctedQuery = null;
    
    /**
     * Boxalino_Intelligence_Block_Result constructor
     */
    public function _construct() {
        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        $p13nHelper = $this->bxHelperData->getAdapter();
        try {
            if ($this->bxHelperData->isSearchEnabled()) {
                if ($this->hasSubPhrases()) {
                    if ($p13nHelper->areResultsCorrectedAndAlsoProvideSubPhrases()) {
                        $this->correctedQuery = $p13nHelper->getCorrectedQuery();
                    }
                    $this->queries =  $this->bxHelperData->getAdapter()->getSubPhrasesQueries();
                    if(count($this->queries) < 2) {
                        Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl('*/*', array('_query' => 'q=' . $this->queries[0])));
                    }
                }
            } else {
                $this->fallback = true;
            }
        } catch (\Exception $e){
            $this->fallback = true;
            Mage::logException($e);
        }
        parent::_construct();
    }

    /**
     * @param $index
     * @return string
     */
    public function getSearchQueryLink($index) {

        return Mage::getUrl('*/*', array('_query' => 'q=' . $this->queries[$index]));
    }

    /**
     * @return string
     */
    public function getCorrectedQueryLink() {
        return Mage::getUrl('*/*', array('_query' => 'q=' . $this->correctedQuery));
    }

    /**
     * @return int|null
     */
    public function hasSubPhrases() {

        if ($this->fallback) {
            return 0;
        }
        
        try {
            return Mage::helper('boxalino_intelligence')->getAdapter()->areThereSubPhrases();
        } catch (\Exception $e){
            Mage::logException($e);
        }
        return null;
    }

    /**
     * @param string $template
     * @return $this|Mage_Core_Block_Template
     */
    public function setTemplate($template) {

        if ($this->bxHelperData->isSearchEnabled()) {
            if ($this->hasSubPhrases()) {
                $this->_template = 'boxalino/catalogsearch/result.phtml';
                return $this;
            }
            if ($this->hasNoResult()) {
                $this->_template = 'boxalino/catalogsearch/noresults.phtml';
                return $this;
            }
        }
        return parent::setTemplate($template);
    }
    /**
     * Retrieve search result count
     *
     * @return string
     */
    public function getResultCount() {
        
        if ($this->fallback) {
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
    public function getHeaderText() {

        $bxHelperData = Mage::helper('boxalino_intelligence');
        if (!$this->fallback && $bxHelperData->getAdapter()->areResultsCorrected()) {
            return $this->__("Corrected search results for '%s'", $bxHelperData->getAdapter()->getCorrectedQuery());
        }
        return parent::getHeaderText();
    }
    
    /**
     * @return string
     */
    public function getProductListHtml() {

        if ($this->fallback) {
            return parent::getProductListHtml();
        }
        // Be careful: This will render toolbar in every subphrase result set if subphrases exist.
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
    public function getSubPhrasesResultText($index) {

        $query = $this->queries[$index];
        return $this->__("Search result for: '%s'", $query);
    }

    /**
     * @return bool
     */
    protected function hasNoResult() {

        $bxHelperData = Mage::helper('boxalino_intelligence');
        return (boolean) !count($bxHelperData->getAdapter()->getEntitiesIds());
    }

}
