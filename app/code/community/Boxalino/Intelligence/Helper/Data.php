<?php

 /**
  * Class Boxalino_Intelligence_Helper_Data
  */
class Boxalino_Intelligence_Helper_Data extends Mage_Core_Helper_Data
{

    /**
     * @var array
     */
    protected $_countries = array();

    /**
     * @var Boxalino_Intelligence_Helper_P13n_Adapter
     */
    protected $adapter;

    /**
     * @var array CMS Block
     */
    protected $cmsBlock = array();

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
            $this->adapter = Mage::helper('boxalino_intelligence/p13n_adapter');
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
        }
        return '';
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
        }
        return '';
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
        }
        return '';
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
        }
        return '';
    }
    
    /**
     * @param $term
     * @param null $filters
     * @return string
     */
    public function reportSearch($term, $filters = null)
    {
        if ($this->isTrackerEnabled()) {
            $logTerm = addslashes($term);
            $script = "_bxq.push(['trackSearch', '" . $logTerm . "', " . json_encode($filters) . "]);" . PHP_EOL;
            return $script;
        }
        return '';
    }

    /**
     * @param $products array example:
     *      <code>
     *          array(
     *              array('product' => 'PRODUCTID1', 'quantity' => 1, 'price' => 59.90),
     *              array('product' => 'PRODUCTID2', 'quantity' => 2, 'price' => 10.0)
     *          )
     *      </code>
     * @param $orderId string
     * @param $price number
     * @param $currency string
     */
    public function reportPurchase($products, $orderId, $price, $currency)
    {
        if($this->isTrackerEnabled()){
            $productsJson = json_encode($products);
            $script = "_bxq.push([" . PHP_EOL;
            $script .= "'trackPurchase'," . PHP_EOL;
            $script .= $price . "," . PHP_EOL;
            $script .= "'" . $currency . "'," . PHP_EOL;
            $script .= $productsJson . "," . PHP_EOL;
            $script .= $orderId . "" . PHP_EOL;
            $script .= "]);" . PHP_EOL;
            return $script;
        }
        return '';
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
     *
     * @throws Exception
     */
    public function getWidgetConfig($widgetName)
    {
        $widgetNames = explode(',', Mage::getStoreConfig('bxRecommendations/others/widget'));
        $widgetScenarios = explode(',', Mage::getStoreConfig('bxRecommendations/others/scenario'));
        $widgetMin = explode(',', Mage::getStoreConfig('bxRecommendations/others/min'));
        $widgetMax = explode(',', Mage::getStoreConfig('bxRecommendations/others/max'));

        $index = array_search($widgetName, $widgetNames);
        if ($index !== false) {
            $widgetConfig = array(
                'widget' => $widgetNames[$index],
                'scenario' => $widgetScenarios[$index],
                'min' => $widgetMin[$index],
                'max' => $widgetMax[$index]
            );
        } else {
            throw new \Exception("There is no configuration for this widget name: " . $widgetName);
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

        $field = Mage::getStoreConfig('bxSearch/top_facet/field');
        $order = Mage::getStoreConfig('bxSearch/top_facet/order');
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

        try{
            $fields = explode(',', Mage::getStoreConfig('bxSearch/left_facets/fields'));
            $labels = explode(',', Mage::getStoreConfig('bxSearch/left_facets/labels'));
            $types = explode(',', Mage::getStoreConfig('bxSearch/left_facets/types'));
            $orders = explode(',', Mage::getStoreConfig('bxSearch/left_facets/orders'));
            $position = explode(',', Mage::getStoreConfig('bxSearch/left_facets/position'));

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
     * @return Mage_Catalog_Model_Resource_Product_Attribute_Collection
     */
    protected function _getFilterableAttributes(){

        $collection = Mage::getResourceModel('catalog/product_attribute_collection');
        $collection
            ->setItemObjectClass('catalog/resource_eav_attribute')
            ->addStoreLabel(Mage::app()->getStore()->getId())
            ->setOrder('position', 'ASC')
            ->addIsFilterableFilter()->load();
        return $collection;
    }

    /**
     * @return array
     */
    public function getFilterProductAttributes(){

        $attributes = array();
        $attributeCollection = $this->_getFilterableAttributes();

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
    public function isHierarchical($fieldName)
    {
        $fields = explode(",", Mage::getStoreConfig('bxSearch/left_facets/fields'));
        $type = explode(",", Mage::getStoreConfig('bxSearch/left_facets/types'));

        if (in_array($fieldName, $fields)) {
            if ($type[array_search($fieldName, $fields)] == 'hierarchical') {
                return true;
            }
        }
        return false;
    }

    /**
     * @return int
     */
    public function getCategoriesSortOrder()
    {
        $fields = explode(',', Mage::getStoreConfig('bxSearch/left_facets/fields'));
        $orders = explode(',', Mage::getStoreConfig('bxSearch/left_facets/orders'));

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
    public function setCmsBlock($block)
    {
        $this->cmsBlock = $block;
        return $this;
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
    public function getSubPhrasesLimit()
    {
        return Mage::getStoreConfig('bxSearch/advanced/search_sub_phrases_limit');
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
    public function isEnabledOnLayer($layer)
    {
        if ($layer instanceof Mage_CatalogSearch_Model_Layer) {
            return $this->isSearchEnabled();
        } elseif ($layer instanceof Mage_Catalog_Model_Layer) {
            return $this->isNavigationEnabled();
        }
        return false;
    }
    
    /**
     * @return bool
     */
    public function isPluginEnabled()
    {
        return Mage::getStoreConfigFlag('bxGeneral/general/enabled') && !$this->fallback;
    }

    /**
     * @return bool
     */
    public function isSearchEnabled()
    {
        return $this->isPluginEnabled() && Mage::getStoreConfigFlag('bxSearch/search/enabled');
    }

    /**
     * @return bool
     */
    public function isAutocompleteEnabled()
    {
        return $this->isPluginEnabled() && Mage::getStoreConfigFlag('bxSearch/autocomplete/enabled');
    }

    /**
     * @return bool
     */
    public function isTrackerEnabled()
    {
        return $this->isPluginEnabled() && Mage::getStoreConfigFlag('bxGeneral/tracker/enabled');
    }

    /**
     * @return bool
     */
    public function isCrosssellEnabled()
    {
        return $this->isPluginEnabled() && Mage::getStoreConfigFlag('bxRecommendations/cart/status');
    }

    /**
     * @return bool
     */
    public function isRelatedEnabled()
    {
        return $this->isPluginEnabled() && Mage::getStoreConfigFlag('bxRecommendations/related/status');
    }

    /**
     * @return bool
     */
    public function isUpsellEnabled()
    {
        return $this->isPluginEnabled() && Mage::getStoreConfigFlag('bxRecommendations/upsell/status');
    }

    /**
     * @return bool
     */
    public function isNavigationEnabled()
    {
        return $this->isPluginEnabled() && Mage::getStoreConfigFlag('bxSearch/navigation/enabled');
    }

    /**
     * @return bool
     */
    public function isLeftFilterEnabled()
    {
        return Mage::getStoreConfigFlag('bxSearch/left_facets/enabled');
    }

    /**
     * @return bool
     */
    public function isTopFilterEnabled()
    {
        return Mage::getStoreConfigFlag('bxSearch/top_facet/enabled');
    }

    /**
     * @return bool
     */
    public function isFilterLayoutEnabled($layer)
    {
        $type = null;
        if ($layer instanceof Mage_CatalogSearch_Model_Layer) {
            $type = 'search';
        } elseif ($layer instanceof Mage_Catalog_Model_Layer) {
            $type = 'navigation';
        }
        if (null === $type) {
            return false;
        }
        return $this->isEnabledOnLayer($layer) && Mage::getStoreConfig("bxSearch/{$type}/filter");
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
