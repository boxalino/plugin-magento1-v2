<?php

/**
 * Class Boxalino_Intelligence_Block_Journey_Profiler
 * Narrative profiler is used in the following way:
 * 1. it keeps a list of subrenderings(questions) of different types which are rendered via ajax on option select/skip action
 * 2.
 */
class Boxalino_Intelligence_Block_Journey_Profiler extends Boxalino_Intelligence_Block_Journey_General
    implements Boxalino_Intelligence_Block_Journey_CPOJourney
{

    protected $bxAdapter = null;

    /**
     * If the questions are to be rendered via ajax - they should not be decoded
     * If the questions are to be rendered server-side - they should be decoded
     */
    public function getQuestions()
    {
        if($this->getData('bx_p_use_ajax') === true)
        {
           return json_encode($this->getSubRenderings());
        }

        return $this->getSubRenderings();
    }

    /**
     * Setting if the progress to be displayed or not
     * @return mixed
     */
    public function displayProgress()
    {
        return $this->getData("bx_p_show_progress");
    }

    /**
     * Action used to load each question via ajax
     *
     * @return string
     */
    public function getLoadUrl()
    {
        if($this->getData("bx_p_load_url"))
        {
            return Mage::getBaseUrl() . $this->getData('bx_p_load_url');
        }

        return $this->getUrl("boxalinointelligence/profiler/loadQuestion");
    }

    /**
     * Action used to save all the information
     *
     * @return string
     */
    public function getSubmitUrl()
    {
        if($this->getData("bx_p_onSave_action"))
        {
            return Mage::getBaseUrl() . $this->getData('bx_p_onSave_action');
        }

        return $this->getUrl("boxalinointelligence/profiler/save");
    }

    /**
     * When the profiler data is to be saved, a hook is to be created for the event
     * Since there can be multiple profiler implementations in the system, this feature is configurable
     */
    public function getSubmitEventCustomer()
    {
        return "bx_p_event_customer";
    }

    /**
     * Dispatched event when a visitor submits information (this means that a secondary action/validation must take place)
     */
    public function getSubmitEventVisitor()
    {
        return "bx_p_event_visitor";
    }


    /**
     * If given a value, get the value if the parameter exists
     * If no value is given, validate if the visual element has the key defined
     *
     * @param $visualElement
     * @param $key
     * @param $value
     * @return bool
     */
    public function fetchVisualElementParam($visualElement, $key, $value=null)
    {
        $parameters = $visualElement['parameters'];
        foreach ($parameters as $parameter) {
            if($parameter['name'] == $key){
                if(is_null($value))
                {
                    return reset($parameter['values']);
                }
                if(in_array($value, $parameter['values'])) {
                    return true;
                }
            }
        }

        if(is_null($value))
        {
            return 0;
        }

        return false;
    }

}
