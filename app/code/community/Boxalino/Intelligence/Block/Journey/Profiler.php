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
       return json_encode($this->getSubRenderings());
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
        $customUrl = $this->getData("bx_p_load_url");
        if(empty($customUrl))
        {
            return $this->getUrl("boxalinointelligence/profiler/loadQuestion");
        }

        return $this->getUrl($customUrl);
    }

    /**
     * Action used to save all the information
     *
     * @return string
     */
    public function getSubmitUrl()
    {
        if($this->getData("bx_p_submit_url"))
        {
            return Mage::getBaseUrl() . $this->getData('bx_p_submit_url');
        }

        return $this->getUrl("boxalinointelligence/profiler/save");
    }

    /**
     * When the profiler data is to be saved, a hook is to be created for the event
     * Since there can be multiple profiler implementations in the system, this feature is configurable
     */
    public function getSubmitEventCustomer()
    {
        $customEvent = $this->getData("bx_p_event_customer");
        if(empty($customEvent))
        {
            return "bx_p_event_customer";
        }

        return $customEvent;
    }

    /**
     * Dispatched event when a visitor submits information (this means that a secondary action/validation must take place)
     */
    public function getSubmitEventVisitor()
    {
        $customEvent = $this->getData("bx_p_event_visitor");
        if(empty($customEvent))
        {
            return "bx_p_event_visitor";
        }

        return $customEvent;
    }
}
