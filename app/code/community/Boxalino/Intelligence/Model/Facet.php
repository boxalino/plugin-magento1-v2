<?php

/**
 * Class Boxalino_Intelligence_Model_Facet
 */
class Boxalino_Intelligence_Model_Facet extends Varien_Object
{

    /**
     * @var array
     */
    protected $facets = array();

    /**
     * @param $facets
     */
    public function setFacets($facets) {
        $this->facets = $facets;
    }

    /**
     * @return array
     */
    public function getFacets() {
        return $this->facets;
    }
}
