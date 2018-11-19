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

    /**
     * Getting fields and definition
     *
     * @return array
     */
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
        $value = $this->getData('bx_q_optional');
        if(empty($value))
        {
            return 0;
        }

        return 1;
    }

    /**
     * If it is a multi-select question - skipping to next question can not be done automatically
     */
    public function isMultiselect()
    {
        $value =  $this->getData('bx_q_multiselect');
        if(empty($value))
        {
            return 0;
        }

        return 1;
    }

    /**
     * When this is true, the next question will load when the user selects an option
     * (recommended for checkboxes)
     * @return mixed
     */
    public function isAutoLoadeAllowed()
    {
        if($this->isMultiselect())
        {
            return 0;
        }

        return (int)$this->getData('bx_q_auto_response');
    }

    /**
     * When values are set for a question, if it is not a submit event (when all data gets validated for logged in/non-logged in customer),
     * a different action can be triggered (ex: validation) before moving to the next question/step
     * The action must be created in an extension, capable of processing ajax request
     *
     * @see Boxalino_Intelligence_ProfilerController::isCustomerAction()
     * @return mixed
     */
    public function getDispatchedAction()
    {
        return $this->getData('bx_q_event');
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
     * When a question triggers the submit event, the submit button will be displayed
     *
     * @return mixed
     */
    public function isSubmit()
    {
        return (int) $this->getData('bx_q_submit');
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

    public function getType()
    {

    }

    public function getCustomTemplate()
    {
        return $this->getData("bx_q_custom_template");
    }

}
