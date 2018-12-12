<?php

/**
 * Class Boxalino_Intelligence_Block_Journey_Profiler
 * Narrative profiler is used in the following way:
 * 1. it keeps a list of subrenderings(questions) of different types which are rendered via ajax on option select/skip action
 * 2. sets JS object properties (save actions, triggered events, etc)
 *
 * The profiler also uses a callback ajax function when the questions are saved (for a registered user) which sends all the content information to Boxalino
 * It is used for content personalization later on
 */
class Boxalino_Intelligence_Block_Journey_Profiler extends Boxalino_Intelligence_Block_Journey_General
    implements Boxalino_Intelligence_Block_Journey_CPOJourney
{

    /**
     * Profiler content is set to the js as a json element;
     * The questions are to be encoded to match the channel
     */
    public function getQuestions()
    {
       return json_encode($this->getSubRenderings());
    }

    /**
     * Setting if the progress to be displayed or not
     *
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

    /**
     * Sync profiler data with Boxalino
     *
     * @param $params
     */
    public function sendProfilerRequest($params = array())
    {
        if(!empty($array))
        {
            $this->p13nHelper->sendRequestWithParams($this->getData("choice"), $params);
        }
    }

    /**
     * Action for synchronizing Bx data
     */
    public function getSendProfilerRequestUrl()
    {
        return $this->getUrl("boxalinointelligence/profiler/bxrequest");
    }
}
