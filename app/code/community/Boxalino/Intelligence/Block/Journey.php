<?php

/**
 * Class Boxalino_Intelligence_Block_Banner
 */
Class Boxalino_Intelligence_Block_Journey extends Mage_Core_Block_Template
{

    protected $bxHelperData;
    protected $p13nHelper;
    protected $renderer;

    public function _construct()
    {
        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        $this->renderer = Mage::getModel("boxalino_intelligence/visualElement_renderer");
        $this->p13nHelper = $this->bxHelperData->getAdapter();

        parent::_construct();
        if(!is_null($this->getData('choice'))) {
            $replaceMain = is_null($this->getData('replace_main')) ? true : $this->getData('replace_main');
            $this->p13nHelper->getNarratives($this->getData('choice'), $this->getData('additional_choices'), $replaceMain, false);
        }
    }

    public function renderDependencies()
    {
        $html = '';
        $replaceMain = is_null($this->getData('replace_main')) ? true : $this->getData('replace_main');
        $dependencies = $this->p13nHelper->getNarrativeDependencies($this->getData('choice'), $this->getData('additional_choices'), $replaceMain);
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
        $replaceMain = is_null($this->getData('replace_main')) ? true : $this->getData('replace_main');
        $narratives = $this->p13nHelper->getNarratives($this->getData('choice'), $this->getData('additional_choices'), $replaceMain);
        foreach ($narratives as $visualElement) {
            if($this->checkVisualElementForParameter($visualElement['visualElement'], 'position', $position)) {
                try {
                    $block = $this->renderer->createVisualElement($visualElement['visualElement']);
                    if ($block) {
                        $block->setChoice($this->getData('choice'));
                        $block->setAdditionalChoice($this->getData('additional_choices'));
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
