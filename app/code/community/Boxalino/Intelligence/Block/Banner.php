<?php

/**
 * Class Boxalino_Intelligence_Block_Banner
 */
Class Boxalino_Intelligence_Block_Banner extends Mage_Core_Block_Template{

  protected $bxHelperData;
  protected $p13nHelper;

  public function _construct()
  {
    $this->bxHelperData = Mage::helper('boxalino_intelligence');
    $this->p13nHelper = $this->bxHelperData->getAdapter();

    parent::_construct();
  }

  protected function _prepareData(){

        return $this;
    }

  protected function isBannerEnabled(){

    return $this->bxHelperData->isBannerEnabled();

  }

  protected function prepareRecommendations(){
    $this->p13nHelper->getRecommendation('banner', array(), 'banner', 15, 15, true, array('title', 'products_bxi_bxi_jssor_slide', 'products_bxi_bxi_jssor_transition', 'products_bxi_bxi_name', 'products_bxi_bxi_jssor_control', 'products_bxi_bxi_jssor_break'));
    }

    public function getBannerSlides() {

        $slides = $this->p13nHelper->getResponse()->getHitFieldValues(array('products_bxi_bxi_jssor_slide', 'products_bxi_bxi_name'), $this->_data['widget']);
        $counters = array();
        foreach($slides as $id => $vals) {
            $slides[$id]['div'] = $this->getBannerSlide($id, $vals, $counters);
        }
        return $slides;
    }

    public function getBannerSlide($id, $vals, &$counters) {
        $language = $this->bxHelperData->getLanguage();
        if(isset($vals['products_bxi_bxi_jssor_slide']) && sizeof($vals['products_bxi_bxi_jssor_slide']) > 0) {
            $json = $vals['products_bxi_bxi_jssor_slide'][0];

            $slide = json_decode($json, true);
            if(isset($slide[$language])) {
                $json = $slide[$language];

                for($i=1; $i<10; $i++) {

                    if(!isset($counters[$i])) {
                        $counters[$i] = 0;
                    }

                    $pieces = explode('BX_COUNTER'.$i, $json);
                    foreach($pieces as $j => $piece) {
                        if($j >= sizeof($pieces)-1) {
                            continue;
                        }
                        $pieces[$j] .= $counters[$i]++;
                    }
                    $json = implode('', $pieces);

                }
                return $json;
            }
        }
        return '';
    }

    public function getBannerJssorSlideGenericJS($key) {
        $language = $this->bxHelperData->getLanguage();

        $slides = $this->p13nHelper->getResponse()->getHitFieldValues(array($key), $this->_data['widget']);

        $jsArray = array();
        foreach($slides as $id => $vals) {
            if(isset($vals[$key]) && sizeof($vals[$key]) > 0) {

                $jsons = json_decode($vals[$key][0], true);
                if(isset($jsons[$language])) {
                    $json = $jsons[$language];

                    //fix some special case an extra '}' appears wrongly at the end
                    $minus = 2;
                    if(substr($json, strlen($json)-1, 1) == '}') {
                        $minus = 3;
                    }

                    //removing the extra [] around
                    $json = substr($json, 1, strlen($json)-$minus);

                    $jsArray[] = $json;
                }
            }
        }

        return '[' . implode(',', $jsArray) . ']';
    }

    public function getBannerJssorSlideTransitions() {

        return $this->getBannerJssorSlideGenericJS('products_bxi_bxi_jssor_transition');

    }

    public function getBannerJssorSlideBreaks() {

        return $this->getBannerJssorSlideGenericJS('products_bxi_bxi_jssor_break');

    }

    public function getBannerJssorSlideControls() {

        return $this->getBannerJssorSlideGenericJS('products_bxi_bxi_jssor_control');

    }

    public function getBannerJssorOptions() {

        $bannerJssorOptions = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_options');

        return $bannerJssorOptions;
    }

    public function getBannerJssorId() {

        $bannerJssorId = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_id');

        return $bannerJssorId;
    }

    public function getBannerJssorStyle() {

        $bannerJssorMaxWidth = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_style');

        return $bannerJssorMaxWidth;
    }

    public function getBannerJssorSlidesStyle() {

        $bannerJssorMaxWidth = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_slides_style');

        return $bannerJssorMaxWidth;
    }

    public function getBannerJssorMaxWidth() {

        $bannerJssorMaxWidth = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_max_width');

        return $bannerJssorMaxWidth;
    }

    public function getBannerJssorCSS() {

        $bannerJssorCss = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_css');

        return str_replace("JSSORID", $this->getBannerJssorId(), $bannerJssorCss);
    }

    public function getBannerJssorLoadingScreen() {

        $bannerJssorLoadingScreen = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_loading_screen');

        return $bannerJssorLoadingScreen;
    }

    public function getBannerJssorBulletNavigator() {

        $bannerJssorBulletNavigator = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_bullet_navigator');

        return $bannerJssorBulletNavigator;

    }

    public function getBannerJssorArrowNavigator() {

        $bannerJssorArrowNavigator = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_arrow_navigator');

        return $bannerJssorArrowNavigator;

    }

    public function getBannerFunction() {

        $bannerFunction = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_function');

        return $bannerFunction;

    }

    public function getHitCount(){

      $hitCount = sizeof($this->p13nHelper->getResponse()->getHitIds());

      return $hitCount;

    }

}
