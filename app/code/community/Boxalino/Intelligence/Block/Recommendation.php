<?php

/**
 * Class Boxalino_Intelligence_Block_Recommendation
 */
class Boxalino_Intelligence_Block_Recommendation extends Mage_Catalog_Block_Product_Abstract
{
    /**
     * @var null
     */
    protected $bxRewriteAllowed = null;

    /**
     * @var
     */
    protected $_itemCollection = [];

    /**
     * @var
     */
    protected $bxHelperData;

    /*
     *
     */
    public function _construct()
    {
        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        if($this->getBxRewriteAllowed())
        {
            $this->_data = array();
            if($this->bxHelperData->isSetup()){
                $cmsBlock = $this->bxHelperData->getCmsBlock();
                if($cmsBlock || sizeof($this->_data) == 0){
                    $recommendationBlocks = $this->getCmsRecommendationBlocks($cmsBlock);
                    $this->prepareRecommendations($recommendationBlocks, $this->getReturnFields());
                    $this->bxHelperData->setSetup(false);
                }
            }
        }

        return false;
    }

    public function init($widget, $scenario)
    {
        if (is_null($this->getData('widget'))) {
            $this->setData('widget', $widget);
        }
        if (is_null($this->getData('scenario'))) {
            $this->setData('scenario', $scenario);
        }
    }

    public function getReturnFields()
    {
        return array();
    }

    /**
     * @param $content
     * @return array
     */
    protected function getCmsRecommendationBlocks($content)
    {
        $results = array();
        $recommendations = array();
        if(is_array($content))
        {
            return $recommendations;
        }
        preg_match_all("/\{\{(.*?)\}\}/",$content, $results);
        if(isset($results[1])){
            foreach($results[1] as $result){
                if(strpos($result,'boxalino_intelligence/recommendation')){
                    preg_match_all("/[-^\s](.*?)\=\"(.*?)\"/",$result, $sectionResults);
                    $result_holder = array();
                    foreach($sectionResults[1] as $index => $sectionResult){
                        $result_holder[$sectionResult] = $sectionResults[2][$index];
                    }
                    $recommendations[] = $result_holder;
                }
            }
        }
        return $recommendations;
    }

    /**
     * @param $content
     * @return array
     */
    protected function getLayoutRecommendationBlocks($content)
    {
        $results = array();
        $recommendations = array();
        preg_match_all("/\<block type=\"boxalino_intelligence\/recommendation\"(.*?)\<\/block\>/" ,$content,$results);
        if(isset($results[0]))
        {
            foreach ($results[0] as $block)
            {
                preg_match_all("/\<name\>(.*?)\<\/name\>\<value\>(.*?)\<\/value\>/", $block, $key_value);
                $data = [];
                if(isset($key_value[1]))
                {
                    foreach ($key_value[1] as $index => $key)
                    {
                        $value = isset($key_value[2][$index]) ? $key_value[2][$index] : '';
                        $data[trim(strtolower($key))] = $value;
                    }
                    $recommendations[] = $data;
                }
            }
        }
        return $recommendations;
    }

    /**
     * @param array $recommendations
     * @return null
     */
    protected function prepareRecommendations($recommendations = array(), $returnFields = array())
    {
        if($recommendations && is_array($recommendations))
        {
            foreach($recommendations as $index => $widget)
            {
                try{
                    $recommendation = array();
                    $widgetConfig = $this->bxHelperData->getWidgetConfig($widget['widget']);

                }catch(\Exception $e){
                    Mage::logException($e);
                    $widgetConfig = array();
                }

                try{
                    $recommendation['scenario'] = isset($widget['scenario']) ? $widget['scenario'] :
                        $widgetConfig['scenario'];

                    $recommendation['min'] = isset($widget['min']) ? $widget['min'] : $widgetConfig['min'];
                    $recommendation['max'] = isset($widget['max']) ? $widget['max'] : $widgetConfig['max'];

                    if (isset($widget['context'])) {
                        $recommendation['context'] = explode(',', str_replace(' ', '', $widget['context']));
                    } else {
                        $scenario = isset($widget['scenario']) ? $widget['scenario'] : $widgetConfig['scenario'];
                        $recommendation['context']  = $this->getWidgetContext($scenario);
                    }
                    $this->bxHelperData->getAdapter()->getRecommendation(
                        $widget['widget'],
                        $recommendation['context'],
                        $recommendation['scenario'],
                        $recommendation['min'] ,
                        $recommendation['max'],
                        false,
                        $returnFields
                    );
                }catch(\Exception $e){
                    Mage::logException($e);
                }
            }
        }
        return null;
    }

    /**
     * @param $scenario
     * @return array|mixed
     */
    protected function getWidgetContext($scenario)
    {
        $context = array();
        switch($scenario)
        {
            case 'category':
                if(Mage::registry('category') !== null){
                    $context = Mage::registry('current_category');
                }
                break;
            case 'blog':
            case 'product':
                if(Mage::registry('product') !== null){
                    $context = Mage::registry('product');
                }
                break;
            case 'basket':
                $order = Mage::registry('last_order');
                if($order == null){
                    $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
                    $order = Mage::getModel('sales/order')->load($orderId);
                    Mage::register('last_order', $order);
                }
                foreach ($order->getAllItems() as $item) {
                    if ($item->getPrice() > 0) {
                        $product = $item->getProduct();
                        if ($product) {
                            $context[] = $product;
                        }
                    }
                }
                break;
            default:
                break;
        }
        return $context;
    }

    /**
     * @return mixed|string
     */
    public function getRecommendationTitle($widget)
    {
        $title = $this->bxHelperData->getAdapter()->getSearchResultTitle($widget);
        return isset($title) ? $title : $this->__('Recommendation');
    }

    /**
     * @return mixed
     */
    public function getItems()
    {
        return $this->_getLoadedProductCollection();
    }

    /**
     * @return mixed
     */
    public function _getLoadedProductCollection()
    {
        return $this->_getProductCollection();
    }

    /**
     * @return mixed
     */
    protected function _getProductCollection()
    {
        if(!$this->_itemCollection && $this->bxHelperData->isPluginEnabled()){
            $this->_prepareData();
        }
        return $this->_itemCollection;
    }

    /**
     * @return $this
     */
    protected function _prepareData()
    {
        $widget  = $this->getData('widget');
        $context = $this->getData('context');
        $scenario = $this->getData('scenario');
        if(($scenario == 'product')) {
            if(is_null($context))
            {
                $context = $this->getWidgetContext($scenario);
            } else {
                $context = Mage::getModel('catalog/product')->load($context);
            }
        }

        if($this->getData('widget') == 'noresults') {
            $config = Mage::getStoreConfig('bxSearch/noresults');
            $widget = $config['widget'];
            $this->bxHelperData->getAdapter()->flushResponses();
            $this->bxHelperData->getAdapter()->getRecommendation(
                $widget,
                $context,
                $scenario,
                $config['min'],
                $config['max'],
                false
            );
        }

        $entity_ids = array();
        try{
            $config = $this->bxHelperData->getWidgetConfig($widget);
            $entity_ids = $this->bxHelperData->getAdapter()->getRecommendation(
                $widget,
                $context,
                $scenario,
                (isset($config['min'])&&!is_null($config['min'])) ? $config['min'] : $this->getData("min"),
                (isset($config['max'])&&!is_null($config['max'])) ? $config['max'] : $this->getData("max")
            );
            $this->setData('title', $this->bxHelperData->getAdapter()->getSearchResultTitle($widget, $this->getData('title')));
        }catch(\Exception $e){
            Mage::logException($e);
        }

        if ((count($entity_ids) == 0)) {
            $entity_ids = array(0);
        }

        $this->_itemCollection = $this->_productCollection = $this->bxHelperData->prepareProductCollection($entity_ids);
        $this->_itemCollection
            ->addAttributeToSelect('*')
            ->addMinimalPrice()
            ->addFinalPrice();
        $this->_itemCollection->setBxCount(count($entity_ids));
        $this->_itemCollection->load();
        return $this;
    }

    /**
     * @return mixed
     */
    public function isPluginEnabled()
    {
        return $this->getBxRewriteAllowed();
    }

    /**
     * @return Mage_Core_Block_Abstract
     */
    protected function _beforeToHtml()
    {
        if($this->isPluginEnabled()) {
            if ($this->bxHelperData->isSetup()) {
                $recommendations = $this->getLayoutRecommendationBlocks($this->getLayout()->getXmlString());
                if ($recommendations) {
                    $this->prepareRecommendations($recommendations);
                }
                $this->bxHelperData->setSetup(false);
            }
            $this->_prepareData();
            return parent::_beforeToHtml();
        }

        return false;
    }

    public function bxRecommendationTitle()
    {
        return $this->getData('title');
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
