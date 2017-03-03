<?php
/**
 * Class Boxalino_Intelligence_Block_Autocomplete
 */
class Boxalino_Intelligence_Block_Autocomplete extends Mage_CatalogSearch_Block_Autocomplete{

    /**
     * @return string
     */
    protected function _toHtml()
    {
        $html = '';
        $suggestData = array();
        if (!$this->_beforeToHtml()) {
            return $html;
        }
        $autocompleteHelper = new Boxalino_Intelligence_Helper_Autocomplete();
        try{
            $suggestData = Mage::helper('boxalino_intelligence')->getAdapter()->autocomplete(
                $query = $this->helper('catalogsearch')->getQueryText(),
                $autocompleteHelper
            );
        }catch(\Exception $e){
            Mage::logException($e);
        }

        $productHtml = '<ul class="products">';
        $globalProducts = isset($suggestData['global_products']) ? $suggestData['global_products'] : [];
        $show_price = Mage::getStoreConfig('bxSearch/autocomplete/show_price');

        foreach ($globalProducts as $product) {
            $global_html = '';
            $global_html = '<li title="global products" ';
            $global_html .= 'class="product-autocomplete global-products">';
            $global_html .= '<a href="'.$product['url'].'">';
            $global_html .= '<div class="product-image"><img src="'.$product['image'].'" alt="'.$this->escapeHtml($product['name']).'" /></div>';
            $global_html .= '<div class="product-title"><span>'.$this->escapeHtml($product['name']).'</span></div>';
            if ($show_price) {
                $global_html .= '<div class="product-price"><span>'.$this->escapeHtml($product['price']).'</span></div>';
            }
            $global_html .= '</a></li>';
            $productHtml .= $global_html;

        }
        unset($suggestData['global_products']);
        $html .= '<ul class="queries"><li style="display:none"></li>';
        $suggestionHtml = '';
        if(count($suggestData)){
            foreach($suggestData as $index => $item){
                $suggestionHtml .= '<li data-word="'.$item['hash'].'" title="'.$this->escapeHtml($item['title']).'"';
                $suggestionHtml .= ' class="acsuggestion">';
                $suggestionHtml .= '<span class"query-title">'.$item['highlighted'].'</span>';
                $suggestionHtml .= '<span class="amount">('.$item['num_results'].')</span></li>';
                if (isset($item['categories']) && count($item['categories'])) {
                    $catalogSearchHelper = Mage::helper('catalogsearch');
                    $resultUrl = $catalogSearchHelper->getResultUrl();
                    foreach ($item['categories'] as $category){
                        if (!isset($category['num_results'])) {
                            $category['num_results'] = 0;
                        }
                        $suggestionHtml .= '<li class="facet"><a href="' . $resultUrl . '?q=' . $item['title'] . '&bx_category_id=' . $category['id'] . '" class="facet"><span class="query-title">' . $this->__("in %s", $category['title']) . '</span>';
                        $suggestionHtml .= '<span class="amount">('.$category['num_results'].')</span></li></a>';
                    }
                }
                foreach($item['products'] as $product){
                    $productHtml .= $this->generateProductHtml($product, $item, false);
                }
            }
        }
        $html .= $suggestionHtml . '</ul>' . $productHtml . '</ul>';
        return  $html;
    }

    private function generateProductHtml($product, $item) {
        ;
        $html = '<li title="'.$this->escapeHtml($item['title']).'" style="display:none" ';
        $html .= 'class="product-autocomplete" data-word="'.$item['hash'].'">';
        $html .= '<a href="'.$product['url'].'">';
        $html .= '<div class="product-image"><img src="'.$product['image'].'" alt="'.$this->escapeHtml($product['name']).'" /></div>';
        $html .= '<div class="product-title"><span>'.$this->escapeHtml($product['name']).'</span></div>';
        if (Mage::getStoreConfig('bxSearch/autocomplete/show_price')) {
            $html .= '<div class="product-price"><span>'.$this->escapeHtml($product['price']).'</span></div>';
        }
        $html .= '</a></li>';
        return $html;
    }
}
