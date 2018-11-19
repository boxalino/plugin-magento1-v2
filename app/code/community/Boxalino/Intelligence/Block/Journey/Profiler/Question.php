<?php

/**
 * The question block is being generated via ajax by setting properties based on the request
 * Required properties are:
 * 1. block type
 * 2. block name
 * 3. data (made of: attribute code. is question to be skipped, is multiselect, is auto response enabled,
 * 4. multiple fields
 */
class Boxalino_Intelligence_Block_Journey_Profiler_Question extends Boxalino_Intelligence_Block_Journey_General
{

    /**
     * Attribute code is also the input name
     * @return mixed
     */
    public function getAttributeCode()
    {
        return $this->getData('bx_q_attribute_code');
    }


    public function getType()
    {

    }

    public function getFields()
    {
        return [];
    }

    /**
     * means that the question is optional and can be skipped
     * in this case, the skip/next button to be displayed
     */
    public function isSkipAllowed()
    {
        $value =  $this->getData('bx_q_optional');
        if(empty($value))
        {
            return false;
        }
    }

    /**
     * If it is a multi-select question - skipping to next question can not be done automatically
     */
    public function isMultiselect()
    {
        $value =  $this->getData('bx_q_multiselect');
        if(empty($value))
        {
            return false;
        }
    }

    /**
     * When this is true, the next question will load when the user selects an option
     * (recommended for checkboxes)
     * @return mixed
     */
    public function isAutoResponseAllowed()
    {
        if($this->isMultiselect())
        {
            return false;
        }

        return $this->getData('bx_q_auto_response');
    }

    public function getDispatchedEvent()
    {
        return $this->getData('bx_q_event');
    }

    /**
     * The question is localized (even for single store views)
     */
    public function getQuestion()
    {

    }

    /**
     * The retrieved options are localized (even for single store views)
     */
    public function getOptions()
    {

    }

    /**
     * Element index describes the order of the question
     * conditional order to be added later
     *
     * @return mixed
     */
    public function getElementIndex() {
        return $this->getData('bx_index');
    }


    /**
     * Dynamically adds fields to the template
     *
     * @param $name | attribute code
     * @param string $tag | input, textarea, select, button
     * @param string $type | text, password, submit, reset, radio, checkbox, color, date, email, range, datetime-local, hidden
     * @param array $values | values for the field/input (array for checkboxes,
     * @param array $properties | other field properties -- they`re going to be rendered inline in the tag
     * @param string $placeholder | applies for textarea and text fields
     * @param string $template | phtml template to load the content
     */
    public function addField($name, $tag="input", $type="text", $values=array(), $properties=array(), $placeholder="", $template="")
    {

    }

    public function getCustomTemplate()
    {
        return $this->getData("bx_q_custom_template");
    }

}
