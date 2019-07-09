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
     * @var array
     */
    protected $bx_filter = array();

    /**
     * @var array
     */
    protected $removedAttributes = array();

    /**
     * @var null
     */
    protected $giftFinderFields = null;

    /**
     * @var bool
     */
    protected $productFinder = false;

    /**
     * @var array
     */
    protected $removeParams = array();

    /**
     * @var array
     */
    protected $systemParams = array();

    /**
     * @var bool
     */
    protected $isFinder = false;

    CONST BOXALINO_PRICE_FACET_FIELD = "discountedPrice";

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
     * @return array
     */
    public function getWidgetConfig($widgetName)
    {
        if($widgetName == Mage::getStoreConfig('bxRecommendations/upsell/widget')){
            return array(
                'widget' => $widgetName,
                'scenario' => 'product',
                'min' => Mage::getStoreConfig('bxRecommendations/upsell/min'),
                'max' => Mage::getStoreConfig('bxRecommendations/upsell/max')
            );
        }

        if($widgetName == Mage::getStoreConfig('bxRecommendations/related/widget')) {
            return array(
                'widget' => $widgetName,
                'scenario' => 'product',
                'min' => Mage::getStoreConfig('bxRecommendations/related/min'),
                'max' => Mage::getStoreConfig('bxRecommendations/related/max')
            );
        }

        if($widgetName == Mage::getStoreConfig('bxRecommendations/blog/widget')) {
            return array(
                'widget' => Mage::getStoreConfig('bxRecommendations/blog/widget'),
                'scenario' => 'blog',
                'min' => Mage::getStoreConfig('bxRecommendations/blog/min'),
                'max' => Mage::getStoreConfig('bxRecommendations/blog/max')
            );
        }

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
            Mage::log("There is no configuration for this widget name: " . $widgetName . " Please configure this widget in Recommendation->Other recommendations configuration tab");
        }

        return $widgetConfig;
    }

    public function getCmsRecommendationBlocks($widgetName)
    {
        $recs = array();
        $recs[] = array(
            'widget' => Mage::getStoreConfig('bxRecommendations/blog/widget'),
            'scenario' => 'blog',
            'min' => Mage::getStoreConfig('bxRecommendations/blog/min'),
            'max' => Mage::getStoreConfig('bxRecommendations/blog/max')
        );

        return $recs;
    }

    public function prepareProductCollection($ids) {
        $productCollection = Mage::getResourceModel('catalog/product_collection');
        $productCollection->addFieldToFilter('entity_id', $ids)->getSelect()
            ->order(new Zend_Db_Expr('FIELD(e.entity_id,' . implode(',', $ids).')'));
        return $productCollection;
    }

    public function isBlogRecommendationActive(){
        return $this->isPluginEnabled() && Mage::getStoreConfig('bxRecommendations/blog/status');
    }

    public function getBlogReturnFields() {
        $fields = array(
            'title',
            $this->getExcerptFieldName(),
            $this->getLinkFieldName(),
            $this->getMediaUrlFieldName(),
            $this->getDateFieldName()
        );
        $extraFields = $this->getExtraFieldNames();

        return array_merge($fields, $extraFields);
    }

    public function getBlogArticleWidget(){
        return Mage::getStoreConfig('bxRecommendations/blog/widget');

    }
    public function getExcerptFieldName(){

        return Mage::getStoreConfig('bxRecommendations/blog/excerptFieldName');

    }
    public function getLinkFieldName(){
        return Mage::getStoreConfig('bxRecommendations/blog/linkFieldName');
    }

    public function getMediaUrlFieldName(){
        return Mage::getStoreConfig('bxRecommendations/blog/mediaUrlFieldName');
    }

    public function getDateFieldName(){
        return Mage::getStoreConfig('bxRecommendations/blog/dateFieldName');
    }

    public function getExtraFieldNames(){
        $fieldNames = Mage::getStoreConfig('bxRecommendations/blog/extraFieldNames');

        if (isset($fieldNames)) {
            return explode(',', $fieldNames);
        }

        return array();
    }

    public function getBlogArticleImageWidth(){
        $imageWidth = Mage::getStoreConfig('bxRecommendations/blog/blogArticleImageHeight');

        if (!is_null($imageWidth)) {
            $imageWidth = '100%';
        }

        return $imageWidth;
    }

    public function getBlogArticleImageHeight(){
        return Mage::getStoreConfig('bxRecommendations/blog/blogArticleImageWidth');
    }

    /**
     * @return Mage_Catalog_Model_Resource_Product_Attribute_Collection
     */
    protected function _getFilterableAttributes(){
        $collection = Mage::getResourceModel('catalog/product_attribute_collection');
        $collection->setItemObjectClass('catalog/resource_eav_attribute')
            ->addStoreLabel(Mage::app()->getStore()->getId())
            ->setOrder('position', 'ASC')
            ->load();
        return $collection;
    }

    /**
     * @return array
     */
    public function getFilterProductAttributes($context = 'search'){
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
                if($context == 'search') {
                    $addToRequest = (boolean) $data['is_filterable_in_search'];
                } else {
                    $addToRequest = (boolean) $data['is_filterable'];
                }
                $code = $code == 'price' ? 'discountedPrice' : $this->getProductAttributePrefix() . $code;
                $attributes[$code] = array(
                    'label' => $attribute->getStoreLabel(Mage::app()->getStore()->getId()),
                    'type' => $type,
                    'order' => 0,
                    'position' => $position,
                    'addToRequest' => $addToRequest
                );
            }catch(\Exception $e){
                Mage::logException($e);
                continue;
            }
        }
        return $attributes;
    }

    /**
     * Option to customize the search filter/params for SEO purposes
     *
     * @return array
     */
    public function getSeoFilterMapping()
    {
        $fields = array_filter(explode(';',Mage::getStoreConfig('bxSearch/advanced/seo_filters')));
        $seoMapping = [];
        foreach ($fields as $field) {
            $values = explode(':', $field);
            $seoMapping[$values[0]] = $values[1];
        }

        return $seoMapping;
    }

    /**
     * @return string
     */
    public function getPriceFacetFilterName()
    {
        $customMapping = $this->getSeoFilterMapping();
        if($key = array_search(self::BOXALINO_PRICE_FACET_FIELD, $customMapping))
        {
            return $key;
        }

        return 'bx_' . self::BOXALINO_PRICE_FACET_FIELD;
    }

    public function getFacetOptions() {
        $fields = explode(',',Mage::getStoreConfig('bxSearch/advanced/multiselect_fields'));
        $facetOptions = array();
        foreach ($fields as $field) {
            $values = explode(';', $field);
            $fieldName = $values[0];
            $andSelectedValues = sizeof($values) > 1 ? (bool)$values[1] : false;
            $facetOptions[$fieldName] = array('andSelectedValues' => $andSelectedValues);
        }
        return $facetOptions;
    }

    public function getSortOptionsMapping()
    {
        $sortMapping = array_filter(explode(';',Mage::getStoreConfig('bxSearch/advanced/sort_options_mapping')));
        $sortFields = ['name' => 'products_bx_parent_title', 'price'=>'products_bx_grouped_price'];
        foreach ($sortMapping as $field) {
            $values = explode(':', $field);
            $sortFields[$values[0]] = $values[1];
        }

        return $sortFields;
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
     * @return mixed
     */
    public function getSubPhrasesToolbar()
    {
        return (bool) Mage::getStoreConfig('bxSearch/advanced/search_sub_phrases_toolbar');
    }

    /**
     * @return string
     */
    private function getProductAttributePrefix(){
        return 'products_';
    }

    /**
     * @param $layer
     * @param $class
     * @return bool
     */
    public function layerCheck($layer, $class){
        if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
            return $layer instanceof $class;
        } else {
            return get_class($layer) == $class;
        }
    }
    /**
     * @param $layer
     * @return bool
     */
    public function isEnabledOnLayer($layer)
    {
        if ($this->layerCheck($layer, 'Mage_CatalogSearch_Model_Layer')) {
            return $this->isSearchEnabled();
        } elseif ($this->layerCheck($layer, 'Mage_Catalog_Model_Layer')) {
            return $this->isNavigationEnabled();
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isPluginEnabled()
    {
        return Mage::helper('core')->isModuleOutputEnabled('Boxalino_Intelligence') && Mage::getStoreConfigFlag('bxGeneral/general/enabled') && !$this->fallback;
    }

    /**
     * @return bool
     */
    public function isExporterEnabled()
    {
        return $this->isPluginEnabled() && Mage::getStoreConfigFlag('bxExporter/exporter/enabled');
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
    public function isBlogSearchEnabled()
    {
        return $this->isPluginEnabled() && Mage::getStoreConfigFlag('bxSearch/search/blog');
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
    public function isBannerEnabled()
    {
        return $this->isPluginEnabled() && Mage::getStoreConfigFlag('bxBanner/banner/status');
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
    public function isNoResultsEnabled() {
        return $this->isPluginEnabled() && Mage::getStoreConfigFlag('bxSearch/noresults/status');
    }

    /**
     * @return bool
     */
    public function isNavigationSortEnabled() {
        return $this->isPluginEnabled() && Mage::getStoreConfigFlag('bxSearch/navigation/sort');
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

    /**
     * @param $array
     * @return array
     */
    public function useValuesAsKeys($array){
        return array_combine(array_keys(array_flip($array)),$array);
    }

    /**
     * @param $fieldName
     */
    public function setGiftFinderFields($fieldName) {
        $this->giftFinderFields = $fieldName;
    }

    /**
     * @return null
     */
    public function getGiftFinderFields() {
        return $this->giftFinderFields;
    }

    public function setIsProductFinderActive($active){
        $this->productFinder = $active;
    }

    public function isProductFinderActive(){
        return $this->productFinder;
    }

    public function getSeparator() {
        $separator = Mage::getStoreConfig('bxSearch/advanced/parameter_separator');
        if($separator == '') {
            $separator = ',';
        }
        return $separator;
    }

    public function setRemoveParams($key) {
        $this->removeParams[] = $key;
    }

    public function getRemoveParams() {
        return $this->removeParams;
    }

    public function setSystemParams($key, $values) {
        $this->systemParams[$key] = $values;
    }

    public function getSystemParams() {
        return $this->systemParams;
    }

    public function getIsFinder() {
        return $this->isFinder;
    }

    public function setIsFinder($isFinder) {
        $this->isFinder = $isFinder;
    }

    protected $includedParams = array();
    public function setIncludedParams($key, $value) {
        $this->includedParams[$key] = $value;
    }

    public function getIncludedParams() {
        return $this->includedParams;
    }

    protected $changeQuery = false;
    public function setChangeQuery($change) {
        $this->changeQuery = $change;
    }

    public function getChangeQuery() {
        return $this->changeQuery;
    }

    public function getSEOPageTitle($choice = null){
        if ($this->isPluginEnabled()) {
            $seoPageTitle = $this->getExtraInfoWithKey('bx-page-title', $choice);
            return $seoPageTitle;
        }
        return;
    }

    public function getSEOMetaTitle($choice = null){
        if ($this->isPluginEnabled()) {
            $seoMetaTitle = $this->getExtraInfoWithKey('bx-html-meta-title', $choice);
            return $seoMetaTitle;
        }
        return;
    }

    public function getSEOMetaDescription($choice = null){
        if ($this->isPluginEnabled()) {
            $seoMetaDescription = $this->getExtraInfoWithKey('bx-html-meta-description', $choice);
            return $seoMetaDescription;
        }
        return;
    }

    public function getExtraInfoWithKey($key, $choice = null){
        return $this->getAdapter()->getExtraInfoWithKey($key, $choice);
    }
}
