 <?php

 /**
  * Class Boxalino_Intelligence_Helper_Data
  */
class Boxalino_Intelligence_Helper_Data extends Mage_Core_Helper_Data{

    /**
     * @var array
     */
    protected $_countries = array();

    /**
     * @var array Plugin Configuration Object
     */
    protected $bxConfig = null;

    /**
     * @var Boxalino_Intelligence_Helper_P13n_Adapter
     */
    protected $adapter;

    /**
     * @var array CMS Block
     */
    protected $cmsBlock;

    /**
     * @var bool
     */
    protected $setup = true;

    /**
     * @var bool
     */
    protected $fallback = false;

    /**
     * @param $countryCode
     * @return mixed
     */
    public function getCountry($countryCode)
    {
        if (!isset($this->_countries[$countryCode])) {
            $country = Mage::getModel('directory/country')->loadByCode($countryCode);
            $this->_countries[$countryCode] = $country;
        }
        return $this->_countries[$countryCode];
    }

    /**
     * @return Boxalino_Intelligence_Helper_P13n_Adapter
     */
    public function getAdapter(){
        if(!$this->adapter){
            $this->adapter = new Boxalino_Intelligence_Helper_P13n_Adapter();
        }
        return $this->adapter;
    }

    /**
     * @param $text
     * @return mixed|null|string
     */
    public function sanitizeFieldName($text)
    {
        $maxLength = 50;
        $delimiter = "_";

        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', $delimiter, $text);

        // trim
        $text = trim($text, $delimiter);

        // transliterate
        if (function_exists('iconv')) {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        }

        // lowercase
        $text = strtolower($text);

        // remove unwanted characters
        $text = preg_replace('~[^_\w]+~', '', $text);

        if (empty($text)) {
            return null;
        }

        // max $maxLength (50) chars
        $text = substr($text, 0, $maxLength);
        $text = trim($text, $delimiter);

        return $text;
    }

    /**
     * @param $product
     * @param $count
     * @param $price
     * @param $currency
     * @return string
     */
    public function reportAddToBasket($product, $count, $price, $currency)
    {
        if ($this->isTrackerEnabled()) {
            $script = "_bxq.push(['trackAddToBasket', '" . $product . "', " . $count . ", " . $price . ", '" . $currency . "']);" . PHP_EOL;
            return $script;
        } else {
            return '';
        }
    }

    /**
     * @param $categoryID
     * @return string
     */
    public function reportCategoryView($categoryID)
    {
        if ($this->isTrackerEnabled()) {
            $script = "_bxq.push(['trackCategoryView', '" . $categoryID . "'])" . PHP_EOL;
            return $script;
        } else {
            return '';
        }
    }

    /**
     * @param $customerId
     * @return string
     */
    public function reportLogin($customerId)
    {
        if ($this->isTrackerEnabled()) {
            $script = "_bxq.push(['trackLogin', '" . $customerId . "'])" . PHP_EOL;
            return $script;
        } else {
            return '';
        }
    }

    /**
     * @param $product
     * @return string
     */
    public function reportProductView($product)
    {
        if ($this->isTrackerEnabled()) {
            $script = "_bxq.push(['trackProductView', '" . $product . "'])" . PHP_EOL;
            return $script;
        } else {
            return '';
        }
    }

    /**
     * @param $productId
     * @param $storeId
     * @return mixed
     */
    public function rewrittenProductUrl($productId, $storeId)
    {
        $coreUrl = Mage::getModel('core/url_rewrite');
        $coreUrl->setStoreId($storeId);
        $coreUrl->loadByIdPath(sprintf('product/%d', $productId));
        return $coreUrl->getRequestPath();
    }

    /**
     * @param $widgetName
     * @return array Widget Configuration
     */
    public function getWidgetConfig($widgetName){

        if(!$this->bxConfig == null || !isset($this->bxConfig['bxRecommendations'])){
            $this->bxConfig['bxRecommendations'] =  Mage::getStoreConfig('bxRecommendations');
        }

        $widgetNames = explode(',', $this->bxConfig['bxRecommendations']['others']['widget']);
        $widgetScenarios = explode(',', $this->bxConfig['bxRecommendations']['others']['scenario']);
        $widgetMin = explode(',', $this->bxConfig['bxRecommendations']['others']['min']);
        $widgetMax = explode(',', $this->bxConfig['bxRecommendations']['others']['max']);

        $index =  array_search($widgetName, $widgetNames);
        $widgetConfig = array();
        if($index !== false){
            $widgetConfig = array('widget' => $widgetNames[$index], 'scenario' => $widgetScenarios[$index],
                'min' => $widgetMin[$index], 'max' => $widgetMax[$index]);
        }
        return $widgetConfig;
    }

    /**
     * @return array
     */
    public function getAllFacetFieldNames() {

        $allFacets = array_keys($this->getLeftFacets());
        if($this->getTopFacetFieldName() != null) {
            $allFacets[] = $this->getTopFacetFieldName();
        }
        return $allFacets;
    }

    /**
     * @return mixed
     */
    public function getTopFacetFieldName()
    {
        list($topField, $topOrder) = $this->getTopFacetConfig();
        return $topField;
    }

    /**
     * @return array|null
     */
    public function getTopFacetConfig() {

        if(!$this->bxConfig == null || !isset($this->bxConfig['bxSearch'])){
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }
        $field = $this->bxConfig['bxSearch']['top_facet']['field'];
        $order = $this->bxConfig['bxSearch']['top_facet']['order'];
        return array($field, $order);
    }
    
    /**
     * @return array
     */
    public function getLeftFacetFieldNames(){
        return array_keys($this->getLeftFacets());
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLeftFacets(){

        $leftFacet = array_merge($this->getFilterProductAttributes(), $this->getLeftFacetConfig());
        uasort($leftFacet, function($a, $b){
            if($a['position'] == $b['position']){
                return strcmp($a['label'],$b['label']);
            }
            if($b['position'] == -1){
                return true;
            }
            return $a['position'] - $b['position'];
        });

        return $leftFacet;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLeftFacetConfig() {

        if(!isset($this->bxConfig['bxSearch'])){
            $this->bxConfig['bxSearch'] =  Mage::getStoreConfig('bxSearch');
        }
        try{
            $fields = explode(',', $this->bxConfig['bxSearch']['left_facets']['fields']);
            $labels = explode(',', $this->bxConfig['bxSearch']['left_facets']['labels']);
            $types = explode(',', $this->bxConfig['bxSearch']['left_facets']['types']);
            $orders = explode(',', $this->bxConfig['bxSearch']['left_facets']['orders']);
            $position = explode(',', $this->bxConfig['bxSearch']['left_facets']['position']);

        }catch (\Exception $e){
            Mage::logException($e);
            return array();
        }

        if($fields[0] == "" || !$this->isLeftFilterEnabled()) {
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
        if(sizeof($fields) != sizeof($position)) {
            throw new \Exception("number of defined left facets fields doesn't match the number of defined left facet position: " . implode(',', $fields) . " versus " . implode(',', $position));
        }

        $facets = array();
        foreach($fields as $k => $field){
            $facets[$field] = array(
                'label' => $labels[$k],
                'type' =>$types[$k],
                'order' => $orders[$k],
                'position' => $position[$k]);
        }
        return $facets;
    }

    /**
     * @return array
     */
    public function getFilterProductAttributes()
    {
        $layer = Mage::getModel('catalog/layer');
        $attributes = array();
        $attributeCollection = $layer->getFilterableAttributes();

        $allowedTypes = array('multiselect', 'price', 'select');
        foreach ($attributeCollection as $attribute) {
            try{
                $data = $attribute->getData();
                if (!in_array($data['frontend_input'], $allowedTypes)) {
                    continue;
                }
                $position = $data['position'];
                $code = $data['attribute_code'];
                $type = 'list';

                if ($code == 'price') {
                    $type = 'ranged';
                }
                $code = $code == 'price' ? 'discountedPrice' : $this->getProductAttributePrefix() . $code;
                $attributes[$code] = array(
                    'label' => $attribute->getStoreLabel(Mage::app()->getStore()->getId()),
                    'type' => $type,
                    'order' => 0,
                    'position' => $position
                );
            }catch(\Exception $e){
                Mage::logException($e);
                continue;
            }
        }
        return $attributes;
    }

    /**
     * @param $fieldName
     * @return bool
     */
    public function isHierarchical($fieldName){

        if(!$this->bxConfig == null || !isset($this->bxConfig['bxSearch'])){
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }
        $facetConfig =   $this->bxConfig['bxSearch']['left_facets'];
        $fields = explode(",", $facetConfig['fields']);
        $type = explode(",", $facetConfig['types']);

        if(in_array($fieldName,$fields )){
            if($type[array_search($fieldName, $fields)] == 'hierarchical'){
                return true;
            }
        }
        return false;
    }

    /**
     * @return int
     */
    public function getCategoriesSortOrder(){

        if(!$this->bxConfig == null || !isset($this->bxConfig['bxSearch'])){
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }

        $fields = explode(',', $this->bxConfig['bxSearch']['left_facets']['fields']);
        $orders = explode(',', $this->bxConfig['bxSearch']['left_facets']['orders']);

        foreach($fields as $index => $field){
            if($field == 'categories'){
                return (int)$orders[$index];
            }
        }
        return 0;
    }

    /**
     * @return string
     */
    public function getLanguage() {
        return substr(Mage::getStoreConfig('general/locale/code'), 0, 2);
    }

    /**
     * @param $block
     */
    public function setCmsBlock($block){

        $this->cmsBlock = $block;
    }

    /**
     * @return array
     */
    public function getCmsBlock(){

        return $this->cmsBlock;
    }

    /**
     * @return bool
     */
    public function isSetup(){

        return $this->setup;
    }

    /**
     * @param $setup
     */
    public function setSetup($setup){

        $this->setup = $setup;
    }
    
    /**
     * @return mixed
     */
    public function getSubPhrasesLimit(){
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxSearch'])){
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }
        return $this->bxConfig['bxSearch']['advanced']['search_sub_phrases_limit'];
    }
    
    /**
     * @return string
     */
    private function getProductAttributePrefix(){
        return 'products_';
    }

    /**
     * @param $layer
     * @return bool
     */
    public function isEnabledOnLayer($layer){
        switch(get_class($layer)){
            case 'Mage_CatalogSearch_Model_Layer':
                return $this->isSearchEnabled();
            case 'Mage_Catalog_Model_Layer':
                return $this->isNavigationEnabled();
            default:
                return false;
        }
    }
    
    /**
     * @return bool
     */
    public function isPluginEnabled(){
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxGeneral'])){
            $this->bxConfig['bxGeneral'] = Mage::getStoreConfig('bxGeneral');
        }
        return (bool)($this->bxConfig['bxGeneral']['general']['enabled'] && !$this->fallback);
    }

    /**
     * @return bool
     */
    public function isSearchEnabled(){
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxSearch'])) {
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }
        return (bool)$this->isPluginEnabled() && $this->bxConfig['bxSearch']['search']['enabled'];
    }

    /**
     * @return bool
     */
    public function isAutocompleteEnabled(){
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxSearch'])) {
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }
        return (bool)$this->isPluginEnabled() && $this->bxConfig['bxSearch']['autocomplete']['enabled'];
    }

    /**
     * @return bool
     */
    public function isTrackerEnabled()
    {
        if (!$this->bxConfig == null || !isset($this->bxConfig['bxGeneral'])) {
            $this->bxConfig['bxGeneral'] = Mage::getStoreConfig('bxGeneral');
        }
        return (bool)$this->isPluginEnabled() && $this->bxConfig['bxGeneral']['tracker']['enabled'];
    }

    /**
     * @return bool
     */
    public function isCrosssellEnabled()
    {
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxRecommendations'])){
            $this->bxConfig['bxRecommendations'] = Mage::getStoreConfig('bxRecommendations');
        }
        return (bool)$this->isPluginEnabled() && $this->bxConfig['bxRecommendations']['cart']['status'];
    }

    /**
     * @return bool
     */
    public function isRelatedEnabled()
    {
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxRecommendations'])){
            $this->bxConfig['bxRecommendations'] = Mage::getStoreConfig('bxRecommendations');
        }
        return (bool)$this->isPluginEnabled() && $this->bxConfig['bxRecommendations']['related']['status'];
    }

    /**
     * @return bool
     */
    public function isUpsellEnabled()
    {
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxRecommendations'])){
            $this->bxConfig['bxRecommendations'] = Mage::getStoreConfig('bxRecommendations');
        }
        return (bool)$this->isPluginEnabled() && $this->bxConfig['bxRecommendations']['upsell']['status'];
    }

    /**
     * @return bool
     */
    public function isNavigationEnabled()
    {
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxSearch'])) {
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }
        return (bool)($this->isPluginEnabled() && $this->bxConfig['bxSearch']['navigation']['enabled']);
    }

    /**
     * @return bool
     */
    public function isLeftFilterEnabled()
    {
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxSearch'])) {
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }
        return $this->bxConfig['bxSearch']['left_facets']['enabled'];
    }

    /**
     * @return bool
     */
    public function isTopFilterEnabled()
    {
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxSearch'])) {
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }
        return (bool)$this->bxConfig['bxSearch']['top_facet']['enabled'];
    }

    /**
     * @return bool
     */
    public function isFilterLayoutEnabled($layer){
        
        $type = '';
        switch(get_class($layer)){
            case 'Mage_CatalogSearch_Model_Layer':
                $type = 'search';
                break;
            case 'Mage_Catalog_Model_Layer':
                $type = 'navigation';
                break;
            default:
                return false;
        }
        
        if(!isset($this->bxConfig['bxSearch'])){
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }
        return (bool)($this->isEnabledOnLayer($layer) && $this->bxConfig['bxSearch'][$type]['filter']);
    }

    /**
     * @param $fallback
     */
    public function setFallback($fallback){
        $this->fallback = $fallback;
    }

    /**
     * @return bool
     */
    public function getFallback(){
        return $this->fallback;
    }
}