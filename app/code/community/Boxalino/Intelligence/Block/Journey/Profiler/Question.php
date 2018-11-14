<?php

/**
 * The question block is being generated via ajax by setting properties based on the request
 *
 */
class Boxalino_Intelligence_Block_Journey_Profiler_Question extends Boxalino_Intelligence_Block_Journey_General
{

    public function getAttributeCode()
    {

    }

    public function getTemplate()
    {

    }

    public function getType()
    {

    }

    /**
     * means that the question is optional and can be skipped
     * in this case, the skip/next button to be displayed
     */
    public function isSkipAllowed()
    {

    }

    /**
     * If it is a multi-select question - skipping to next question can not be done automatically
     */
    public function isMultiselect()
    {

    }

    public function isAutoResponseAllowed()
    {

    }

    public function getLocalizedQuestionText()
    {

    }

    public function getLocalizedOptions()
    {

    }

    public function getElementIndex() {
        return $this->getData('bx_index');
    }

}
