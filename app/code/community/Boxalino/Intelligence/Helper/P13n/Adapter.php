<?php

class Boxalino_Intelligence_Helper_P13n_Adapter{

    private static $bxClient = null;
    /**
     * @var array
     */
    private static $choiceContexts = array();
    protected $bxHelperData;
    protected $currentSearchChoice;
    public function __construct()
    {
        Mage::setIsDeveloperMode(true);
        $this->bxHelperData = Mage::helper('intelligence');
        $libPath = Mage::getModuleDir('','Boxalino_Intelligence') . DIRECTORY_SEPARATOR . 'lib';
        require_once($libPath . DIRECTORY_SEPARATOR . 'BxClient.php');
        \com\boxalino\bxclient\v1\BxClient::LOAD_CLASSES($libPath);

        if($this->bxHelperData->isPluginEnabled()){
            $this->initializeBXClient();
        }
    }
    
    protected function initializeBXClient() {
        if(self::$bxClient == null) {
            $generalConfig = Mage::getStoreConfig('bxGeneral');
            $account = $generalConfig['general']['account_name'];
            $password = $generalConfig['general']['password'];
            $isDev = $generalConfig['general']['dev'];
            $host = $generalConfig['advanced']['host'];
            $p13n_username = $generalConfig['advanced']['p13n_username'];
            $p13n_password = $generalConfig['advanced']['p13n_password'];
            $domain = $generalConfig['general']['domain'];
            self::$bxClient = new \com\boxalino\bxclient\v1\BxClient($account, $password, $domain, $isDev, $host, null, null, null, $p13n_username, $p13n_password);
            self::$bxClient->setTimeout(Mage::getStoreConfig('bxGeneral/advanced/thrift_timeout'));

        }
    }

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

    public function getAutocompleteChoice() {

        $choice = Mage::getStoreConfig('bxSearch/advanced/autocomplete_choice_id');
        if($choice == null) {
            $choice = "autocomplete";
        }
        $this->currentSearchChoice = $choice;
        return $choice;
    }

    public function getSearchChoice($queryText) {

        if($queryText == null) {
            $choice = Mage::getStoreConfig('bxSearch/advanced/navigation_choice_id');
            if($choice == null) {
                $choice = "navigation";
            }
            $this->currentSearchChoice = $choice;
            return $choice;
        }

        $choice = Mage::getStoreConfig('bxSearch/advanced/search_choice_id');
        if($choice == null) {
            $choice = "search";
        }
        $this->currentSearchChoice = $choice;
        return $choice;
    }

    public function getEntityIdFieldName() {
        $entityIdFieldName = Mage::getStoreConfig('bxGeneral/advanced/entity_id');
        if (!isset($entityIdFieldName) || $entityIdFieldName === '') {
            $entityIdFieldName = 'products_group_id';
        }
        return $entityIdFieldName;
    }



    public function autocomplete($queryText, $autocomplete) {

        $order = array();
        $data = array();
        $hash = null;

        $autocompleteConfig = Mage::getStoreConfig('bxSearch/autocomplete');
        $autocomplete_limit = $autocompleteConfig['limit'];
        $products_limit = $autocompleteConfig['products_limit'];

        if ($queryText) {
            $bxRequest = new \com\boxalino\bxclient\v1\BxAutocompleteRequest($this->bxHelperData->getLanguage(), $queryText, $autocomplete_limit, $products_limit, $this->getAutocompleteChoice(), $this->getSearchChoice($queryText));
            $searchRequest = $bxRequest->getBxSearchRequest();

            $searchRequest->setReturnFields(array('products_group_id'));
            $searchRequest->setGroupBy($this->getEntityIdFieldName());
            $searchRequest->setFilters($this->getSystemFilters($queryText));
            self::$bxClient->setAutocompleteRequest($bxRequest)->autocomplete();
            $bxAutocompleteResponse = self::$bxClient->getAutocompleteResponse();

            foreach ($bxAutocompleteResponse->getTextualSuggestions() as $i => $suggestion) {
                $entity_ids = array();
                $totalHitcount = $bxAutocompleteResponse->getTextualSuggestionTotalHitCount($suggestion);

                if ($totalHitcount <= 0) {
                    continue;
                }

                $_data = array(
                    'title' => $suggestion,
                    'num_results' => $totalHitcount,
                    'hash' => substr(md5($suggestion . $i), 0, 10),
                    'products' => array()
                );

                foreach ($bxAutocompleteResponse->getBxSearchResponse($suggestion)->getHitIds() as $id) {
                    $entity_ids[$id] = $id;
                }

                if (count($entity_ids) > 0) {
                    $collection = Mage::getResourceModel('catalog/product_collection');
                    $list = $collection->addFieldToFilter('entity_id', $entity_ids)
                        ->addAttributeToselect('*')->load();
                    $productValues = $autocomplete->getListValues($list);
                    $list = null;
                    $_data['products'] = $productValues;
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

    public function search($queryText, $pageOffset = 0, $overwriteHitcount = null, \com\boxalino\bxclient\v1\BxSortFields $bxSortFields=null, $categoryId=null)
    {
        $returnFields = array($this->getEntityIdFieldName(), 'categories', 'discountedPrice', 'products_bx_grouped_price', 'title', 'score');
        $additionalFields = explode(',', Mage::getStoreConfig('bxGeneral/advanced/additional_fields'));
        $returnFields = array_merge($returnFields, $additionalFields);

        $hitCount = $overwriteHitcount;

        //create search request
        $bxRequest = new \com\boxalino\bxclient\v1\BxSearchRequest($this->bxHelperData->getLanguage(), $queryText, $hitCount, $this->getSearchChoice($queryText));
        $bxRequest->setGroupBy($this->getEntityIdFieldName());
        $bxRequest->setReturnFields($returnFields);
        $bxRequest->setOffset($pageOffset);
        $bxRequest->setSortFields($bxSortFields);
        $bxRequest->setFacets($this->prepareFacets());
        $bxRequest->setFilters($this->getSystemFilters($queryText));
        $bxRequest->setMax($overwriteHitcount);

        if($categoryId != null) {
            $filterField = "category_id";
            $filterValues = array($categoryId);
            $filterNegative = false;
            $bxRequest->addFilter(new com\boxalino\bxclient\v1\BxFilter($filterField, $filterValues, $filterNegative));
        }

        self::$bxClient->addRequest($bxRequest);
    }

    public function getMagentoStoreConfigPageSize() {
        $request = Mage::app()->getRequest();
        $storeConfig = Mage::getStoreConfig('catalog/frontend');
        $storeDisplayMode = $storeConfig['list_mode'];

        //we may get grid-list, list-grid, grid or list
        $storeMainMode = explode('-', $storeDisplayMode);
        if($request->getParam('mode') == 'list'){
            $storeMainMode = $storeMainMode[1];
        }else{
            $storeMainMode = $storeMainMode[0];
        }

        $hitCount = $storeConfig[$storeMainMode . '_per_page'];

        return $hitCount;
    }

    public function didViewModeChange(){
        $request = Mage::app()->getRequest();
        $storeConfig = Mage::getStoreConfig('catalog/frontend');
        $storeDisplayMode = $storeConfig['list_mode'];
        $storeMainMode = explode('-', $storeDisplayMode);
        if($request->getParam('mode') == 'list'){
            $storeMainMode = $storeMainMode[1];
        }else{
            $storeMainMode = $storeMainMode[0];
        }

        $limit = $request->getParam('limit');
        return $limit != null && strpos($storeConfig[$storeMainMode . '_per_page_values'], $limit) === false;
    }

    public function simpleSearch() {
        $request = Mage::app()->getRequest();
        $queryText =Mage::helper('catalogsearch')->getEscapedQueryText();
        if(self::$bxClient->getChoiceIdRecommendationRequest($this->getSearchChoice($queryText))!=null) {
            return;
        }

        $field = '';
        $dir = '';

        $order = $request->getParam('order');

        if(isset($order)){
            if($order == 'name'){
                $field = 'title';
            } elseif($order == 'price'){
                $field = 'products_bx_grouped_price';
            }
        }
        $dirOrder = $request->getParam('dir');
        if($dirOrder){
            $dir = $dirOrder == 'asc' ? false : true;
        } else{
            $dir = true;
        }

        $categoryId = $request->getParam($this->getUrlParameterPrefix() . 'category_id');
        if (empty($categoryId)) {
            /* @var $category Mage_Catalog_Model_Category */
            $category = Mage::registry('current_category');
            if (!empty($category)) {
                $_REQUEST[$this->getUrlParameterPrefix() . 'category_id'][0] = $category->getId();
            }
            // GET param 'cat' may override the current_category,
            // i.e. when clicking on subcategories in a category page
            $cat = $request->getParam('cat');
            if (!empty($cat)) {
                $_REQUEST[$this->getUrlParameterPrefix() . 'category_id'][0] = $cat;
            }
        }

        $overWriteLimit = isset($_REQUEST['limit'])? $_REQUEST['limit'] : $this->getMagentoStoreConfigPageSize();

        $pageOffset = isset($_REQUEST['p'])? ($_REQUEST['p']-1)*($overWriteLimit) : 0;
        $this->search($queryText, $pageOffset, $overWriteLimit, new \com\boxalino\bxclient\v1\BxSortFields($field, $dir), $categoryId);
    }
    
    private function getUrlParameterPrefix() {
        return 'bx_';
    }
    
    private function prepareFacets(){

        $bxFacets = new \com\boxalino\bxclient\v1\BxFacets();
        $bxHelperData = Mage::helper('intelligence');
        $selectedValues = array();
        foreach ($_REQUEST as $key => $values) {
            if (strpos($key, $this->getUrlParameterPrefix()) !== false) {
                $fieldName = substr($key, 3);
                $selectedValues[$fieldName] = !is_array($values)?array($values):$values;
            }
        }

        $catId = isset($selectedValues['category_id']) && sizeof($selectedValues['category_id']) > 0 ? $selectedValues['category_id'][0] : null;

        $bxFacets->addCategoryFacet($catId);
        $attributeCollection = $bxHelperData->getLeftFacets();
        
        foreach($attributeCollection as $code => $attribute){
            $bound = $code == 'discountedPrice' ? true : false;
            list($label, $type, $order, $position) = array_values($attribute);
            $selectedValue = isset($selectedValues[$code]) ? $selectedValues[$code][0] : null;

            $bxFacets->addFacet($code, $selectedValue, $type, $label, $order, $bound);
        }
        list($topField, $topOrder) = $bxHelperData->getTopFacetConfig();

        if($topField) {
            $selectedValue = isset($selectedValues[$topField][0]) ? $selectedValues[$topField][0] : null;
            $bxFacets->addFacet($topField, $selectedValue, "string", $topField, $topOrder);
        }
        return $bxFacets;
    }
    
    public function getTotalHitCount()
    {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getTotalHitCount($this->currentSearchChoice);
    }
    
    public function getEntitiesIds()
    {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getHitIds($this->currentSearchChoice);
    }

    public function getFacets() {
        $this->simpleSearch();
        $facets = self::$bxClient->getResponse()->getFacets($this->currentSearchChoice);
        if(empty($facets)){
            return null;
        }
        $facets->setParameterPrefix($this->getUrlParameterPrefix());
        return $facets;
    }

    public function getCorrectedQuery() {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getCorrectedQuery($this->currentSearchChoice);
    }
 
    public function areResultsCorrected() {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->areResultsCorrected($this->currentSearchChoice);
    }

    public function areThereSubPhrases() {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->areThereSubPhrases($this->currentSearchChoice);
    }

    public function getSubPhrasesQueries() {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getSubPhrasesQueries($this->currentSearchChoice);
    }

    public function getSubPhraseTotalHitCount($queryText) {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getSubPhraseTotalHitCount($queryText,$this->currentSearchChoice);
    }

    public function getSubPhraseEntitiesIds($queryText) {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getSubPhraseHitIds($queryText, $this->currentSearchChoice);
    }

    public function getRecommendation($widgetName, $context = array(), $widgetType = '', $minAmount = 3, $amount = 3, $execute=true){

        if(!$execute) {
            if(!isset(self::$choiceContexts[$widgetName])) {
                self::$choiceContexts[$widgetName] = array();
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
                        $bxRequest->setBasketProductWithPrices($this->getEntityIdFieldName(), $basketProducts);
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
            return array();
        }
        $count = array_search(json_encode(array($context)), self::$choiceContexts[$widgetName]);
        return self::$bxClient->getResponse()->getHitIds($widgetName, true, $count);
    }
}