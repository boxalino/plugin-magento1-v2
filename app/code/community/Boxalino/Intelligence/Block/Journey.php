<?php

/**
 * Class Boxalino_Intelligence_Block_Banner
 */
Class Boxalino_Intelligence_Block_Journey extends Mage_Core_Block_Template
{

    protected $bxHelperData;
    protected $p13nHelper;

    public function _construct()
    {
        $this->bxHelperData = Mage::helper('boxalino_intelligence');
        $this->p13nHelper = $this->bxHelperData->getAdapter();

        parent::_construct();
        if(!is_null($this->getData('choice'))) {
            $replaceMain = is_null($this->getData('replace_main')) ? true : $this->getData('replace_main');
            $this->p13nHelper->getNarratives($this->getData('choice'), $this->getData('additional_choices'), $replaceMain, false);
        }
    }

    protected function createBlock($type, $name, $data, $arguments, $children)
    {
        $block = $this->getLayout()->createBlock($type, $name, $data);
        foreach ($arguments as $command => $argument) {
            $block->setData($command, $argument);
            if (strpos($command, 'magento_block_function_') === 0) {
                $function = substr($command, strlen('magento_block_function_'));
                foreach ($argument as $value) {
                    $args = array();
                    if ($function == 'setData') {
                        $args = json_decode($value, true);
                        call_user_func(array($block, $function), $args);
                    } else {
                        if ($function == 'setChild') {
                            if(!isset($children[$value])) continue;
                            $args[] = $value;
                            $args[] = $children[$value];
                        } else {
                            $args[] = $value;
                        }
                        call_user_func_array(array($block, $function), $args);
                    }
                }
            }
        }
        return $block;
    }

    protected function createChildrenBlocks($visualElements, $childNames)
    {
        $children = array();
        foreach ($visualElements as $visualElement) {
            foreach($visualElement['visualElement']['parameters'] as $parameter) {
                if($parameter['name'] == 'magento_block_name' && in_array(reset($parameter['values']), $childNames)) {
                    $children[reset($parameter['values'])] = $this->createBlockElement($visualElement['visualElement']);
                    break;
                }
            }
        }
        return $children;
    }


    protected function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }


    protected function getDecodedValues($values)
    {
        if(is_array($values)) {
            foreach ($values as $i => $value) {
                if($this->isJson($value)) {
                    $values[$i] = json_decode($value, true);
                }
            }
        }
        return $values;
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
                    $block = $this->createVisualElement($visualElement['visualElement']);
                    if ($block) {
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

    public function createVisualElement($visualElement, $additional_parameter = null)
    {
        return $this->createBlockElement($visualElement, $additional_parameter);
    }

    protected function createBlockElement($visualElement, $additional_parameter = null)
    {
        $parameters = $visualElement['parameters'];
        $children = array();
        $arguments = array();
        $type = '';
        $name = '';
        $data = ['bxVisualElement' => $visualElement];
        foreach ($parameters as $parameter) {
            if($parameter['name'] == 'magento_block_type') {
                $type = reset($parameter['values']);
            } else if($parameter['name'] == 'magento_block_name') {
                $name = reset($parameter['values']);
            } else {
                if($parameter['name'] == 'magento_block_function_setChild'){
                    $visualElements = $visualElement['subRenderings'][0]['rendering']['visualElements'];
                    $children = $this->createChildrenBlocks($visualElements, $parameter['values']);
                }
                $paramValues = $this->getDecodedValues($parameter['values']);
                if (strpos($parameter['name'], 'magento_block_function_') !== 0) {
                    $paramValues = sizeof($paramValues) < 2 ? reset($paramValues) : $paramValues;
                }
                $arguments[$parameter['name']] = $paramValues;
            }
        }
        if(is_array($additional_parameter)) {
            $data = array_merge($data, $additional_parameter);
        }
        return $this->createBlock($type, $name, $data, $arguments, $children);
    }

}
