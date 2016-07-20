<?php

class Boxalino_Intelligence_Helper_P13n_Adapter{

    private static $bxClient = null;
    protected $bxHelperData;
    public function __construct()
    {
        $this->bxHelperData = Mage::helper('intelligence');
        $libPath = Mage::getModuleDir('','Boxalino_Intelligence') . DIRECTORY_SEPARATOR . 'lib';
        require_once($libPath . DIRECTORY_SEPARATOR . 'BxClient.php');
        \com\boxalino\bxclient\v1\BxClient::LOAD_CLASSES($libPath);

        if($this->bxHelperData->isPluginEnabled()){
            $this->initializeBXClient();
        }
//        var_dump(self::$bxClient);
//        exit;
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
        }
    }

    public function getSystemFilters($queryText="") {

        $filters = array();
        if($queryText == "") {
            $filters[] = new \com\boxalino\bxclient\v1\BxFilter('products_visibility_' . $this->getLanguage(), array(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE, Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH), true);
        } else {
            $filters[] = new \com\boxalino\bxclient\v1\BxFilter('products_visibility_' . $this->getLanguage(), array(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE, Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG), true);
        }
        $filters[] = new \com\boxalino\bxclient\v1\BxFilter('products_status', array(Mage_Catalog_Model_Product_Status::STATUS_ENABLED));

        return $filters;
    }

    public function getAutocompleteChoice() {

        $choice = Mage::getStoreConfig('bxSearch/advanced/autocomplete_choice_id');
        if($choice == null) {
            $choice = "autocomplete";
        }
        return $choice;
    }

    public function getSearchChoice($queryText) {

        if($queryText == null) {
            $choice = Mage::getStoreConfig('bxSearch/advanced/navigation_choice_id');
            if($choice == null) {
                $choice = "navigation";
            }
            return $choice;
        }

        $choice = Mage::getStoreConfig('bxSearch/advanced/search_choice_id');
        if($choice == null) {
            $choice = "search";
        }
        return $choice;
    }

    public function getEntityIdFieldName() {
        $entityIdFieldName = Mage::getStoreConfig('bxGeneral/advanced/entity_id');
        if (!isset($entity_id) || $entity_id === '') {
            $entityIdFieldName = 'id';
        }
        return $entityIdFieldName;
    }

    public function getLanguage() {
        return substr(Mage::getStoreConfig('general/locale/code'), 0, 2);
    }

    public function autocomplete($queryText, $autocomplete) {
        $order = array();
        $hash = null;
        $data = array();
        $autocompleteConfig = Mage::getStoreConfig('bxSearch/autocomplete');
        $autocomplete_limit = $autocompleteConfig['limit'];
        $products_limit = $autocompleteConfig['products_limit'];

        if ($queryText) {

            $bxRequest = new \com\boxalino\bxclient\v1\BxAutocompleteRequest($this->getLanguage(), $queryText, $autocomplete_limit, $products_limit, $this->getAutocompleteChoice(), $this->getSearchChoice($queryText));
            $searchRequest = $bxRequest->getBxSearchRequest();

            $searchRequest->setReturnFields(array('products_group_id'));
            $searchRequest->setGroupBy('products_group_id');
            $searchRequest->setFilters($this->getSystemFilters($queryText));
            self::$bxClient->setAutocompleteRequest($bxRequest);
            self::$bxClient->autocomplete();
            $bxAutocompleteResponse = self::$bxClient->getAutocompleteResponse();

            $entity_ids = array();
            foreach($bxAutocompleteResponse->getBxSearchResponse()->getHitIds() as $id) {
                $entity_ids[$id] = $id;
            }
            foreach ($bxAutocompleteResponse->getTextualSuggestions() as $i => $suggestion) {

                $totalHitcount = $bxAutocompleteResponse->getTextualSuggestionTotalHitCount($suggestion);

                if ($totalHitcount <= 0) {
                    continue;
                }

                $_data = array(
                    'title' => $suggestion,
                    'num_results' => $totalHitcount,
                    'type' => 'suggestion',
                    'id' => $i,
                    'row_class' => 'acsuggestions'
                );

                foreach($bxAutocompleteResponse->getBxSearchResponse($suggestion)->getHitIds() as $id) {
                    $entity_ids[$id] = $id;
                }

                if ($_data['title'] == $queryText) {
                    array_unshift($data, $_data);
                } else {
                    $data[] = $_data;
                }
            }
        }

        if(sizeof($entity_ids) > 0) {
            $collection = Mage::getResourceModel('catalog/product_collection');
            $list = $collection->create()->setSotre(Mage::app()->getStore())->addFieldToFilter('entity_id', $entity_ids)
                ->addAttributeToselect('*')->load();
//            $list = $this->collectionFactory->create()->setStoreId($this->storeManager->getStore()->getId())
//                ->addFieldToFilter('entity_id', $entity_ids)->addAttributeToSelect('*');
//            $list->load();

            $productValues = $autocomplete->getListValues($list);

            $first = true;
            foreach($bxAutocompleteResponse->getBxSearchResponse()->getHitIds() as $id) {
                $row = array();
                $row['type'] = 'global_products';
                $row['row_class'] = 'suggestion-item global_product_suggestions';
                $row['product'] = $productValues[$id];
                $row['first'] = $first;
                $first = false;
                $data[] = $row;
            }

            foreach ($bxAutocompleteResponse->getTextualSuggestions() as $i => $suggestion) {
                foreach($bxAutocompleteResponse->getBxSearchResponse($suggestion)->getHitIds() as $id) {
                    $data[] = array("type"=>"sub_products","product"=> $productValues[$id], 'row_class'=>'suggestion-item sub_product_suggestions sub_id_' . $i);
                }
            }
        }

        return $data;
    }

    public function search($queryText, $pageOffset = 0, $overwriteHitcount = null, \com\boxalino\bxclient\v1\BxSortFields $bxSortFields=null, $categoryId=null)
    {
        $returnFields = array('products_group_id'/*$this->getEntityIdFieldName()*/, 'categories', 'discountedPrice', 'title', 'score');
        $additionalFields = explode(',', Mage::getStoreConfig('bxGeneral/advanced/additional_fields'));
        $returnFields = array_merge($returnFields, $additionalFields);

        $hitCount = $overwriteHitcount;

        //create search request
        $bxRequest = new \com\boxalino\bxclient\v1\BxSearchRequest($this->getLanguage(), $queryText, $hitCount, $this->getSearchChoice($queryText));
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
                $field = 'discountedPrice';
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
        echo "$overWriteLimit<br>";
        echo $pageOffset;
        $this->search($queryText, $pageOffset, $overWriteLimit, new \com\boxalino\bxclient\v1\BxSortFields($field, $dir), $categoryId);
    }

    private function getLeftFacets() {

        $fields = explode(',', Mage::getStoreConfig('bxSearch/left_facets/fields'));
        $labels = explode(',', Mage::getStoreConfig('bxSearch/left_facets/labels'));
        $types = explode(',', Mage::getStoreConfig('bxSearch/left_facets/types'));
        $orders = explode(',', Mage::getStoreConfig('bxSearch/left_facets/orders'));

        if($fields[0] == "" || !$this->bxHelperData->isLeftFilterEnabled()) {
            return array();
        }

        if(sizeof($fields) != sizeof($labels)) {
            throw new \Exception("number of defined left facets fields doesn't match the number of defined left facet labels: " . implode(',', $fields) . " versus " . implode(',', $labels));
        }
        if(sizeof($fields) != sizeof($types)) {
            throw new \Exception("number of defined left facets fields doesn't match the number of defined left facet types: " . implode(',', $fields) . " versus " . implode(',', $types));
        }
        if(sizeof($fields) != sizeof($orders)) {
            throw new \Exception("number of defined left facets fields doesn't match the number of defined left facet orders: " . implode(',', $fields) . " versus " . implode(',', $orders));
        }

        $facets = array();
        foreach($fields as $k => $field){
            $facets[$field] = array($labels[$k], $types[$k], $orders[$k]);
        }

        return $facets;
    }

    private function getTopFacetValues() {

        if($this->bxHelperData->isTopFilterEnabled()){
            $field = Mage::getStoreConfig('bxSearch/top_facet/field');
            $order = Mage::getStoreConfig('bxSearch/top_facet/order');
            return array($field, $order);
        }
        return null;

    }

    public function getLeftFacetFieldNames() {
        return array_keys($this->getLeftFacets());
    }

    public function getAllFacetFieldNames() {
        $allFacets = array_keys($this->getLeftFacets());
        if($this->getTopFacetFieldName() != null) {
            $allFacets[] = $this->getTopFacetFieldName();
        }
        return $allFacets;
    }
    
    private function getUrlParameterPrefix() {
        return 'bx_';
    }
    
    private function prepareFacets(){

        if($this->bxHelperData->isSearchEnabled()){

            $bxFacets = new \com\boxalino\bxclient\v1\BxFacets();

            $selectedValues = array();
            foreach ($_REQUEST as $key => $values) {
                if (strpos($key, $this->getUrlParameterPrefix()) !== false) {
                    $fieldName = substr($key, 3);
                    $selectedValues[$fieldName] = !is_array($values)?array($values):$values;
                }
            }

            $catId = isset($selectedValues['category_id']) && sizeof($selectedValues['category_id']) > 0 ? $selectedValues['category_id'][0] : null;

            $bxFacets->addCategoryFacet($catId);
            foreach($this->getLeftFacets() as $fieldName => $facetValues) {
                $selectedValue = isset($selectedValues[$fieldName][0]) ? $selectedValues[$fieldName][0] : null;
                $bxFacets->addFacet($fieldName, $selectedValue, $facetValues[1], $facetValues[0], $facetValues[2]);
            }


            list($topField, $topOrder) = $this->getTopFacetValues();
            if($topField) {
                $selectedValue = isset($selectedValues[$topField][0]) ? $selectedValues[$topField][0] : null;
                $bxFacets->addFacet($topField, $selectedValue, "string", $topField, $topOrder); // 1 ?? *iku*
            }

            return $bxFacets;
        }
        return null;
    }
    
    
    public function getTopFacetFieldName() {
        list($topField, $topOrder) = $this->getTopFacetValues();
        return $topField;
    }
    
    public function getTotalHitCount()
    {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getTotalHitCount();
    }
    
    public function getEntitiesIds()
    {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getHitIds();
    }

    public function getFacets() {
        $this->simpleSearch();
        $facets = self::$bxClient->getResponse()->getFacets();
        if(empty($facets)){
            return null;
        }
        $facets->setParameterPrefix($this->getUrlParameterPrefix());
        return $facets;
    }

    public function getCorrectedQuery() {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getCorrectedQuery();
    }

    public function areResultsCorrected() {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->areResultsCorrected();
    }

    public function areThereSubPhrases() {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->areThereSubPhrases();
    }

    public function getSubPhrasesQueries() {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getSubPhrasesQueries();
    }

    public function getSubPhraseTotalHitCount($queryText) {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getSubPhraseTotalHitCount($queryText);
    }

    public function getSubPhraseEntitiesIds($queryText) {
        $this->simpleSearch();
        return self::$bxClient->getResponse()->getSubPhraseHitIds($queryText, $this->getEntityIdFieldName());
    }

    public function getRecommendation($widgetType, $widgetName, $minAmount = 3, $amount = 3, $context = array(), $execute=true)
    {
        if(sizeof(self::$bxClient->getRecommendationRequests()) == 0) {

            $recommendations = Mage::getStoreConfig('bxRecommendations');
            if ($widgetType == '') {

                $bxRequest = new \com\boxalino\bxclient\v1\BxRecommendationRequest($this->getLanguage(), $widgetName, $amount);
                $bxRequest->setMin($minAmount);
                $bxRequest->setFilters($this->getSystemFilters());
                if (isset($context[0])) {
                    $product = $context[0];
                    $bxRequest->setProductContext($this->getEntityIdFieldName(), $product->getId());
                }
                self::$bxClient->addRequest($bxRequest);
            } else {
                if($recommendations['others'] != null){
                    $widgetNames = explode(',',$recommendations['others']['widget']);
                    $widgetTypes = explode(',',$recommendations['others']['scenario']);
                    $widgetMin = explode(',',$recommendations['others']['min']);
                    $widgetMax = explode(',',$recommendations['others']['max']);
                    unset($recommendations['others']);
                    foreach($widgetTypes as $i => $type){
                        $recommendations[] = array('enabled' => 1,
                            'min' => $widgetMin[$i], 'max' => $widgetMax[$i], 'widget'=> $widgetNames[$i],
                            'scenario' => $type);
                    }
                }

                foreach ($recommendations as $key => $recommendation) {
                    $type = 'others';
                    if($key == 'cart') {
                        $type = 'basket';
                        if($recommendation['widget'] == ''){
                            $recommendation['widget'] = 'basket';
                        }
                    }
                    if($key == 'related' || $key == 'upsell') {
                        $type = 'product';
                        if($recommendation['widget'] == ''){
                            $recommendation['widget'] = $key == 'related'? 'similar' : 'complementary';
                        }
                    }
                    if(isset($recommendation['scenario'])){
                        $type = $recommendation['scenario'];
                    }

                    if (
                        (!empty($recommendation['min']) || $recommendation['min'] >= 0) &&
                        (!empty($recommendation['max']) || $recommendation['max'] >= 0) &&
                        ($recommendation['min'] <= $recommendation['max']) &&
                        (!isset($recommendation['enabled']) || $recommendation['enabled'] == 1)
                    ) {
                        if ($type == $widgetType) {
                            $bxRequest = new \com\boxalino\bxclient\v1\BxRecommendationRequest($this->getLanguage(), $recommendation['widget'], $recommendation['max']);
                            $bxRequest->setMin($recommendation['min']);
                            $bxRequest->setFilters($this->getSystemFilters());
                            if ($widgetType === 'basket') {
                                $basketProducts = array();
                                foreach($context as $product) {
                                    $basketProducts[] = array('id'=>$product->getid(), 'price'=>$product->getPrice());
                                }
                                $bxRequest->setBasketProductWithPrices($this->getEntityIdFieldName(), $basketProducts);
                            } elseif ($widgetType === 'product' && isset($context[0])) {
                                $product = $context[0];
                                $bxRequest->setProductContext($this->getEntityIdFieldName(), $product->getId());
                            } elseif ($widgetType === 'category' && isset($context[0])){
                                $filterField = "category_id";
                                $filterValues = array($context[0]);
                                $filterNegative = false;
                                $bxRequest->addFilter(new BxFilter($filterField, $filterValues, $filterNegative));
                            }
                            self::$bxClient->addRequest($bxRequest);
                        }
                    }
                }
            }
        }
        if(!$execute) {
            return array();
        }
        return self::$bxClient->getResponse()->getHitIds($widgetName);
    }
}