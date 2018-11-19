<?php

/**
 * Class Boxalino_Intelligence_Model_Narrative
 * The logic for creating narrative views should be reusable without having to call for adapter and other process-consuming steps
 * If the model processes ajax data, it means the data structure is json
 * If the data is being received as json, it is to be decoded first
 */
class Boxalino_Intelligence_Model_VisualElement_Renderer extends Varien_Object
{

    /**
     * @var false|Mage_Core_Model_Abstract
     */
    protected $layout;

    /**
     * @var bool
     */
    protected $isJson = false;

    /**
     * Boxalino_Intelligence_Model_Narrative constructor.
     */
    public function __construct()
    {
        $this->layout = Mage::getModel("core/layout");
        parent::__construct();
    }

    /**
     * Creating a block from a visual element
     * The visual element received can either be a string(json) or array
     *
     * @param $visualElement
     * @param null $additional_parameter
     * @return mixed
     */
    public function createVisualElement($visualElement, $additional_parameter = null)
    {
        if(is_array($visualElement))
        {
            return $this->createBlockElement($visualElement, $additional_parameter);
        }

        $element = json_decode($visualElement, true);
        return $this->createBlockElement($element['visualElement'], $additional_parameter);

    }

    /**
     * Gets subrenderings for a visual element
     *
     * @param $visualElement
     * @return array of decoded values of
     */
    public function getSubRenderingsByVisualElement($visualElement)
    {
        if(!is_array($visualElement))
        {
            $visualElement = json_decode($visualElement, true)['visualElement'];
        }

        if(isset($visualElement['subRenderings'][0]['rendering']['visualElements'])) {
            return $visualElement['subRenderings'][0]['rendering']['visualElements'];
        }

        return array();
    }

    protected function createBlockElement($visualElement, $additional_parameter = null)
    {
        $parameters = $visualElement['parameters'];
        $arguments = array();
        $children = array();
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

    /**
     * @param $values
     * @return array
     */
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

    /**
     * @param $string
     * @return bool
     */
    protected function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * Creates a block
     * The block definition must be used by a block structure to be displayed
     *
     * @param $type
     * @param $name
     * @param $data
     * @param $arguments
     * @return mixed
     */
    protected function createBlock($type, $name, $data, $arguments, $children=array())
    {
        $block = $this->layout->createBlock($type, $name, $data);
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

    /**
     * For ajax rendering, the block values should not be decoded
     * @param $value
     * @return $this
     */

    public function setIsJson($value)
    {
        $this->isJson = $value;
        return $this;
    }

    public function getIsJson()
    {
        return $this->isJson;
    }
}