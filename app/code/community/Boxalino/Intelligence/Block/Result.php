<?php

/**
 * Class Boxalino_Intelligence_Block_Result
 */
class Boxalino_Intelligence_Block_Result extends Mage_CatalogSearch_Block_Result
{

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
     * @var null
     */
    protected $bxRewriteAllowed = null;

    /**
     * @var Mage_Core_Helper_Abstract
     */
    protected $bxHelperData;

    /**
     * @var Boxalino_Intelligence_Helper_P13n_Adapter
     */
    protected $adapter = null;

    /**
     * Boxalino_Intelligence_Block_Result constructor
     */
    public function __construct() {
        if($this->getBxRewriteAllowed()) {
            $this->bxHelperData = Mage::helper('boxalino_intelligence');
            try {
                if ($this->bxHelperData->isSearchEnabled()) {
                    $this->adapter = $this->bxHelperData->getAdapter();
                    if ($this->hasSubPhrases()) {
                        if ($this->adapter->areResultsCorrectedAndAlsoProvideSubPhrases()) {
                            $this->correctedQuery = $this->adapter->getCorrectedQuery();
                        }
                        $this->queries = $this->adapter->getSubPhrasesQueries();
                    }
                } else {
                    $this->fallback = true;
                }
            } catch (\Exception $e) {
                $this->bxHelperData->setFallback(true);
                $this->fallback = true;
                Mage::logException($e);
            }
        }

        return parent::__construct();
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
    public function hasSubPhrases()
    {
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
    public function setTemplate($template)
    {
        if(!$this->getBxRewriteAllowed())
        {
            return parent::setTemplate($template);
        }

        if ($this->bxHelperData->isSearchEnabled()) {
            if ($this->hasSubPhrases()) {
                $this->_template = 'boxalino/catalogsearch/result.phtml';
                return $this;
            }
            if ($this->bxHelperData->isBlogSearchEnabled() && $this->getBlogTotalHitCount()>0) {
                $this->_template = 'boxalino/catalogsearch/resultNoSubphrases.phtml';
                return $this;
            }
            if ($this->bxHelperData->isNoResultsEnabled() && $this->hasNoResult()) {
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

    public function getBlogTotalHitCount(){
      $bxHelperData = Mage::helper('boxalino_intelligence');
        return $bxHelperData->getAdapter()->getBlogTotalHitCount();
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        $bxHelperData = Mage::helper('boxalino_intelligence');
        if (!$this->fallback && $bxHelperData->getAdapter()->areResultsCorrected()) {
            return $this->__("Corrected search results for '%s'", $bxHelperData->getAdapter()->getCorrectedQuery());
        }
        return parent::getHeaderText();
    }

    /**
     * @return string
     */
    public function getProductListHtml()
    {
        if ($this->fallback) {
            return parent::getProductListHtml();
        }
        // Be careful: This will render toolbar in every subphrase result set if subphrases exist.
        return $this->getChildHtml('search_result_list', false);
    }

    /**
     * @return int
     */
    public function getSubPhrasesResultCount()
    {
        return sizeof($this->queries);
    }

    /**
     * @param $index
     * @return string
     */
    public function getSubPhrasesResultText($index)
    {
        $query = $this->queries[$index];
        return $this->__("Search result for: '%s'", $query);
    }

    /**
     * @return bool
     */
    protected function hasNoResult()
    {
        try{
            $bxHelperData = Mage::helper('boxalino_intelligence');
            return (boolean) !count($bxHelperData->getAdapter()->getEntitiesIds());
        } catch(\Exception $e) {
            $this->bxHelperData->setFallback(true);
            $this->fallback = true;
            Mage::logException($e);
        }
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

        $this->fallback = true;
        return false;
    }

    public function getBxRewriteAllowed()
    {
        if(is_null($this->bxRewriteAllowed))
        {
            $this->bxRewriteAllowed = $this->checkIfPluginToBeUsed();
        }

        return $this->bxRewriteAllowed;
    }

}
