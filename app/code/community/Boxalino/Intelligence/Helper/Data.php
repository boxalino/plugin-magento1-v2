 <?php

class Boxalino_Intelligence_Helper_Data extends Mage_Core_Helper_Data{

    protected $_countries = array();
    protected $bxConfig = null;
    protected $adapter;
    public function getCountry($countryCode)
    {
        if (!isset($this->_countries[$countryCode])) {
            $country = Mage::getModel('directory/country')->loadByCode($countryCode);
            $this->_countries[$countryCode] = $country;
        }
        return $this->_countries[$countryCode];
    }
    
    public function getAdapter(){
        if(!$this->adapter){
            $this->adapter = new Boxalino_Intelligence_Helper_P13n_Adapter();
        }
        return $this->adapter;
    }
    
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

    public function rewrittenProductUrl($productId, $storeId)
    {
        $coreUrl = Mage::getModel('core/url_rewrite');
        $coreUrl->setStoreId($storeId);
        $coreUrl->loadByIdPath(sprintf('product/%d', $productId));
        return $coreUrl->getRequestPath();
    }

    public function isPluginEnabled(){
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxGeneral'])){
            $this->bxConfig['bxGeneral'] = Mage::getStoreConfig('bxGeneral');
        }
        return (bool)$this->bxConfig['bxGeneral']['general']['enabled'];
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
        return (bool)$this->isSearchEnabled() && $this->bxConfig['bxSearch']['navigation']['enabled'];
    }

    /**
     * @return bool
     */
    public function isLeftFilterEnabled()
    {
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxSearch'])) {
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }
        return (bool)$this->isSearchEnabled() && $this->bxConfig['bxSearch']['left_facets']['enabled'];
    }

    /**
     * @return bool
     */
    public function isTopFilterEnabled()
    {
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxSearch'])) {
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }
        return (bool)$this->isSearchEnabled() && $this->bxConfig['bxSearch']['top_facet']['enabled'];
    }

    /**
     * @return bool
     */
    public function isFilterLayoutEnabled(){
        if(!$this->bxConfig == null || !isset($this->bxConfig['bxSearch'])) {
            $this->bxConfig['bxSearch'] = Mage::getStoreConfig('bxSearch');
        }
        return (bool)$this->isSearchEnabled() && $this->bxConfig['bxSearch']['filter']['enabled'];
    }
}