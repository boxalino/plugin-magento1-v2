<?php

class Boxalino_Intelligence_Model_Mysql4_Exporter_Delta extends Boxalino_Intelligence_Model_Mysql4_Indexer
{
    const INDEX_TYPE = 'delta';

    /** @var date Date of last data sync */
    protected $_lastIndex = 0;

    /**
     * @description Declare where Indexer should start
     * @return void
     */
    protected function _construct()
    {
        $this->_init('boxalino_intelligence/delta', '');
    }

    /**
     * @description Get list of products with their tags
     * @return object List of products with their tags
     */
    protected function _getProductTags()
    {
        if (empty($this->_allProductTags)) {
            $tags = Mage::getResourceModel('tag/product_collection')->addAttributeToFilter('updated_at', array('from' => $this->_getLastIndex(), 'date' => true))->getData();
            foreach ($tags as $tag) {
                $this->_allProductTags[$tag['entity_id']] = $tag['tag_id'];
            }
        }
        return $this->_allProductTags;
    }
}
