<?php

/**
 * Class Boxalino_Intelligence_Block_Product_List_Blog
 */
class Boxalino_Intelligence_Block_Product_List_Parametrized extends Boxalino_Intelligence_Block_Recommendation
{

    protected $bxHelperData;

    protected $p13nHelper;

    public function _construct()
    {
        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        $this->p13nHelper = $this->bxHelperData->getAdapter();

        parent::_construct();
        parent::init($this->getChoiceId(), $this->getScenario());
    }

    public function getChoiceId()
    {
        return $this->getRequest()->getParam('bx_choice');
    }

    public function getMin()
    {
        return $this->getRequest()->getParam('bx_min');
    }

    public function getMax()
    {
        return $this->getRequest()->getParam('bx_max');
    }

    public function getFormat()
    {
        return $this->getRequest()->getParam('format');
    }

    public function getScenario()
    {
        return 'parametrized';
    }

    public function getLanguage()
    {
        return $this->bxHelperData->getLanguage();
    }

    public function getReturnFields()
    {
        return explode(',', $this->getRequest()->getParam('bx_returnfields'));
    }

    public function getCmsRecommendationBlocks($content)
    {
        $recs = array();
        $recs[] = array(
            'widget'=>$this->getChoiceId(),
            'context'=> 'products',
            'scenario'=>$this->getScenario(),
            'min'=>$this->getMin(),
            'max'=>$this->getMax()
        );
        $this->_data['widget'] = $this->getChoiceId();
        return $recs;
    }

}
