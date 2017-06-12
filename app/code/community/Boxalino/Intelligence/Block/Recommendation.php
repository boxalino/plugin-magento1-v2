<?php

/**
 * Class Boxalino_Intelligence_Block_Recommendation
 */
class Boxalino_Intelligence_Block_Recommendation extends Mage_Catalog_Block_Product_Abstract{

    /**
     * @var
     */
    protected $_itemCollection;

    /**
     * @var
     */
    protected $bxHelperData;

    /*
     * 
     */
    public function _construct(){

        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        if($this->bxHelperData->isSetup() && $this->bxHelperData->isPluginEnabled()){
            $cmsBlock = $this->bxHelperData->getCmsBlock();
            if($cmsBlock){
                $recommendationBlocks = $this->getCmsRecommendationBlocks($cmsBlock);
                $this->prepareRecommendations($recommendationBlocks);
                $this->bxHelperData->setSetup(false);
            }
        }
        parent::_construct();
    }

    /**
     * @param $content
     * @return array
     */
    protected function getCmsRecommendationBlocks($content){

        $results = array();
        $recommendations = array();
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
    protected function getLayoutRecommendationBlocks($content){

        $results = array();
        $recommendations = array();
        preg_match_all("/\<block type=\"boxalino_intelligence\/recommendation\"(.*?)\<\/block\>/" ,$content,$results);
        if(isset($results[0])){
            foreach ($results[0] as $block){
                preg_match_all("/\<name\>(.*?)\<\/name\>\<value\>(.*?)\<\/value\>/", $block, $key_value);
                $data = [];
                if(isset($key_value[1])){
                    foreach ($key_value[1] as $index => $key){
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
    protected function prepareRecommendations($recommendations = array()){
        if($recommendations && is_array($recommendations)){
            foreach($recommendations as $index => $widget){

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
                        $recommendation['context']  = $this->getWidgetContext($widgetConfig['scenario']);
                    }

                    $this->bxHelperData->getAdapter()->getRecommendation(
                        $widget['widget'],
                        $recommendation['context'],
                        $recommendation['scenario'],
                        $recommendation['min'] ,
                        $recommendation['max'],
                        false
                    );
                }catch(\Exception $e){
                    Mage::logException($e);
                }
            }
        }
        return null;
    }

    /**
     * @return mixed
     */
    public function _getLoadedProductCollection(){
        
        return $this->_getProductCollection();
    }

    /**
     * @return mixed
     */
    protected function _getProductCollection(){

        if(!$this->_itemCollection && $this->bxHelperData->isPluginEnabled()){
            $this->_prepareData();
        }
        return $this->_itemCollection;
    }

    /**
     * @return $this
     */
    protected function _prepareData(){
        $widget  = $this->getData('widget');
        $context = $this->getData('context');
        if($this->getData('widget') == 'noresults') {
            $config = Mage::getStoreConfig('bxSearch/noresults');
            $widget = $config['widget'];
            $this->bxHelperData->getAdapter()->flushResponses();
            $this->bxHelperData->getAdapter()->getRecommendation(
                $widget,
                $context,
                '',
                $config['min'],
                $config['max'],
                false);
        }

        $entity_ids = array();
        try{
            $entity_ids = $this->bxHelperData->getAdapter()->getRecommendation(
                $widget,
                $context
            );
        }catch(\Exception $e){
            Mage::logException($e);
        }
        

        if ((count($entity_ids) == 0)) {
            $entity_ids = array(0);
        }

        $this->_itemCollection = $this->_productCollection = Mage::getResourceModel('catalog/product_collection');
        $this->_itemCollection->addFieldToFilter('entity_id', $entity_ids)
            ->addAttributeToSelect('*')
            ->addMinimalPrice()
            ->addFinalPrice();
        $this->_itemCollection->setBxCount(count($entity_ids));
        $this->_itemCollection->load();
        return $this;
    }

    /**
     * @return Mage_Core_Block_Abstract
     */
    protected function _beforeToHtml(){

        if($this->bxHelperData->isSetup()){
            $recommendations = $this->getLayoutRecommendationBlocks($this->getLayout()->getXmlString());
            if($recommendations){
                $this->prepareRecommendations($recommendations);
            }
            $this->bxHelperData->setSetup(false);
        }
        $this->_prepareData();
        return parent::_beforeToHtml();
    }
}
