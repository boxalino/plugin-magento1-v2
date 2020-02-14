<?php

/**
 * Class Boxalino_Intelligence_Block_Banner
 */
Class Boxalino_Intelligence_Block_Banner extends Mage_Core_Block_Template
{
    protected $bxHelperData;
    protected $p13nHelper;

    public function _construct()
    {
        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        $this->p13nHelper = $this->bxHelperData->getAdapter();

        parent::_construct();
    }

    protected function _prepareData()
    {
        return $this;
    }

    protected function prepareRecommendations()
    {
        $vals = $this->getValues();
        $this->p13nHelper->getRecommendation($vals['choiceID'], array(), 'banner', $vals['min'], $vals['max'], true, array('title', 'products_bxi_bxi_jssor_slide', 'products_bxi_bxi_jssor_transition', 'products_bxi_bxi_name', 'products_bxi_bxi_jssor_control', 'products_bxi_bxi_jssor_break'));
    }

    protected function isActive()
    {
        return $this->bxHelperData->isBannerEnabled();
    }

    public function getValues()
    {
        if (!empty($this->getData('choiceID'))) {
            $vals['choiceID'] = strVal($this->getData('choiceID'));
        } else {
            $vals['choiceID'] = 'banner';
        }

        if (!empty($this->getData('min'))) {
            $vals['min'] = intval($this->getData('min'));
        } else {
            $vals['min'] = 1;
        }

        if (!empty($this->getData('max'))) {
            $vals['max'] = intval($this->getData('max'));
        } else {
            $vals['max'] = 1;
        }

        if (!empty($this->getData('jssorID'))) {
            $vals['jssorID'] = strVal($this->getData('jssorID'));
        } else {
            $vals['jssorID'] = 'jssor_1';
        }

        if (!empty($this->getData('jssorIndex'))) {
            $vals['jssorIndex'] = strVal($this->getData('jssorIndex'));
        } else {
            $vals['jssorIndex'] = '1';
        }

        return $vals;
    }

    public function check()
    {
        $values = array(
            0 => $this->getBannerSlides(),
            1 => $this->getBannerJssorId(),
            2 => $this->getBannerJssorSlideTransitions(),
            3 => $this->getBannerJssorSlideBreaks(),
            4 => $this->getBannerJssorSlideControls(),
            5 => $this->getBannerJssorOptions(),
            6 => $this->getBannerJssorMaxWidth(),
            7 => $this->getBannerJssorCSS(),
            8 => $this->getBannerJssorStyle(),
            9 => $this->getBannerJssorLoadingScreen(),
            10 => $this->getBannerJssorSlidesStyle(),
            11 => $this->getBannerJssorBulletNavigator(),
            12 => $this->getBannerJssorArrowNavigator(),
            13 => $this->getBannerFunction(),
            14 => $this->getBannerLayout()
        );

        if (!in_array('', $values)) {
            return true;
        }else{
            return false;
        }
    }

    public function getBannerSlides()
    {
        $configValues = $this->getValues();

        $slides = $this->p13nHelper->getResponse()->getHitFieldValues(array('products_bxi_bxi_jssor_slide', 'products_bxi_bxi_name'), $configValues['choiceID']);
        $counters = array();
        foreach($slides as $id => $vals) {
            $slides[$id]['div'] = $this->getBannerSlide($id, $vals, $counters);
        }

        // if the small banner is used, use the first banner for the first block & the second for the second
        if ($this->getBannerLayout() == 'small') {

            if ($configValues['jssorIndex'] == '1') {
                return array(reset($slides));
            }
            if ($configValues['jssorIndex'] == '2') {
                return array(end($slides));
            }
        }

        return $slides;
    }

    public function getBannerSlide($id, $vals, &$counters)
    {
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

    public function getBannerJssorSlideGenericJS($key)
    {
        $language = $this->bxHelperData->getLanguage();
        $vals = $this->getValues();
        $slides = $this->p13nHelper->getResponse()->getHitFieldValues(array($key), $vals['choiceID']);

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

    public function getBannerJssorSlideTransitions()
    {
        return $this->getBannerJssorSlideGenericJS('products_bxi_bxi_jssor_transition');
    }

    public function getBannerJssorSlideBreaks()
    {
        return $this->getBannerJssorSlideGenericJS('products_bxi_bxi_jssor_break');
    }

    public function getBannerJssorSlideControls()
    {
        return $this->getBannerJssorSlideGenericJS('products_bxi_bxi_jssor_control');
    }

    public function getBannerJssorOptions()
    {
        $bannerJssorOptions = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_options');
        return $bannerJssorOptions;
    }

    public function getBannerJssorId()
    {
        $bannerJssorId = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_id');
        return $bannerJssorId;
    }

    public function getBannerJssorStyle()
    {
        $bannerJssorMaxWidth = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_style');
        return $bannerJssorMaxWidth;
    }

    public function getBannerJssorSlidesStyle()
    {
        $bannerJssorMaxWidth = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_slides_style');
        return $bannerJssorMaxWidth;
    }

    public function getBannerJssorMaxWidth()
    {
        $bannerJssorMaxWidth = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_max_width');
        return $bannerJssorMaxWidth;
    }

    public function getBannerJssorCSS()
    {
        $bannerJssorCss = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_css');
        return str_replace("JSSORID", $this->getBannerJssorId(), $bannerJssorCss);
    }

    public function getBannerJssorLoadingScreen()
    {
        $bannerJssorLoadingScreen = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_loading_screen');
        return $bannerJssorLoadingScreen;
    }

    public function getBannerJssorBulletNavigator()
    {
        $bannerJssorBulletNavigator = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_bullet_navigator');
        return $bannerJssorBulletNavigator;
    }

    public function getBannerJssorArrowNavigator()
    {
        $bannerJssorArrowNavigator = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_arrow_navigator');
        return $bannerJssorArrowNavigator;
    }

    public function getBannerFunction()
    {
        $bannerFunction = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_function');
        return $bannerFunction;
    }

    public function getBannerLayout()
    {
        $bannerLayout = $this->p13nHelper->getResponse()->getExtraInfo('banner_jssor_layout');
        return $bannerLayout;
    }

    public function getBannerTitle()
    {
        $vals = $this->getValues();
        $bannerTitle = $this->p13nHelper->getClientResponse()->getResultTitle($vals['choiceID']);

        return $bannerTitle;
    }

    public function getHitCount()
    {
        $hitCount = sizeof($this->p13nHelper->getResponse()->getHitIds());
        return $hitCount;
    }

}
