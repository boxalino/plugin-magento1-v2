<?php

/**
 * Class Boxalino_Intelligence_Block_Product_List
 */
class Boxalino_Intelligence_Block_Product_List extends Mage_Catalog_Block_Product_List
{

    /**
     * @var int
     */
    public static $number = 0;

    /**
     * @var array
     */
    protected $_subPhraseCollections = [];

    /**
     * @var null
     */
    protected $bxRewriteAllowed = null;

    /**
     * @var null
     */
    protected $hasSubPhrases = null;

    /**
     * @var null | Boxalino_Intelligence_Helper_P13n_Adapter
     */
    protected $p13nAdapter = null;

    /**
     * @var bool
     */
    protected $showToolbar = false;
    
    /**
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    protected function _getProductCollection()
    {
        if(!$this->getBxRewriteAllowed())
        {
            return parent::_getProductCollection();
        }

        /** @var Boxalino_Intelligence_Helper_Data $bxHelperData */
        $bxHelperData = Mage::helper('boxalino_intelligence');
        $p13nHelper = $this->getP13nAdapter();
        $layer = $this->getLayer();
        if (null === $this->_productCollection && !(isset($this->_subPhraseCollections[self::$number]))) {
            try {
                if ($bxHelperData->isEnabledOnLayer($layer)) {
                   
                    if ($bxHelperData->layerCheck($layer, 'Mage_Catalog_Model_Layer')) {
                        // We skip boxalino processing if category is static cms block only.
                        if (Mage::registry('current_category')
                            && Mage::getBlockSingleton('catalog/category_view')->getCurrentCategory()
                            && Mage::getBlockSingleton('catalog/category_view')->isContentMode()
                        ) {
                            return parent::_getProductCollection();
                        }
                    }
                    $queries = array();
                    if ($this->hasSubPhrases) {
                        $queries = $p13nHelper->getSubPhrasesQueries();
                        $entity_ids = $p13nHelper->getSubPhraseEntitiesIds($queries[self::$number]);
                        $entity_ids = array_slice($entity_ids, 0, $this->getLimitForProductCollection());
                    } else {
                        $entity_ids = $p13nHelper->getEntitiesIds();
                    }

                    if (count($entity_ids) == 0) {
                        $entity_ids = array(0);
                    }
                    $this->_setupCollection($entity_ids, $queries);
                    // Soft cache subphrases to prevent multiple requests.
                    if ($this->hasSubPhrases) {
                        $this->_subPhraseCollections[self::$number] = $this->_productCollection;
                        $this->_productCollection = null;
                    }
                } else {
                    $this->_productCollection = parent::_getProductCollection();
                }
            } catch (\Exception $e) {
                Mage::logException($e);
                $bxHelperData->setFallback(true);
                $this->_productCollection = parent::_getProductCollection();
            }
        }
        return (isset($this->_subPhraseCollections[self::$number]))
            ? $this->_subPhraseCollections[self::$number] : $this->_productCollection;
    }

    /**
     * Setter if is a relaxation recommendations case
     */
    public function setHasSubphrases($value)
    {
        $this->hasSubPhrases = $value;
        return $this;
    }

    /**
     * @param $id
     * @param $field
     * @return string
     */
    public function getHitValueForField($id, $field) {
        $bxHelperData = Mage::helper('boxalino_intelligence');
        $p13nHelper = $this->getP13nAdapter();
        $value = '';
        if($bxHelperData->isEnabledOnLayer($this->getLayer())){
            $value = $p13nHelper->getHitVariable($id, $field);
        }
        return is_array($value) ? reset($value) : $value;
    }

    /**
     * @param $entity_ids
     * @throws Exception
     */
    protected function _setupCollection($entity_ids, $queries=array())
    {
        $helper = Mage::helper('boxalino_intelligence');
        $this->_productCollection = $helper->prepareProductCollection($entity_ids);
        $this->_productCollection
            ->setStore($this->getLayer()->getCurrentStore())
            ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addStoreFilter()
            ->addUrlRewrite();

        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($this->_productCollection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInSearchFilterToCollection($this->_productCollection);

        $this->_productCollection->setCurBxPage($this->getToolbarBlock()->getCurrentPage());
        $limit = $this->getLimitForProductCollection();

        try{
            if ($this->hasSubPhrases) {
                $totalHitCount = $this->getP13nAdapter()->getSubPhraseTotalHitCount($queries[self::$number]);
            } else {
                $totalHitCount = $this->getP13nAdapter()->getTotalHitCount();
            }
        }catch(\Exception $e){
            Mage::logException($e);
            throw $e;
        }
        $count = $totalHitCount == 0 ? $totalHitCount : count($entity_ids);
        $lastPage = ceil($totalHitCount / $limit);
        $this->_productCollection
            ->setLastBxPage($lastPage)
            ->setBxTotal($totalHitCount)
            ->setBxCount($count)
            ->load();
    }

    protected function _beforeToHtml()
    {
        if(!$this->getBxRewriteAllowed())
        {
            return parent::_beforeToHtml();
        }

        if($this->getBxRewriteAllowed() && Mage::helper('boxalino_intelligence')->isEnabledOnLayer($this->getLayer()))
        {
            $this->setHasSubphrases($this->getP13nAdapter()->areThereSubPhrases());
            parent::_beforeToHtml();
            if($this->hasSubPhrases) {
                $this->setChild('toolbar' . self::$number, $this->getToolbarBlock());
                $this->_getProductCollection()->load();
            }

            return $this;
        }

        if(!is_null(Mage::registry('current_category')) && Mage::helper('boxalino_intelligence')->isEnabledOnLayer($this->getLayer()) &&
                Mage::helper('boxalino_intelligence')->isNavigationSortEnabled())
        {
            $toolbar = $this->getToolbarBlock();
            $orders = $toolbar->getAvailableOrders();
            $orders = array_merge(['relevance' => $this->__('Relevance')], $orders);
            $toolbar->setAvailableOrders($orders);
            $toolbar->setDefaultOrder('relevance');
        }

        return parent::_beforeToHtml();
    }

    /**
     * overwritten to display correct number of products on toolbar
     *
     * @return string
     */
    public function getMode()
    {
        if($this->hasSubPhrases)
        {
            return $this->getChild('toolbar' . self::$number)->getCurrentMode();
        }

        return $this->getChild('toolbar')->getCurrentMode();
    }

    /**
     * overwritten to display correct number of products on toolbar
     *
     * @return string
     */
    public function getToolbarHtml()
    {
        if($this->hasSubPhrases)
        {
            if(Mage::helper('boxalino_intelligence')->getSubPhrasesToolbar())
            {
                return $this->getChildHtml('toolbar' . self::$number);
            }

            return '';
        }

        return parent::getToolbarHtml();
    }


    public function getP13nAdapter()
    {
        if(is_null($this->p13nAdapter))
        {
            $this->p13nAdapter = $p13nHelper = Mage::helper('boxalino_intelligence')->getAdapter();
        }

        return $this->p13nAdapter;
    }

    /**
     * Get toolbar limit to be used
     *
     * @return mixed|string
     * @throws Exception
     */
    protected function getLimitForProductCollection()
    {
        if(Mage::helper('boxalino_intelligence')->getSubPhrasesLimit())
        {
            return Mage::helper('boxalino_intelligence')->getSubPhrasesLimit();
        }

        return $this->getRequest()->getParam('limit') ? $this->getRequest()->getParam('limit') : $this->getToolbarBlock()->getLimit();
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

    public function getBxRewriteAllowed()
    {
        if(is_null($this->bxRewriteAllowed))
        {
            $this->bxRewriteAllowed = $this->checkIfPluginToBeUsed();
        }

        return $this->bxRewriteAllowed;
    }

}
