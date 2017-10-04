<?php

/**
 * Class Boxalino_Intelligence_Helper_P13n_Adapter
 */
class Boxalino_Intelligence_Helper_P13n_Adapter{

    /**
     * @var \com\boxalino\bxclient\v1\BxClient
     */
    private static $bxClient = null;
    
    /**
     * @var array
     */
    private static $choiceContexts = array();


    /**
     * @var Mage_Core_Helper_Abstract
     */
    protected $bxHelperData;

    /**
     * @var
     */
    protected $currentSearchChoice;

    /**
     * @var bool
     */
    protected $navigation = false;

    /**
     * @var string
     */
    protected $prefixContextParameter = '';

    /**
     * Boxalino_Intelligence_Helper_P13n_Adapter constructor.
     */
    public function __construct(){
        
        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        $libPath = Mage::getModuleDir('','Boxalino_Intelligence') . DIRECTORY_SEPARATOR . 'lib';
        require_once($libPath . DIRECTORY_SEPARATOR . 'BxClient.php');
        \com\boxalino\bxclient\v1\BxClient::LOAD_CLASSES($libPath);

        if($this->bxHelperData->isPluginEnabled()){
            $this->initializeBXClient();
        }
    }

    /**
     * Initialize BxClient 
     */
    protected function initializeBXClient() {
        
        if(self::$bxClient == null) {
            $account = Mage::getStoreConfig('bxGeneral/general/account_name');
            $password = Mage::getStoreConfig('bxGeneral/general/password');
            $isDev = Mage::getStoreConfig('bxGeneral/general/dev');
            $host = Mage::getStoreConfig('bxGeneral/advanced/host');
            $p13n_username = Mage::getStoreConfig('bxGeneral/advanced/p13n_username');
            $p13n_password = Mage::getStoreConfig('bxGeneral/advanced/p13n_password');
            $domain = Mage::getStoreConfig('bxGeneral/general/domain');
            self::$bxClient = new \com\boxalino\bxclient\v1\BxClient($account, $password, $domain, $isDev, $host, null, null, null, $p13n_username, $p13n_password);
            self::$bxClient->setTimeout(Mage::getStoreConfig('bxGeneral/advanced/thrift_timeout'));
        }
    }

    /**
     * @param string $queryText
     * @return array
     */
    public function getSystemFilters($queryText="") {

        $filters = array();
        if($queryText == "") {
            $filters[] = new \com\boxalino\bxclient\v1\BxFilter('products_visibility_' . $this->bxHelperData->getLanguage(), array(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE, Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH), true);
        } else {
            $filters[] = new \com\boxalino\bxclient\v1\BxFilter('products_visibility_' . $this->bxHelperData->getLanguage(), array(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE, Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG), true);
        }
        $filters[] = new \com\boxalino\bxclient\v1\BxFilter('products_status', array(Mage_Catalog_Model_Product_Status::STATUS_ENABLED));

        return $filters;
    }

    /**
     * @return mixed|string
     */
    public function getAutocompleteChoice() {

        $choice = Mage::getStoreConfig('bxSearch/advanced/autocomplete_choice_id');
        if($choice == null) {
            $choice = "autocomplete";
        }
        $this->currentSearchChoice = $choice;
        return $choice;
    }

    /**
     * @param $queryText
     * @return mixed|string
     */
    public function getSearchChoice($queryText) {
        if($queryText == null && !is_null(Mage::registry('current_category'))) {
            $choice = Mage::getStoreConfig('bxSearch/advanced/navigation_choice_id');
            if($choice == null) {
                $choice = "navigation";
            }
            $this->currentSearchChoice = $choice;
            $this->navigation = true;
            return $choice;
        }

        if($this->bxHelperData->isProductFinderActive()){
            $this->currentSearchChoice = 'productfinder';
            return 'productfinder';
        }

        $choice = Mage::getStoreConfig('bxSearch/advanced/search_choice_id');
        if($choice == null) {
            $choice = "search";
        }
        $this->currentSearchChoice = $choice;
        return $choice;
    }

    /**
     * @return mixed|string
     */
    public function getEntityIdFieldName() {
        
        $entityIdFieldName = Mage::getStoreConfig('bxGeneral/advanced/entity_id');
        if (!isset($entityIdFieldName) || $entityIdFieldName === '') {
            $entityIdFieldName = 'products_group_id';
        }
        return $entityIdFieldName;
    }

    /**
     * @param $queryText
     * @param $autocomplete
     * @return array
     */
    public function autocomplete($queryText, $autocomplete) {

        $data = array();
        $hash = null;
        $autocompleteConfig = Mage::getStoreConfig('bxSearch/autocomplete');
        $autocomplete_limit = $autocompleteConfig['limit'];
        $products_limit = $autocompleteConfig['products_limit'];

        if ($queryText) {
            $bxRequest = new \com\boxalino\bxclient\v1\BxAutocompleteRequest($this->bxHelperData->getLanguage(), $queryText, $autocomplete_limit, $products_limit, $this->getAutocompleteChoice(), $this->getSearchChoice($queryText));
            $searchRequest = $bxRequest->getBxSearchRequest();

            if ($autocompleteConfig['category']){
                $facets = new \com\boxalino\bxclient\v1\BxFacets();
                $facets->addCategoryFacet(null, 1, 20);
                $searchRequest->setFacets($facets);
            }
            $searchRequest->setReturnFields(array('products_group_id'));
            $searchRequest->setGroupBy($this->getEntityIdFieldName());
            $searchRequest->setFilters($this->getSystemFilters($queryText));
            self::$bxClient->setAutocompleteRequest($bxRequest);
            self::$bxClient->autocomplete();

            $bxAutocompleteResponse = self::$bxClient->getAutocompleteResponse();

            $entityIds = $bxAutocompleteResponse->getBxSearchResponse()->getHitIds($this->currentSearchChoice);
            if(empty($entityIds))return $data;
            $data['global_products'] = $autocomplete->getListValues($entityIds);
            foreach ($bxAutocompleteResponse->getTextualSuggestions() as $i => $suggestion) {

                $entity_ids = array();
                $totalHitcount = $bxAutocompleteResponse->getTextualSuggestionTotalHitCount($suggestion);

                if ($totalHitcount <= 0) {
                    continue;
                }

                $_data = array(
                    'highlighted' => $bxAutocompleteResponse->getTextualSuggestionHighlighted($suggestion),
                    'title' => $suggestion,
                    'num_results' => $totalHitcount,
                    'hash' => substr(md5($suggestion . $i), 0, 10),
                    'products' => array()
                );

                if ($i == 0) {
                    $textualSuggestionFacets = $bxAutocompleteResponse->getTextualSuggestionFacets($suggestion);
                    if ($textualSuggestionFacets != null) {
						$count = 0;
                        foreach ($textualSuggestionFacets->getCategories($autocompleteConfig['ranking'], $autocompleteConfig['level']) as $category) {
                            $_data['categories'][] = ['id' => $facets->getCategoryValueId($category),
                                'title' => $facets->getCategoryValueLabel($category),
                                'num_results' => $facets->getCategoryValueCount($category)
                            ];
							if($count++>=$autocompleteConfig['count']) {
								break;
							}
                        }
                    }
                }

                foreach ($bxAutocompleteResponse->getBxSearchResponse($suggestion)->getHitIds($this->currentSearchChoice) as $id) {
                    $entity_ids[$id] = $id;
                }

                if (count($entity_ids) > 0) {
                    $_data['products'] = $autocomplete->getListValues($entity_ids);
                }

                if ($_data['title'] == $queryText) {
                    array_unshift($data, $_data);
                } else {
                    $data[] = $_data;
                }
            }
        }
        return $data;
    }

    /**
     * @param $queryText
     * @param int $pageOffset
     * @param null $overwriteHitCount
     * @param \com\boxalino\bxclient\v1\BxSortFields|null $bxSortFields
     * @param null $categoryId
     */
    public function search($queryText, $pageOffset = 0, $overwriteHitCount = null, \com\boxalino\bxclient\v1\BxSortFields $bxSortFields=null, $categoryId=null){

        $returnFields = array($this->getEntityIdFieldName(), 'categories', 'discountedPrice', 'products_bx_grouped_price', 'title', 'score');
        $additionalFields = explode(',', Mage::getStoreConfig('bxGeneral/advanced/additional_fields'));
        $returnFields = array_merge($returnFields, $additionalFields);
        $hitCount = $overwriteHitCount;

        //create search request
        if($this->bxHelperData->isProductFinderActive()) {
            $bxRequest = new \com\boxalino\bxclient\v1\BxParametrizedRequest($this->bxHelperData->getLanguage(), $this->getSearchChoice($queryText));
            $bxRequest->setQuerytext($queryText);
            $this->prefixContextParameter = $bxRequest->getRequestWeightedParametersPrefix();
            $this->setPrefixContextParameter($this->getPrefixContextParameter());
        } else {
            $bxRequest = new \com\boxalino\bxclient\v1\BxSearchRequest($this->bxHelperData->getLanguage(), $queryText, $hitCount, $this->getSearchChoice($queryText));
        }

        $bxRequest->setGroupBy($this->getEntityIdFieldName());
        $bxRequest->setReturnFields($returnFields);
        $bxRequest->setOffset($pageOffset);
        $bxRequest->setSortFields($bxSortFields);
        $bxRequest->setFacets($this->prepareFacets());
        $bxRequest->setFilters($this->getSystemFilters($queryText));
        $bxRequest->setMax($hitCount);
        if($categoryId != null) {
            $filterField = "category_id";
            $filterValues = array($categoryId);
            $filterNegative = false;
            $bxRequest->addFilter(new com\boxalino\bxclient\v1\BxFilter($filterField, $filterValues, $filterNegative));
        }
        self::$bxClient->addRequest($bxRequest);
    }

    /**
     * @param $soft_facets
     * @param $prefix
     */
    protected function setPrefixContextParameter($prefix){
        $requestParams = Mage::app()->getRequest()->getParams();
        foreach ($requestParams as $key => $value) {
            if(strpos($key, $prefix) == 0) {
                self::$bxClient->addRequestContextParameter($key, $value);
            }
        }
    }

    /**
     * 
     */
    public function simpleSearch(){
        $request = Mage::app()->getRequest();
        $queryText = Mage::helper('catalogsearch')->getQueryText();

        if (self::$bxClient->getChoiceIdRecommendationRequest($this->getSearchChoice($queryText)) != null) {
            return;
        }
        $this->checkForProductFinder();
        $field = '';
        $order = $request->getParam('order') != null ? $request->getParam('order') : $this->getMagentoStoreConfigListOrder();
       
        if ($order == 'name') {
            $field = 'products_bx_parent_title';
        } elseif ($order == 'price') {
            $field = 'products_bx_grouped_price';
        }

        $dir = false;
        $dirOrder = $request->getParam('dir');
        if ($dirOrder) {
            $dir = $dirOrder == 'asc' ? false : true;
        }

        $categoryId = Mage::registry('current_category') != null ? Mage::registry('current_category')->getId() : null;
        $overWriteLimit = $request->getParam('limit') != null ? $request->getParam('limit') : Mage::getBlockSingleton('catalog/product_list_toolbar')->getLimit();
        $pageOffset = isset($_REQUEST['p'])? ($_REQUEST['p']-1)*($overWriteLimit) : 0;
        $this->search($queryText, $pageOffset, $overWriteLimit, new \com\boxalino\bxclient\v1\BxSortFields($field, $dir), $categoryId);
    }

    /**
     * @return mixed
     */
    protected function getMagentoStoreConfigListOrder(){

        $storeConfig = $this->getMagentoStoreConfig();
        return $storeConfig['default_sort_by'];
    }

    /**
     * @return mixed
     */
    private function getMagentoStoreConfig(){

        return Mage::getStoreConfig('catalog/frontend');
    }

    /**
     * @return string
     */
    public function getPrefixContextParameter() {
        return $this->prefixContextParameter;
    }

    /**
     * @return string
     */
    private function getUrlParameterPrefix() {
        
        return 'bx_';
    }

    /**
     * @return \com\boxalino\bxclient\v1\BxFacets
     */
    private function prepareFacets(){

        $bxFacets = new \com\boxalino\bxclient\v1\BxFacets();
        $selectedValues = array();
        $bxSelectedValues = array();
        $requestParams = Mage::app()->getRequest()->getParams();
        $bxHelperData = Mage::helper('boxalino_intelligence');
        $attributeCollection = $bxHelperData->getFilterProductAttributes();
        $facetOptions = $bxHelperData->getFacetOptions();
        foreach ($requestParams as $key => $values) {
            if (strpos($key, $this->getUrlParameterPrefix()) === 0 && $key != 'bx_category_id') {
                $fieldName = substr($key, 3);
                $separator =  Mage::getStoreConfig('bxSearch/advanced/parameter_separator');
                $bxSelectedValues[$fieldName] = explode($separator, $values);
            }
            if (isset($attributeCollection['products_' . $key])) {
                $paramValues = !is_array($values) ? array($values) : $values;
                $attributeModel = Mage::getModel('eav/config')->getAttribute('catalog_product', $key)->getSource();
                foreach ($paramValues as $paramValue){
                    $selectedValues['products_' . $key][] = $attributeModel->getOptionText($paramValue);
                }
            }
        }

        if (!$this->navigation) {
            $separator =  Mage::getStoreConfig('bxSearch/advanced/parameter_separator');
            $values = isset($requestParams['bx_category_id']) ? $requestParams['bx_category_id'] : 2;
            $values = explode($separator, $values);
            $andSelectedValues = isset($facetOptions['category_id']) ? $facetOptions['category_id']['andSelectedValues']: false;
            $bxFacets->addCategoryFacet($values, 2, -1, $andSelectedValues);
        }

        foreach ($attributeCollection as $code => $attribute) {
            $bound = $code == 'discountedPrice' ? true : false;
            list($label, $type, $order, $position) = array_values($attribute);
            $selectedValue = isset($selectedValues[$code]) ? $selectedValues[$code][0] : null;
            if ($code == 'discountedPrice' && isset($bxSelectedValues[$code])) {
                $bxFacets->addPriceRangeFacet($bxSelectedValues[$code]);
            } else {
                $andSelectedValues = isset($facetOptions[$code]) ? $facetOptions[$code]['andSelectedValues']: false;
                $bxFacets->addFacet($code, $selectedValue, $type, $label, $order, $bound, -1, $andSelectedValues);
            }
        }
        foreach($bxSelectedValues as $field => $values) {
            if($field == 'discountedPrice') continue;
            $andSelectedValues = isset($facetOptions[$field]) ? $facetOptions[$field]['andSelectedValues']: false;
            $bxFacets->addFacet($field, $values, 'string', null, 2, false, -1, $andSelectedValues);
        }
        return $bxFacets;
    }

    /**
     * @return int
     */
    public function getTotalHitCount(){
        
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getTotalHitCount($this->currentSearchChoice);
    }

    /**
     * @return array
     */
    public function getEntitiesIds()
    {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getHitIds($this->currentSearchChoice, true, 0, 10, $this->getEntityIdFieldName());
    }

    /**
     * @return null
     */
    public function getFacets() {
        
        $this->simpleSearch();
        $facets = self::$bxClient->getResponse()->getFacets($this->currentSearchChoice);
        if(empty($facets)){
            return null;
        }
        $facets->setParameterPrefix($this->getUrlParameterPrefix());
        return $facets;
    }

    /**
     * @return null
     */
    public function getCorrectedQuery() {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getCorrectedQuery($this->currentSearchChoice);
    }

    /**
     * @return bool
     */
    public function areResultsCorrected() {
        
        $this->simpleSearch();
        return self::$bxClient->getResponse()->areResultsCorrected($this->currentSearchChoice);
    }

    /**
     * @return bool
     */
    public function areThereSubPhrases() {
        
        $this->simpleSearch();
        return self::$bxClient->getResponse()->areThereSubPhrases($this->currentSearchChoice);
    }

    /**
     * @return bool
     */
    public function areResultsCorrectedAndAlsoProvideSubPhrases(){
        $this->simpleSearch();
        return self::$bxClient->getResponse()->areResultsCorrectedAndAlsoProvideSubPhrases($this->currentSearchChoice);
    }
    
    /**
     * @return array
     */
    public function getSubPhrasesQueries() {
        
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getSubPhrasesQueries($this->currentSearchChoice);
    }

    /**
     * @param $queryText
     * @return int|mixed
     */
    public function getSubPhraseTotalHitCount($queryText) {
        
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getSubPhraseTotalHitCount($queryText,$this->currentSearchChoice);
    }

    /**
     * @param $queryText
     * @return array
     */
    public function getSubPhraseEntitiesIds($queryText) {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getSubPhraseHitIds($queryText, $this->currentSearchChoice, 0, $this->getEntityIdFieldName());
    }

    /**
     *
     */
    public function flushResponses() {
        self::$bxClient->flushResponses();
        self::$bxClient->resetRequests();
    }

    /**
     * @param $widgetName
     * @param array $context
     * @param string $widgetType
     * @param int $minAmount
     * @param int $amount
     * @param bool $execute
     * @return array|void
     */
    public function getRecommendation($widgetName, $context = array(), $widgetType = '', $minAmount = 3, $amount = 3, $execute=true){

        if(!$execute || !isset(self::$choiceContexts[$widgetName])){
            if (!isset(self::$choiceContexts[$widgetName])) {
                self::$choiceContexts[$widgetName] = [];
            }
            if(in_array(json_encode($context), self::$choiceContexts[$widgetName])) {
                return;
            }
            self::$choiceContexts[$widgetName][] = json_encode($context);
            if ($widgetType == '') {
                $bxRequest = new \com\boxalino\bxclient\v1\BxRecommendationRequest($this->bxHelperData->getLanguage(), $widgetName, $amount);
                $bxRequest->setGroupBy($this->getEntityIdFieldName());
                $bxRequest->setMin($minAmount);
                $bxRequest->setFilters($this->getSystemFilters());
                if (isset($context[0])) {
                    $product = $context[0];
                    $bxRequest->setProductContext($this->getEntityIdFieldName(), $product->getId());
                }

                self::$bxClient->addRequest($bxRequest);
            } else {
                if (($minAmount >= 0) && ($amount >= 0) && ($minAmount <= $amount)) {
                    $bxRequest = new \com\boxalino\bxclient\v1\BxRecommendationRequest($this->bxHelperData->getLanguage(), $widgetName, $amount, $minAmount);
                    $bxRequest->setGroupBy($this->getEntityIdFieldName());
                    $bxRequest->setFilters($this->getSystemFilters());
                    $bxRequest->setReturnFields(array($this->getEntityIdFieldName()));
                    if ($widgetType === 'basket' && is_array($context)) {
                        $basketProducts = array();
                        foreach($context as $product) {
                            $basketProducts[] = array('id'=>$product->getId(), 'price'=>$product->getPrice());
                        }
                        $bxRequest->setBasketProductWithPrices('id', $basketProducts);
                    } elseif ($widgetType === 'product' && !is_array($context)) {
                        $product = $context;
                        $bxRequest->setProductContext($this->getEntityIdFieldName(), $product->getId());
                    } elseif ($widgetType === 'category' && $context != null){
                        $filterField = "category_id";
                        $filterValues = is_array($context) ? $context : array($context);
                        $filterNegative = false;
                        $bxRequest->addFilter(new \com\boxalino\bxclient\v1\BxFilter($filterField, $filterValues, $filterNegative));
                    }
                    self::$bxClient->addRequest($bxRequest);
                }
            }
            if (!$execute) {
                return array();
            }
        }
        $count = array_search(json_encode(array($context)), self::$choiceContexts[$widgetName]);
        return self::$bxClient->getResponse()->getHitIds($widgetName, true, $count);
    }

    /**
     * @return array
     */
    protected function checkForProductFinder(){
        $fieldNames = array();
        $xml = Mage::app()->getLayout()->getXmlString();
        if(strpos($xml, '"boxalino_intelligence/giftfinder') !== false){
            $this->bxHelperData->setIsProductFinderActive(true);
        }
        return $fieldNames;
    }
}
