<?php

/**
 * Class Boxalino_Intelligence_Model_Narrative
 * The logic for creating narrative views should be reusable without having to call for adapter and other process-consuming steps
 * If the model processes ajax data, it means the data structure is json
 * If the data is being received as json, it is to be decoded first
 */
class Boxalino_Intelligence_Model_VisualElement_Renderer extends Varien_Object
{

    CONST NARRATIVE_RENDER_BLOCK_TYPE_KEY = "magento_block_type";
    CONST NARRATIVE_RENDER_BLOCK_TEMPLATE_KEY = "magento_block_function_setTemplate";
    CONST NARRATIVE_RENDER_BLOCK_SET_CHILD = "magento_block_function_setChild";
    CONST NARRATIVE_RENDER_BLOCK_NAME_KEY = "magento_block_name";
    CONST NARRATIVE_RENDER_BLOCK_FUNCTION = "magento_block_function_";

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
            if($parameter['name'] == self::NARRATIVE_RENDER_BLOCK_TYPE_KEY) {
                $type = reset($parameter['values']);
            } else if($parameter['name'] == self::NARRATIVE_RENDER_BLOCK_NAME_KEY) {
                $name = reset($parameter['values']);
            } else {
                if($parameter['name'] == self::NARRATIVE_RENDER_BLOCK_SET_CHILD){
                    $visualElements = $visualElement['subRenderings'][0]['rendering']['visualElements'];
                    $children = $this->createChildrenBlocks($visualElements, $parameter['values']);
                }
                $paramValues = $this->getDecodedValues($parameter['values']);
                if (strpos($parameter['name'], self::NARRATIVE_RENDER_BLOCK_FUNCTION) !== 0) {
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
     * @param $parameters
     * @return array
     */
    public function getVisualElementParameters($parameters)
    {
        $arguments = array();
        foreach ($parameters as $parameter) {
            if($parameter['name'] == self::NARRATIVE_RENDER_BLOCK_TYPE_KEY) {
                $arguments['block_type'] = reset($parameter['values']);
            } else if($parameter['name'] == self::NARRATIVE_RENDER_BLOCK_NAME_KEY) {
                $arguments['block_name'] = reset($parameter['values']);
            } else if($parameter['name'] == self::NARRATIVE_RENDER_BLOCK_TEMPLATE_KEY) {
                $arguments['template'] = reset($parameter['values']);
            } else {
                $paramValues = $this->getDecodedValues($parameter['values']);
                if (strpos($parameter['name'], self::NARRATIVE_RENDER_BLOCK_FUNCTION) !== 0) {
                    $paramValues = sizeof($paramValues) < 2 ? reset($paramValues) : $paramValues;
                }
                $arguments[$parameter['name']] = $paramValues;
            }
        }

        return $this->getLocaleValuesForParameter($arguments);
    }


    /**
     * Iteratively extract the localized values and set them on the field
     * @param $parameters
     * @return array
     */
    protected function getLocaleValuesForParameter($parameters)
    {
        $localizedParameters = array();
        $locale = $this->getLocale();
        foreach($parameters as $field=>$values)
        {
            $localizedParameters[$field] = $values;
            if(is_array($values))
            {
                if(isset($values[$locale]))
                {
                    $localizedParameters[$field]=$values[$locale];
                    continue;
                }
                $localizedParameters[$field] = $this->getLocaleValuesForParameter($values);
            }
        }

        return $localizedParameters;
    }


    /**
     * Decode json response (ex: in case of localized values)
     *
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
            if (strpos($command, self::NARRATIVE_RENDER_BLOCK_FUNCTION) === 0) {
                $function = substr($command, strlen(self::NARRATIVE_RENDER_BLOCK_FUNCTION));
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
     * The localized questions must be retrieved for the given store view (en, de, fr, it, etc)
     *
     * @return bool|string
     */
    public function getLocale() {
        return substr(Mage::getStoreConfig('general/locale/code'), 0, 2);
    }
}