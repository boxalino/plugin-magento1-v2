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

    public function getQuestionsJson()
    {

    }

    public function getProgress()
    {

    }

    public function setProgress($progress)
    {

    }

    public function getCurrentQuestion()
    {

    }

    /**
     * connection to the boxalino server
     *
     * @return null
     */
    public function getBxAdapter(){
        if(is_null($this->bxAdapter))
        {
            $dataHelper = Mage::helper('boxalino_intelligence');
            $this->bxAdapter = $dataHelper->getAdapter();
        }

        return $this->bxAdapter;
    }

    /**
     * @return string
     */
    public function getProfilerActionUrl()
    {
        return Mage::getBaseUrl() . $this->getData('profiler_url');
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
