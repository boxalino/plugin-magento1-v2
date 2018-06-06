<?php

/**
 * Interface Boxalino_Intelligence_Block_Journey_CPOJourney
 */
interface Boxalino_Intelligence_Block_Journey_CPOJourney{

    public function getSubRenderings();

    public function renderVisualElement($element, $additional_parameter = null);

    public function getLocalizedValue($values);
}
