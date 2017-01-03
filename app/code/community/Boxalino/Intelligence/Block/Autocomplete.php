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
      

        $html .= '<ul class="queries"><li style="display:none"></li>';
        $suggestionHtml = '';
        $productHtml = '<ul class="products">';
        $first = true;
        if(count($suggestData)){
            foreach($suggestData as $index => $item){
                $suggestionHtml .= '<li data-word="'.$item['hash'].'" title="'.$this->escapeHtml($item['title']).'"';
                $suggestionHtml .= ' class="acsuggestion">';
                $suggestionHtml .= '<span class"query-title">'.$this->escapeHtml($item['title']).'</span>';
                $suggestionHtml .= '<span class="amount">('.$item['num_results'].')</span></li>';

                foreach($item['products'] as $product){
                    $productHtml .= '<li title="'.$this->escapeHtml($item['title']).'"';
                    if(!$first){
                        $productHtml.= ' style="display:none" ';
                    }
                    $productHtml .= 'class="product-autocomplete" data-word="'.$item['hash'].'">';
                    $productHtml .= '<a href="'.$product['url'].'">';
                    $productHtml .= '<div class="product-image"><img src="'.$product['image'].'" alt="'.$this->escapeHtml($product['name']).'" /></div>';
                    $productHtml .= '<div class="product-title"><span>'.$this->escapeHtml($product['name']).'</span></div>';
                    $productHtml .= '</a></li>';
                }
                $first = false;
            }
        }
        $html .= $suggestionHtml . '</ul>' . $productHtml . '</ul>';
        return  $html;
    }
}
