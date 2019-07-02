<?php
/**
 * Class Boxalino_Intelligence_Block_Autocomplete
 */
class Boxalino_Intelligence_Block_Autocomplete extends Mage_CatalogSearch_Block_Autocomplete
{

    CONST BOXALINO_AUTOCOMPLETE_CATEGORIES = "categories";

    /**
     * @var null | bool
     */
    protected $showPrice = null;

    /**
     * @var null
     */
    protected $showPropertyAfterTextualSuggestion = null;

    /**
     * @return string
     */
    protected function _toHtml()
    {
        if(!$this->checkIfPluginToBeUsed())
        {
            return parent::_toHtml();
        }

        $html = '';
        if (!$this->_beforeToHtml()) {
            return $html;
        }
        $query = $this->helper('catalogsearch')->getQueryText();
        if(empty($query)){
            return $html;
        }

        $suggestData = [];
        $autocompleteHelper = new Boxalino_Intelligence_Helper_Autocomplete();
        try {
            $suggestData = Mage::helper('boxalino_intelligence')->getAdapter()->autocomplete(
                $query,
                $autocompleteHelper
            );
        } catch(\Exception $e){
            Mage::logException($e);
        }

        if(!empty($suggestData))
        {
            $productHtml = '<ul class="products">';
            $globalProducts = isset($suggestData['global_products']) ? $suggestData['global_products'] : [];

            $productHtml .= $this->generateGlobalProductHtml($globalProducts);
            unset($suggestData['global_products']);

            $outCategoriesHtml = '';
            if(!$this->showCategoriesAfterFirstTextualSuggestion())
            {
                $outCategoriesHtml = $this->generateCategoryHtml($suggestData);
                unset($suggestData['categories']);
            }

            $extraPropertiesHtml = $this->generatePropertyHtml($suggestData['properties'], $query);
            unset($suggestData['properties']);

            $suggestionHtml = '';
            foreach($suggestData as $index => $item)
            {
                $suggestionHtml .= $this->generateTextualSuggestionHtml($item);
                if($index==0 && $this->showCategoriesAfterFirstTextualSuggestion())
                {
                    if(isset($item[self::BOXALINO_AUTOCOMPLETE_CATEGORIES]) && count($item[self::BOXALINO_AUTOCOMPLETE_CATEGORIES]))
                    {
                        $suggestionHtml .= $this->generateCategoryHtml($item);
                    }
                }

                foreach($item['products'] as $product)
                {
                    $productHtml .= $this->generateProductHtml($product, $item, false);
                }
            }

            $html .= '<ul class="queries"><li style="display:none"></li>';
            $html .= $suggestionHtml . '</ul>' . $productHtml . '</ul>' . $outCategoriesHtml .  $extraPropertiesHtml ;
        }

        return  $html;
    }

    /**
     * Global Product recommendations HTML template in the response list
     * It can be overwritten in a custom class
     *
     * @param $globalProductsCollection
     * @return string
     */
    public function generateGlobalProductHtml($globalProductsCollection)
    {
        $globalSuggestionsHtml = '';
        foreach ($globalProductsCollection as $product)
        {
            $globalSuggestionsHtml .= '<li title="global products" ';
            $globalSuggestionsHtml .= 'class="product-autocomplete global-products">';
            $globalSuggestionsHtml .= '<a href="'.$product['url'].'">';
            $globalSuggestionsHtml .= '<div class="product-image"><img src="'.$product['image'].'" alt="'.$this->escapeHtml($product['name']).'" /></div>';
            $globalSuggestionsHtml .= '<div class="product-title"><span>'.$this->escapeHtml($product['name']).'</span></div>';
            if ($this->showPrice())
            {
                $globalSuggestionsHtml .= '<div class="product-price"><span>'.$this->escapeHtml($product['price']).'</span></div>';
            }
            $globalSuggestionsHtml .= '</a></li>';
        }

        return $globalSuggestionsHtml;
    }

    /**
     * Product HTML template in the response list
     * It can be overwritten in a custom class
     *
     * @param $product
     * @param $item
     * @return string
     */
    public function generateProductHtml($product, $item)
    {
        $html = '<li title="'.$this->escapeHtml($item['title']).'" style="display:none" ';
        $html .= 'class="product-autocomplete" data-word="'.$item['hash'].'">';
        $html .= '<a href="'.$product['url'].'">';
        $html .= '<div class="product-image"><img src="'.$product['image'].'" alt="'.$this->escapeHtml($product['name']).'" /></div>';
        $html .= '<div class="product-title"><span>'.$this->escapeHtml($product['name']).'</span></div>';
        if ($this->showPrice())
        {
            $html .= '<div class="product-price"><span>'.$this->escapeHtml($product['price']).'</span></div>';
        }
        $html .= '</a></li>';

        return $html;
    }

    /**
     * Textual Suggestion HTML template in the response list
     * It can be overwritten in a custom class
     *
     * @param array $item
     * @return string
     */
    public function generateTextualSuggestionHtml($item = [])
    {
        $suggestionHtml = '';
        $suggestionHtml .= '<li data-word="'.$item['hash'].'" title="'.$this->escapeHtml($item['title']).'"';
        $suggestionHtml .= ' class="acsuggestion">';
        $suggestionHtml .= '<span class="query-title">'.$item['highlighted'].'</span>';
        $suggestionHtml .= '<span class="amount">('.$item['num_results'].')</span></li>';

        return $suggestionHtml;
    }

    /**
     * Category HTML template in the response list
     * It can be overwritten in a custom class
     *
     * @param $item
     * @return string
     */
    public function generateCategoryHtml($item=[])
    {
        $suggestionHtml = '<ul class="categories"><li style="display:none"></li>';
        $catalogSearchHelper = Mage::helper('catalogsearch');
        $resultUrl = $catalogSearchHelper->getResultUrl();
        foreach ($item[self::BOXALINO_AUTOCOMPLETE_CATEGORIES] as $category)
        {
            if (!isset($category['num_results'])) {
                $category['num_results'] = 0;
            }
            $suggestionHtml .= '<li class="facet"><a href="' . $resultUrl . '?q=' . $item['title'] . '&bx_category_id=' . $category['id'] . '" class="facet"><span class="query-title">' . $this->__("in %s", $category['title']) . '</span>';
            $suggestionHtml .= '<span class="amount">('.$category['num_results'].')</span></li></a>';
        }

        $suggestionHtml .= '</ul>';
        return $suggestionHtml;
    }

    /**
     * Custom property HTML template in the response list
     * It can be overwritten in a custom class
     *
     * @param $data
     * @param $query
     * @return string
     */
    public function generatePropertyHtml($data, $query)
    {
        $suggestionHtml = '<ul class="suggestions-facets"><li style="display:none"></li>';
        if(empty($data))
        {
            return $suggestionHtml .  '</ul>';
        }

        $catalogSearchHelper = Mage::helper('catalogsearch');
        $resultUrl = $catalogSearchHelper->getResultUrl();
        foreach($this->getProperties() as $property)
        {
            $suggestionHtml .='<ul class="property-$property"><li style="display:none"></li>';
            foreach($data[$property] as $value)
            {
                if (!isset($value['num_results'])) {
                    $value['num_results'] = 0;
                }

                $suggestionHtml .= '<li class="facet"><a href="' . $resultUrl . '?q=' . $query . '&bx_'.$property.'=' . $value['value'] . '" class="facet"><span class="query-title">' . $this->__("in %s", $value['title']) . '</span>';
                $suggestionHtml .= '<span class="amount">('.$value['num_results'].')</span></li></a>';
            }
            $suggestionHtml .='</ul>';
        }

        $suggestionHtml .= '</ul>';
        return $suggestionHtml;
    }

    /**
     * Get extra properties requests
     *
     * @return []
     */
    public function getProperties()
    {
        return array_filter(explode(',', Mage::getStoreConfig('bxSearch/autocomplete/property_query')));
    }

    /**
     * Check if property content is linked to 1st textual suggestion
     *
     * @return bool
     */
    public function showCategoriesAfterFirstTextualSuggestion()
    {
        if(is_null($this->showPropertyAfterTextualSuggestion))
        {
            $this->showPropertyAfterTextualSuggestion = (bool) Mage::getStoreConfig('bxSearch/autocomplete/category_suggestion_first');
        }

        return $this->showPropertyAfterTextualSuggestion;
    }

    /**
     * Check if the price is to be displayed in the template
     *
     * @return bool
     */
    public function showPrice()
    {
        if(is_null($this->showPrice))
        {
            $this->showPrice = (bool) Mage::getStoreConfig('bxSearch/autocomplete/show_price');
        }

        return $this->showPrice;
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
            if(Mage::helper('boxalino_intelligence')->isPluginEnabled() && Mage::helper('boxalino_intelligence')->isAutocompleteEnabled())
            {
                return true;
            }
        }

        return false;
    }
}
