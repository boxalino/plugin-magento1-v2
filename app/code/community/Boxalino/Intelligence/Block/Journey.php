<?php

/**
 * Class Boxalino_Intelligence_Block_Banner
 */
Class Boxalino_Intelligence_Block_Journey extends Mage_Core_Block_Template
{

    CONST BX_NARRATIVE_CHOICE_VAR = "choice";
    CONST BX_NARRATIVE_ADDITIONAL_CHOICE_VAR = "additional_choices";
    CONST BX_NARRATIVE_MAIN_VAR = "replace_main";
    CONST BX_NARRATIVE_EVENT_VAR = "narrative_call";
    CONST BX_NARRATIVE_EXTENDED_REQUEST_VAR = "extended_request";

    protected $bxHelperData;
    protected $p13nHelper;
    protected $renderer;

    public function _construct()
    {
        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        $this->renderer = Mage::getModel("boxalino_intelligence/visualElement_renderer");
        $this->p13nHelper = $this->bxHelperData->getAdapter();

        parent::_construct();

        $choice = $this->getData(self::BX_NARRATIVE_CHOICE_VAR);
        $additionalChoice = $this->getData(self::BX_NARRATIVE_ADDITIONAL_CHOICE_VAR);
        $isMain = is_null($this->getData(self::BX_NARRATIVE_MAIN_VAR)) ? true : $this->getData(self::BX_NARRATIVE_MAIN_VAR);
        $extended = is_null($this->getData(self::BX_NARRATIVE_EXTENDED_REQUEST_VAR)) ? false : $this->getData(self::BX_NARRATIVE_EXTENDED_REQUEST_VAR);

        if($extended)
        {
            $narrativeInfo = new Varien_Object(array('choice' => $choice, 'additional'=> $additionalChoice, 'context'=>""));
            Mage::dispatchEvent('boxalino_block_narrative_construct', array('request' => $narrativeInfo));
        }

        if(!is_null($choice)) {
            $replaceMain = is_null($isMain) ? true : $isMain;
            $this->p13nHelper->getNarratives($choice, $additionalChoice, $replaceMain, false, $extended);
            $this->setReplaceMain($replaceMain);
            $this->setAdditionalChoices($additionalChoice);
            $this->setExtended($extended);
            $this->setChoice($choice);
        }
    }

    public function renderDependencies()
    {
        $html = '';
        $dependencies = $this->p13nHelper->getNarrativeDependencies($this->getChoice(), $this->getAdditionalChoices(), $this->getReplaceMain());
        if(isset($dependencies['js'])) {
            foreach ($dependencies['js'] as $js) {
                $url = $js;
                $html .= $this->getDependencyElement($url, 'js');
            }
        }
        if(isset($dependencies['css'])) {
            foreach ($dependencies['css'] as $css) {
                $url = $css;
                $html .= $this->getDependencyElement($url, 'css');
            }
        }

        return $html;
    }

    protected function getDependencyElement($url, $type)
    {
        if($type == 'css'){
            return "<link href=\"{$url}\" type=\"text/css\" rel=\"stylesheet\" />";
        }

        if($type == 'js') {
            return"<script src=\"{$url}\" type=\"text/javascript\"></script>";
        }

        return '';
    }

    public function renderElements()
    {
        $html = '';
        $position = $this->getData('position');
        $narratives = $this->p13nHelper->getNarratives($this->getChoice(), $this->getAdditionalChoices(), $this->getReplaceMain());
        foreach ($narratives as $visualElement) {
            if($this->checkVisualElementForParameter($visualElement['visualElement'], 'position', $position)) {
                try {
                    $block = $this->renderer->createVisualElement($visualElement['visualElement']);
                    if ($block) {
                        $block->setChoice($this->getChoice());
                        $block->setAdditionalChoice($this->getAdditionalChoices());
                        $html .= $block->toHtml();
                    }
                } catch (\Exception $e) {
                    Mage::logException($e);
                }
            }
        }
        return $html;
    }

    public function checkVisualElementForParameter($visualElement, $key, $value)
    {
        foreach ($visualElement['parameters'] as $parameter) {
            if($parameter['name'] == $key && in_array($value, $parameter['values'])) {
                return true;
            }
        }
        return false;
    }

}
