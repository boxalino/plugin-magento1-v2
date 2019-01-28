<?php

/**
 * The question block is being generated via ajax by setting properties based on the request
 * All questions fields are defined as subrenderings on the main element
 */
class Boxalino_Intelligence_Block_Journey_Profiler_Question extends Boxalino_Intelligence_Block_Journey_General
{

    protected $fields = array();

    /**
     * Getting fields and definition
     * Fields are set as subrenderings on a question narrative
     *
     * @return array(Varien_Object())
     */
    public function getFields()
    {
        foreach($this->getSubRenderings() as $field)
        {
            $fieldObject = $this->prepareFieldDefinitionBySubrendering($field);
            $this->addField($fieldObject->getName(), $fieldObject);
        }

        return $this->fields;
    }


    /**
     * Using a subrendering (array) - a field object is created
     * To be left until the narrative is rendered as object
     *
     * @param array $subrendering
     * @return Varien_Object
     */
    protected function prepareFieldDefinitionBySubrendering($subrendering)
    {
        $field = $this->getDefaultFieldByDefinition();
        $parameters = array();
        if(is_array($subrendering) && isset($subrendering["visualElement"]))
        {
            $parameters = $this->renderer->getVisualElementParameters($subrendering['visualElement']['parameters']);
        }

        return $field->addData($parameters);
    }

    /**
     * means that the question is optional and can be skipped
     * in this case, the skip/next button to be displayed
     */
    public function isSkipAllowed()
    {
        if($this->isSubmit())
        {
            return 0;
        }

        return $this->getData('bx_q_optional');
    }

    /**
     * When this is true, the next question will load when the user selects an option
     * (recommended for checkboxes)
     * @return mixed
     */
    public function isAutoloadAllowed()
    {
        if($this->isSubmit())
        {
            return 0;
        }

        return $this->getData('bx_q_auto_response');
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
     * Getting question value based on locale
     *
     * @return mixed
     */
    public function getQuestion()
    {
        $questionData =  $this->getData('bx_q_question');
        if(is_array($questionData))
        {
            $locale = $this->renderer->getLocale();
            return $questionData[$locale];
        }

        return $questionData;
    }

    /**
     * Dynamically adds fields to the template
     * @param $code string => attribute code
     * @param  Varien_Object $field
     * @return Boxalino_Intelligence_Block_Journey_Profiler_Question
     */
    public function addField($code, $field)
    {
        if(isset($this->fields[$code]))
        {
            return $this;
        }

        $this->fields[$code] = $field;
        return $this;
    }

    /**
     * Skeleton for a field object
     *
     * @data name | attribute code, field input name
     * @data type | text, password, submit, reset, radio, checkbox, color, date, email, range, datetime-local, hidden
     * @data array $options | values for the field/input (array for checkboxes,
     * @data string $attributes | other field properties -- they`re going to be rendered inline in the tag
     * @data string $placeholder | applies for textarea and text fields
     * @data string $template | phtml template to load the content
     * @data string $block_type | block type
     * @data string $block_name | block name in case it is referenced in layout/phtml
     * @return Varien_Object
     */
    public function getDefaultFieldByDefinition()
    {
        return new Varien_Object(
            [
                "name"=>'',
                "type"=>'',
                'options'=> array(),
                "placeholder"=> "",
                "template"=> 'boxalino/journey/profiler/field/text.phtml',
                "block_type"=> 'core/template',
                "block_name" => "",
                "label" => "",
                "attributes" => ""
            ]
        );
    }

}
