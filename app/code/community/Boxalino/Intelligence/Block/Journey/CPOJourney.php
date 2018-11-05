<?php

/**
 * Interface Boxalino_Intelligence_Block_Journey_CPOJourney
 */
interface Boxalino_Intelligence_Block_Journey_CPOJourney
{

    /**
     * Making a list of subrenderings for the element; which can be rendered recursively
     *
     * @return mixed
     */
    public function getSubRenderings();

    /**
     * Renders the visual element
     *
     * @param $element
     * @param null $additional_parameter
     * @return mixed
     */
    public function renderVisualElement($element, $additional_parameter = null);

    /**
     * @param $values
     * @return mixed
     */
    public function getLocalizedValue($values);
}
