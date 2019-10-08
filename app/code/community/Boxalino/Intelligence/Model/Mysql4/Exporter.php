<?php

/**
 * Class Boxalino_Intelligence_Model_Resource_Exporter
 * Easier overwrite in order to customize logic on certain steps/queries
 */
class Boxalino_Intelligence_Model_Mysql4_Exporter extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * @var
     */
    protected $_prefix;

    /**
     * @var bool
     */
    protected $isDelta = false;

    /**
     * @var int
     */
    protected $_lastIndex = 0;

    /**
     * @var []
     */
    protected $exportIds = [];

    /**
     * read connection to the resource
     *
     * @var \Varien_Dbadapter_Interface
     */
    protected $adapter;


    public function _construct()
    {
        $this->adapter = Mage::getModel('core/resource')->getConnection('core_read');
        $this->_prefix = Mage::getConfig()->getTablePrefix();
    }

    /**
     * @return \Varien_Dbadapter_Interface
     */
    protected function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @return mixed
     */
    public function getProductAttributes()
    {
        $select = $this->adapter->select()
            ->from(
                ['ca_t' => $this->adapter->getTableName('catalog_eav_attribute')],
                ['attribute_id']
            )
            ->joinInner(
                ['a_t' => $this->adapter->getTableName('eav_attribute')],
                'ca_t.attribute_id = a_t.attribute_id',
                ['attribute_code']
            );

        return $this->adapter->fetchPairs($select);
    }

    /**
     * @param $id
     * @param $attributeId
     * @param $storeId
     * @return mixed
     */
    public function getProductAttributeValue($id, $attributeId, $storeId)
    {
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                [new \Zend_Db_Expr("CASE WHEN c_p_e_b.value IS NULL THEN c_p_e_a.value ELSE c_p_e_b.value END as value")]
            )
            ->joinLeft(
                ['c_p_e_a' => $this->adapter->getTableName('catalog_product_entity_int')],
                'c_p_e_a.entity_id = c_p_e.entity_id AND c_p_e_a.store_id = 0 AND c_p_e_a.attribute_id = ' . $attributeId,
                []
            )
            ->joinLeft(
                ['c_p_e_b' => $this->adapter->getTableName('catalog_product_entity_int')],
                'c_p_e_b.entity_id = c_p_e.entity_id AND c_p_e_b.store_id = ' . $storeId . ' AND c_p_e_b.attribute_id = ' . $attributeId,
                []
            )
            ->where('c_p_e.entity_id = ?', $id);

        return $this->adapter->fetchOne($select);
    }

    /**
     * Extended stock information export logic
     * In case of configurable/grouped products, the stock will also depend on how many child products in stock the product has
     *
     * @return mixed
     */
    public function getProductStockInformation()
    {
        $stockSelect = $this->adapter->select()
            ->from(
                ['c_s_i' => $this->adapter->getTableName('cataloginventory_stock_item')],
                array('entity_id' => 'product_id', 'value'=>'is_in_stock')
            );

        $childrenSelect = $this->adapter->select()
            ->from(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                ['c_p_r.child_id']
            )
            ->joinLeft(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                'c_p_e.entity_id = c_p_r.parent_id',
                ['c_p_e.entity_id']
            )
            ->join(['t_d' => new \Zend_Db_Expr("( ". $stockSelect->__toString() . ' )')],
                't_d.entity_id = c_p_r.child_id',
                ['t_d.value']
            )
            ->where('t_d.value = ?', Mage_CatalogInventory_Model_Stock::STOCK_IN_STOCK);

        $childCountSql = $this->adapter->select()
            ->from(
                ["child_select"=> new \Zend_Db_Expr("( ". $childrenSelect->__toString() . ' )')],
                ["child_count" => new \Zend_Db_Expr("COUNT(child_select.child_id)"), 'entity_id']
            )
            ->group("child_select.entity_id");

        $select = $this->adapter->select()
            ->from(
                ['c_s_i' => $this->adapter->getTableName('cataloginventory_stock_item')],
                array('entity_id' => 'product_id', 'qty', 'is_in_stock')
            )
            ->joinLeft(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                'c_p_e.entity_id = c_s_i.product_id',
                ['c_p_e.type_id']
            );
        if($this->isDelta)$select->where('product_id IN(?)', $this->exportIds);

        $configurableType = Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE;
        $groupedType = Mage_Catalog_Model_Product_Type_Grouped::TYPE_CODE;
        $finalSelect = $this->adapter->select()
            ->from(
                ["entity_select" => new \Zend_Db_Expr("( ". $select->__toString() . " )")],
                [
                    "entity_select.entity_id",
                    "entity_select.qty",
                    "is_in_stock" => new \Zend_Db_Expr("
                        (CASE 
                            WHEN (entity_select.type_id = '{$configurableType}' OR entity_select.type_id = '{$groupedType}') AND entity_select.is_in_stock = '1' THEN IF(child_count.child_count > 0, 1, 0)
                            ELSE entity_select.is_in_stock
                         END
                        )"
                    )
                ]
            )
            ->joinLeft(
                ["child_count"=> new \Zend_Db_Expr("( ". $childCountSql->__toString() . " )")],
                "child_count.entity_id = entity_select.entity_id",
                []
            );

        return $this->adapter->fetchAll($finalSelect);
    }

    /**
     * @return mixed
     */
    public function getProductParentCategoriesInformation()
    {
        $selectTwo = $this->getProductParentCategoriesInformationSql();
        $selectOne = clone $selectTwo;
        $selectOne->join(
            ['c_c_p' => $this->adapter->getTableName('catalog_category_product')],
            'c_c_p.product_id = c_p_r.parent_id',
            ['category_id']
        );
        $selectTwo->join(
            ['c_c_p' => $this->adapter->getTableName('catalog_category_product')],
            'c_c_p.product_id = c_p_e.entity_id AND c_p_r.parent_id IS NULL',
            ['category_id']
        );

        $select = $this->adapter->select()
            ->union(
                [$selectOne, $selectTwo],
                Zend_Db_Select::SQL_UNION_ALL
            );

        return $this->adapter->fetchAll($select);
    }

    protected function getProductParentCategoriesInformationSql()
    {
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['c_p_e.entity_id']
            )
            ->joinLeft(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'c_p_e.entity_id = c_p_r.child_id',
                []
            );
        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('product_id IN(?)', $this->exportIds);
        }

        return $select;
    }

    /**
     * @param array $duplicateIds
     * @return mixed
     */
    public function getProductParentCategoriesInformationByDuplicateIds($duplicateIds = [])
    {
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['entity_id']
            )->join(
                ['c_c_p' => $this->adapter->getTableName('catalog_category_product')],
                'c_c_p.product_id = c_p_e.entity_id',
                ['category_id']
            )->where('c_p_e.entity_id IN(?)', $duplicateIds);

        return $this->adapter->fetchAll($select);
    }

    /**
     * @return mixed
     */
    public function getProductSuperLinkInformation()
    {
        $select = $this->adapter->select()
            ->from(
                $this->adapter->getTableName('catalog_product_super_link'),
                ['entity_id' => 'product_id', 'parent_id', 'link_id']
            );
        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('product_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    /**
     * @return mixed
     */
    public function getProductLinksInformation()
    {
        $select = $this->adapter->select()
            ->from(
                ['pl'=> $this->adapter->getTableName('catalog_product_link')],
                ['entity_id' => 'product_id', 'linked_product_id', 'lt.code']
            )
            ->joinLeft(
                ['lt' => $this->adapter->getTableName('catalog_product_link_type')],
                'pl.link_type_id = lt.link_type_id', []
            )
            ->where('lt.link_type_id = pl.link_type_id');
        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('product_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }


    protected function getProductParentTitleInformationSql($storeId)
    {
        $attrId = $this->getAttributeId("name", $this->getEntityTypeId('catalog_product'));
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['c_p_e.entity_id']
            )
            ->joinLeft(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'c_p_e.entity_id = c_p_r.child_id',
                ['c_p_r.parent_id']
            );
        $select->where('t_d.attribute_id = ?', $attrId)->where('t_d.store_id = 0 OR t_d.store_id = ?',$storeId);
        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('c_p_e.entity_id IN(?)', $this->exportIds);
        }

        return $select;
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getProductParentTitleInformationByStore($storeId)
    {
        $selectTwo = $this->getProductParentTitleInformationSql($storeId);
        $selectOne = clone $selectTwo;
        $selectOne->join(
            ['t_d' => $this->adapter->getTableName('catalog_product_entity_varchar')],
            't_d.entity_id = c_p_e.entity_id AND c_p_r.parent_id IS NULL',
            [new \Zend_Db_Expr('LOWER(t_d.value) as value'), 't_d.store_id']
        );
        $selectTwo->join(
            ['t_d' => $this->adapter->getTableName('catalog_product_entity_varchar')],
            't_d.entity_id = c_p_r.parent_id',
            [new \Zend_Db_Expr('LOWER(t_d.value) as value'), 't_d.store_id']
        );

        $select = $this->adapter->select()
            ->union(
                [$selectOne, $selectTwo],
                Zend_Db_Select::SQL_UNION_ALL
            );

        return $this->adapter->fetchAll($select);
    }

    public function getProductParentTitleInformationByStoreAttrDuplicateIds($storeId, $duplicateIds = [])
    {
        $attrId = $this->getAttributeId("name", $this->getEntityTypeId('catalog_product'));
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['entity_id', new \Zend_Db_Expr("CASE WHEN c_p_e_v_b.value IS NULL THEN LOWER(c_p_e_v_a.value) ELSE LOWER(c_p_e_v_b.value) END as value")]
            )->joinLeft(
                ['c_p_e_v_a' => $this->adapter->getTableName('catalog_product_entity_varchar')],
                '(c_p_e_v_a.attribute_id = ' . $attrId . ' AND c_p_e_v_a.store_id = 0) AND (c_p_e_v_a.entity_id = c_p_e.entity_id)',
                []
            )->joinLeft(
                ['c_p_e_v_b' => $this->adapter->getTableName('catalog_product_entity_varchar')],
                '(c_p_e_v_b.attribute_id = ' . $attrId . ' AND c_p_e_v_b.store_id = ' . $storeId . ') AND (c_p_e_v_b.entity_id = c_p_e.entity_id)',
                []
            )->where('c_p_e.entity_id IN (?)', $duplicateIds);

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param $attr_code
     * @return mixed
     * @throws \Exception
     */
    public function getProductCategoryAttributeId($attr_code)
    {
        $select = $this->adapter->select()
            ->from(
                array('a_t' => $this->adapter->getTableName($this->_prefix . 'eav_attribute'))
            )->where('a_t.entity_type_id = 3 AND a_t.attribute_code = ?', $attr_code);

        try{
            return $this->adapter->fetchRow($select)['attribute_id'];
        }catch(\Exception $e){
            throw $e;
        }
    }


    /**
     * @param $entity_type_code
     * @return null
     * @throws Zend_Db_Statement_Exception
     */
    public function getEntityTypeId($entity_type_code)
    {
        $select = $this->adapter->select()
            ->from(
                array('e_e_t' => $this->adapter->getTableName($this->_prefix . 'eav_entity_type')),
                array('entity_type_id')
            )->where('e_e_t.entity_type_code = ?', $entity_type_code);

        $result = $this->adapter->query($select);
        if($result->rowCount()){
            while ($row = $result->fetch()) {
                return $row['entity_type_id'];
            }
        }

        return null;
    }

    /**
     * @param $attr_code
     * @param $type_id
     * @return null
     * @throws Zend_Db_Statement_Exception
     */
    public function getAttributeId($attr_code, $type_id)
    {
        $select = $this->adapter->select()
            ->from(
                array('e_a' => $this->adapter->getTableName($this->_prefix . 'eav_attribute')),
                array('attribute_id')
            )->where('e_a.attribute_code = ?', $attr_code)->where('e_a.entity_type_id = ?', $type_id);

        $result = $this->adapter->query($select);
        if($result->rowCount()){
            while ($row = $result->fetch()) {
                return $row['attribute_id'];
            }

        }
        return null;
    }

    /**
     * @param $storeId
     * @param $attributeId
     * @param $condition
     * @return array
     */
    public function getProductDuplicateIds($storeId, $attributeId, $condition)
    {
        $select = $this->adapter->select()
            ->from(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                [
                    'child_id',
                    new \Zend_Db_Expr("CASE WHEN c_p_e_b.value IS NULL THEN c_p_e_a.value ELSE c_p_e_b.value END as value")
                ]
            )->joinLeft(
                ['c_p_e_a' => $this->adapter->getTableName('catalog_product_entity_int')],
                'c_p_e_a.entity_id = c_p_r.child_id AND c_p_e_a.store_id = 0 AND c_p_e_a.attribute_id = ' . $attributeId,
                ['default_store'=>'c_p_e_a.store_id']
            )->joinLeft(
                ['c_p_e_b' => $this->adapter->getTableName('catalog_product_entity_int')],
                'c_p_e_b.entity_id = c_p_r.child_id AND c_p_e_b.store_id = ' . $storeId . ' AND c_p_e_b.attribute_id = ' . $attributeId,
                ['c_p_e_b.store_id']
            );

        $main =  $this->adapter->select()
            ->from(
                ['main'=> new \Zend_Db_Expr('( '. $select->__toString() . ' )')],
                ['id'=>'child_id', 'child_id']
            )
            ->where('main.value <> ?', $condition);

        return $this->adapter->fetchPairs($main);

    }

    public function getProductEntityByLimitPage($limit, $page, $websiteId)
    {
        $select = $this->adapter->select()
            ->from(
                ['e' => $this->adapter->getTableName('catalog_product_entity')],
                ["*"]
            )
            ->join(array('c_p_w' => $this->adapter->getTableName($this->_prefix . 'catalog_product_website')), 'e.entity_id = c_p_w.product_id', array('website_id'))
            ->limit($limit, ($page - 1) * $limit)
            ->joinLeft(
                ['p_t' => $this->adapter->getTableName('catalog_product_relation')],
                'e.entity_id = p_t.child_id', ['group_id' => 'parent_id']
            )
            ->where('c_p_w.website_id = ?', $websiteId);

        if($this->isDelta)
        {
            $select->where('e.created_at >= ? OR e.updated_at >= ?', $this->_getLastIndex());
        }

        return $this->adapter->fetchAll($select);
    }

    public function getProductAttributesByCodes($codes = [])
    {
        $select = $this->adapter->select()
            ->from(
                ['main_table' => $this->adapter->getTableName('eav_attribute')],
                ['attribute_id', 'attribute_code', 'backend_type', 'frontend_input']
            )
            ->joinInner(
                ['additional_table' => $this->adapter->getTableName('catalog_eav_attribute'), 'is_global'],
                'additional_table.attribute_id = main_table.attribute_id'
            )
            ->where('main_table.entity_type_id = ?', $this->getEntityTypeId('catalog_product'))
            ->where('main_table.attribute_code IN(?)', $codes);

        return $this->adapter->fetchAll($select);
    }

    public function getPriceByType($type, $key)
    {
        $select = $this->getPriceSqlByType($type, $key);
        if(!empty($this->exportIds) && $this->isDelta)
        {
            $select->where('c_p_r.parent_id IN(?)', $this->exportIds);
        }

        return $this->adapter->fetchAll($select);
    }

    public function getPriceSqlByType($type, $key)
    {
        $statusId = $this->getAttributeIdByAttributeCodeAndEntityType('status', $this->getEntityTypeId("catalog_product"));
        $select = $this->adapter->select()
            ->from(
                array('c_p_r' => $this->adapter->getTableName('catalog_product_relation')),
                array('parent_id')
            )
            ->join(
                array('t_d' => $this->adapter->getTableName('catalog_product_entity_' . $type)),
                't_d.entity_id = c_p_r.child_id',
                array(
                    'value' => 'MIN(t_d.value)'
                )
            )->join(
                array('t_s' => $this->adapter->getTableName('catalog_product_entity_int')),
                't_s.entity_id = c_p_r.child_id AND t_s.value = 1',
                array()
            )
            ->where('t_d.attribute_id = ?', $key)
            ->where('t_s.attribute_id = ?', $statusId)
            ->group(array('parent_id'));

        return $select;
    }

    public function getAttributeIdByAttributeCodeAndEntityType($code, $type)
    {
        $whereConditions = [
            $this->adapter->quoteInto(
                'attr.attribute_code = ?',
                $code
            ),
            $this->adapter->quoteInto(
                'attr.entity_type_id = ?',
                $type
            )
        ];

        $attributeIdSql = $this->adapter->select()
            ->from(['attr'=>'eav_attribute'], ['attribute_id'])
            ->where(implode(' AND ', $whereConditions));

        return $this->adapter->fetchOne($attributeIdSql);
    }

    /**
     * Get child product attribute value based on the parent product attribute value
     *
     * @param $attributeCode string
     * @param $type
     * @param $storeId int
     * @return \Zend_Db_Select
     * @throws Zend_Db_Statement_Exception
     */
    public function getProductAttributeParentUnionSqlByCodeTypeStore($attributeCode, $type, $storeId)
    {
        $attributeId = $this->getAttributeIdByAttributeCodeAndEntityType($attributeCode, $this->getEntityTypeId("catalog_product"));
        $select1 = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['c_p_e.entity_id']
            )
            ->joinLeft(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'c_p_e.entity_id = c_p_r.child_id',
                ['parent_id']
            );

        $select1->where('t_d.attribute_id = ?', $attributeId)->where('t_d.store_id = 0 OR t_d.store_id = ?',$storeId);
        if(!empty($this->exportIds) && $this->isDelta) $select1->where('c_p_e.entity_id IN(?)', $this->exportIds);

        $select2 = clone $select1;
        $select2->join(['t_d' => $this->adapter->getTableName('catalog_product_entity_' . $type)],
            't_d.entity_id = c_p_e.entity_id AND c_p_r.parent_id IS NULL',
            [
                't_d.attribute_id',
                't_d.value',
                't_d.store_id'
            ]
        );
        $select1->join(['t_d' => $this->adapter->getTableName('catalog_product_entity_' . $type)],
            't_d.entity_id = c_p_r.parent_id',
            [
                't_d.attribute_id',
                't_d.value',
                't_d.store_id'
            ]
        );

        return $this->adapter->select()->union(
            array($select1, $select2),
            \Zend_Db_Select::SQL_UNION
        );
    }

    /**
     * Get product attribute value as is in Magento
     *
     * @param $attributeCode string
     * @param $type
     * @param $storeId int
     * @return \Zend_Db_Select
     * @throws Zend_Db_Statement_Exception
     */
    public function getProductAttributeValueSqlByCodeTypeStore($attributeCode, $type, $storeId)
    {
        $attributeId = $this->getAttributeIdByAttributeCodeAndEntityType($attributeCode, $this->getEntityTypeId("catalog_product"));
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                [
                    new \Zend_Db_Expr("CASE WHEN c_p_e_b.value IS NULL THEN c_p_e_a.value ELSE c_p_e_b.value END as value"),
                    new \Zend_Db_Expr("CASE WHEN c_p_e_b.value IS NULL THEN c_p_e_a.store_id ELSE c_p_e_b.store_id END as store_id"),
                    'c_p_e.entity_id'
                ]
            )
            ->joinInner(
                ['c_p_e_a' => $this->adapter->getTableName('catalog_product_entity_' . $type)],
                'c_p_e_a.entity_id = c_p_e.entity_id AND c_p_e_a.store_id = 0 AND c_p_e_a.attribute_id = ' . $attributeId,
                []
            )
            ->joinLeft(
                ['c_p_e_b' => $this->adapter->getTableName('catalog_product_entity_' . $type)],
                'c_p_e_b.entity_id = c_p_e.entity_id AND c_p_e_b.store_id = ' . $storeId . ' AND c_p_e_b.attribute_id = ' . $attributeId,
                []
            );

        if(!empty($this->exportIds) && $this->isDelta) $select->where('c_p_e.entity_id IN(?)', $this->exportIds);

        return $select;
    }

    /**
     * Query for setting the product status value based on the parent properties and product visibility
     * Fixes the issue when parent product is enabled but child product is disabled.
     *
     * @param $storeId
     * @return mixed
     * @throws Zend_Db_Statement_Exception
     */
    public function getProductStatusParentDependabilityByStore($storeId)
    {
        $prodEntityId = $this->getEntityTypeId("catalog_product");
        $statusId = $this->getAttributeIdByAttributeCodeAndEntityType('status', $prodEntityId);
        $visibilityId = $this->getAttributeIdByAttributeCodeAndEntityType('visibility', $prodEntityId);

        $parentsCountSql = $this->getProductAttributeParentCountSqlByAttrIdValueStoreId($statusId,  Mage_Catalog_Model_Product_Status::STATUS_ENABLED, $storeId);
        $childCountSql = $this->getParentProductAttributeChildCountSqlByAttrIdValueStoreId($statusId,  Mage_Catalog_Model_Product_Status::STATUS_ENABLED, $storeId);

        $statusSql = $this->getEavJoinAttributeSQLByStoreAttrIdTable($statusId, $storeId, "catalog_product_entity_int");
        $visibilitySql = $this->getEavJoinAttributeSQLByStoreAttrIdTable($visibilityId, $storeId, "catalog_product_entity_int");
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['c_p_e.entity_id', 'c_p_e.type_id']
            )
            ->joinLeft(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                'c_p_e.entity_id = c_p_r.child_id',
                ['parent_id']
            )
            ->join(
                ['c_p_e_s' => new \Zend_Db_Expr("( ". $statusSql->__toString() . ' )')],
                "c_p_e.entity_id = c_p_e_s.entity_id",
                ['c_p_e_s.attribute_id', 'c_p_e_s.store_id','entity_status'=>'c_p_e_s.value']
            )
            ->join(
                ['c_p_e_v' => new \Zend_Db_Expr("( ". $visibilitySql->__toString() . ' )')],
                "c_p_e.entity_id = c_p_e_v.entity_id",
                ['entity_visibility'=>'c_p_e_v.value']
            );

        if(!empty($this->exportIds) && $this->isDelta) $select->where('c_p_e.entity_id IN(?)', $this->exportIds);
        $configurableType = Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE;
        $groupedType = Mage_Catalog_Model_Product_Type_Grouped::TYPE_CODE;
        $visibilityOptions = implode(',', [Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH, Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG, Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH]);
        $finalSelect = $this->adapter->select()
            ->from(
                ["entity_select" => new \Zend_Db_Expr("( ". $select->__toString() . " )")],
                [
                    "entity_select.entity_id",
                    "entity_select.parent_id",
                    "entity_select.store_id",
                    "value" => new \Zend_Db_Expr("
                        (CASE 
                            WHEN (entity_select.type_id = '{$configurableType}' OR entity_select.type_id = '{$groupedType}') AND entity_select.entity_status = '1' THEN IF(child_count.child_count > 0, 1, 2)
                            WHEN entity_select.parent_id IS NULL THEN entity_select.entity_status
                            WHEN entity_select.entity_status = '2' THEN 2 
                            ELSE IF(entity_select.entity_status = '1' AND entity_select.entity_visibility IN ({$visibilityOptions}), 1, IF(entity_select.entity_status = '1' AND parent_count.count > 0, 1, 2))
                         END
                        )"
                    )
                ]
            )
            ->joinLeft(
                ["parent_count"=> new \Zend_Db_Expr("( ". $parentsCountSql->__toString() . " )")],
                "parent_count.entity_id = entity_select.entity_id",
                ["count"]
            )
            ->joinLeft(
                ["child_count"=> new \Zend_Db_Expr("( ". $childCountSql->__toString() . " )")],
                "child_count.entity_id = entity_select.entity_id",
                ["child_count"]
            );

        return $finalSelect;
    }

    /**
     * Default function for accessing product attributes values
     * join them with default store
     * and make a selection on the store id
     *
     * @param $attributeId
     * @param $storeId
     * @param $table
     * @return mixed
     */
    protected function getEavJoinAttributeSQLByStoreAttrIdTable($attributeId, $storeId, $table, $main = 'catalog_product_entity')
    {
        $select = $this->adapter
            ->select()
            ->from(
                array('e' => $main),
                array('entity_id' => 'entity_id')
            );

        $innerCondition = array(
            $this->adapter->quoteInto("{$attributeId}_default.entity_id = e.entity_id", ''),
            $this->adapter->quoteInto("{$attributeId}_default.attribute_id = ?", $attributeId),
            $this->adapter->quoteInto("{$attributeId}_default.store_id = ?", 0)
        );

        $joinLeftConditions = array(
            $this->adapter->quoteInto("{$attributeId}_store.entity_id = e.entity_id", ''),
            $this->adapter->quoteInto("{$attributeId}_store.attribute_id = ?", $attributeId),
            $this->adapter->quoteInto("{$attributeId}_store.store_id IN(?)", $storeId)
        );

        $select
            ->joinInner(
                array($attributeId . '_default' => $table), implode(' AND ', $innerCondition),
                array('default_value' => 'value', 'attribute_id')
            )
            ->joinLeft(
                array("{$attributeId}_store" => $table), implode(' AND ', $joinLeftConditions),
                array("store_value" => 'value', 'store_id')
            );

        $selectSql = $this->adapter->select()
            ->from(
                array('joins' => $select),
                array(
                    'attribute_id'=>'joins.attribute_id',
                    'entity_id' => 'joins.entity_id',
                    'store_id' => new \Zend_Db_Expr("IF (joins.store_value IS NULL OR joins.store_value = '', 0, joins.store_id)"),
                    'value' => new \Zend_Db_Expr("IF (joins.store_value IS NULL OR joins.store_value = '', joins.default_value, joins.store_value)")
                )
            );

        return $selectSql;
    }

    /**
     * Getting count of parent products that have a certain value for an attribute
     * Used for validation of child values
     *
     * @param $attributeId
     * @param $value
     * @param $storeId
     * @return \Magento\Framework\DB\Select
     */
    protected function getProductAttributeParentCountSqlByAttrIdValueStoreId($attributeId, $value, $storeId)
    {
        $storeAttributeValue = $this->getEavJoinAttributeSQLByStoreAttrIdTable($attributeId, $storeId, "catalog_product_entity_int");
        $select = $this->adapter->select()
            ->from(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                ['c_p_r.parent_id']
            )
            ->joinLeft(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                'c_p_e.entity_id = c_p_r.child_id',
                ['c_p_e.entity_id']
            )
            ->join(['t_d' => new \Zend_Db_Expr("( ". $storeAttributeValue->__toString() . ' )')],
                't_d.entity_id = c_p_r.parent_id',
                ['t_d.value']
            );

        $mainSelect = $this->adapter->select()
            ->from(
                ["parent_select"=> new \Zend_Db_Expr("( ". $select->__toString() . ' )')],
                ["count" => new \Zend_Db_Expr("COUNT(parent_select.parent_id)"), 'entity_id']
            )
            ->where("parent_select.value = ?", $value)
            ->group("parent_select.entity_id");

        return $mainSelect;
    }

    /**
     * Getting count of child products that have a certain value for an attribute
     * Used for validation of parent values
     *
     * @param $attributeId
     * @param $value
     * @param $storeId
     * @return \Magento\Framework\DB\Select
     */
    protected function getParentProductAttributeChildCountSqlByAttrIdValueStoreId($attributeId, $value, $storeId)
    {
        $storeAttributeValue = $this->getEavJoinAttributeSQLByStoreAttrIdTable($attributeId, $storeId, "catalog_product_entity_int");
        $select = $this->adapter->select()
            ->from(
                ['c_p_r' => $this->adapter->getTableName('catalog_product_relation')],
                ['c_p_r.child_id']
            )
            ->joinLeft(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                'c_p_e.entity_id = c_p_r.parent_id',
                ['c_p_e.entity_id']
            )
            ->join(['t_d' => new \Zend_Db_Expr("( ". $storeAttributeValue->__toString() . ' )')],
                't_d.entity_id = c_p_r.child_id',
                ['t_d.value']
            )
            ->where('t_d.value = ?', $value);

        $mainSelect = $this->adapter->select()
            ->from(
                ["child_select"=> new \Zend_Db_Expr("( ". $select->__toString() . ' )')],
                ["child_count" => new \Zend_Db_Expr("COUNT(child_select.child_id)"), 'entity_id']
            )
            ->group("child_select.entity_id");

        return $mainSelect;
    }


    /**
     * We use the crypt key as salt when generating the guest user hash
     * this way we can still optimize on those users behaviour, whitout
     * exposing any personal data. The server salt is there to guarantee
     * that we can't connect guest user profiles across magento installs.
     *
     * @param array $billingColumns
     * @param array $shippingColumns
     * @param $date
     * @param int $mode
     * @return mixed
     */
    public function prepareTransactionsSelectByShippingBillingModeSql($account, $billingColumns =[], $shippingColumns = [], $mode = 1)
    {
        $salt = $this->adapter->quote(
            ((string) Mage::getConfig()->getNode('global/crypt/key')) .
            $account
        );
        $sales_order_table = $this->adapter->getTableName('sales_flat_order');
        $sales_order_item = $this->adapter->getTableName('sales_flat_order_item');
        $sales_order_address =  $this->adapter->getTableName('sales_flat_order_address');
        $sales_order_payment =  $this->adapter->getTableName('sales_flat_order_payment');

        $select = $this->adapter
            ->select()
            ->from(
                array('order' => $sales_order_table),
                array(
                    'entity_id',
                    'status',
                    'updated_at',
                    'created_at',
                    'customer_id',
                    'base_subtotal',
                    'shipping_amount',
                    'shipping_method',
                    'customer_is_guest',
                    'customer_email',
                    'order_currency_code'
                )
            )
            ->joinLeft(
                array('item' => $sales_order_item),
                'order.entity_id = item.order_id',
                array(
                    'product_id',
                    'product_options',
                    'price',
                    'original_price',
                    'product_type',
                    'qty_ordered',
                )
            )
            ->joinLeft(
                array('guest' => $sales_order_address),
                'order.billing_address_id = guest.entity_id',
                array(
                    'guest_id' => 'IF(guest.email IS NOT NULL, SHA1(CONCAT(guest.email, ' . $salt . ')), NULL)'
                )
            )
            ->joinLeft(
                array('payment' => $sales_order_payment),
                'order.entity_id = payment.entity_id',
                array(
                    'payment_method' => 'method'
                )
            );

        if (!$mode) {
            $select->where('DATE(order.created_at) >=  DATE(NOW() - INTERVAL 1 MONTH)');
        }

        if(!empty($billingColumns) && !empty($shippingColumns))
        {
            $select
                ->joinLeft(
                    array('billing_address' => $sales_order_address),
                    'order.billing_address_id = billing_address.entity_id',
                    $billingColumns
                )
                ->joinLeft(
                    array('shipping_address' => $sales_order_address),
                    'order.shipping_address_id = shipping_address.entity_id',
                    $shippingColumns
                );
        }

        return $select;
    }

    /**
     * @param $limit
     * @param $page
     * @param $initialSelect
     * @return mixed
     */
    public function getTransactionsByLimitPage($limit, $page, $initialSelect)
    {
        $select = $this->adapter->select()
            ->from(['transactions_export' => new \Zend_Db_Expr("( " . $initialSelect->__toString() . ')')], ['*'])
            ->limit($limit, ($page - 1) * $limit);

        return $this->adapter->fetchAll($select);
    }

    /**
     * @param $storeId
     * @return mixed
     * @throws Zend_Db_Statement_Exception
     */
    public function getCategoriesByStoreId($storeId, $rootId)
    {
        $attributeId = $this->getAttributeIdByAttributeCodeAndEntityType('name', $this->getEntityTypeId("catalog_category"));
        $select = $this->adapter->select()
            ->from(
                ['c_t' => $this->adapter->getTableName('catalog_category_entity')],
                ['entity_id', 'parent_id']
            )
            ->joinInner(
                ['c_v' => $this->adapter->getTableName('catalog_category_entity_varchar')],
                'c_v.entity_id = c_t.entity_id',
                ['c_v.value', 'c_v.store_id']
            )
            ->where('c_v.attribute_id = ?', $attributeId)
            ->where('c_v.store_id = ? OR c_v.store_id = 0', $storeId)
            ->where('c_t.path like \'1/'.$rootId.'%\'');

        return $this->adapter->fetchAll($select);
    }

    /**
     * @return mixed
     */
    public function getCustomerAttributes()
    {
        $select = $this->adapter->select()
            ->from(
                ['a_t' => $this->adapter->getTableName('eav_attribute')],
                ['code' => 'attribute_code', 'attribute_code']
            )
            ->where('a_t.entity_type_id = ?', $this->getEntityTypeId("customer"));

        return $this->adapter->fetchPairs($select);
    }

    public function getCustomerAttributesByCodes($codes = [])
    {
        $select = $this->adapter->select()
            ->from(
                ['main_table' => $this->adapter->getTableName('eav_attribute')],
                [
                    'aid' => 'attribute_id',
                    'attribute_code',
                    'backend_type',
                ]
            )
            ->joinInner(
                ['additional_table' => $this->adapter->getTableName('customer_eav_attribute')],
                'additional_table.attribute_id = main_table.attribute_id',
                []
            )
            ->where('main_table.entity_type_id = ?', $this->getEntityTypeId("customer"))
            ->where('main_table.attribute_code IN (?)', $codes);

        return $this->adapter->fetchAll($select);
    }

    public function getCustomerAddressByFieldsAndLimit($limit, $page, $attributeGroups = [])
    {
        $select = $this->adapter->select()
            ->from(
                $this->adapter->getTableName('customer_entity'),
                $attributeGroups
            )
            ->join(
                $this->adapter->getTableName('customer_address_entity'),
                'customer_entity.entity_id = customer_address_entity.parent_id',
                ['address_id' => 'entity_id']
            )
            ->group('customer_entity.entity_id')
            ->limit($limit, ($page - 1) * $limit);

        return $this->adapter->fetchAll($select);
    }

    public function getUnionCustomerAttributesByAttributesAndIds($attributes, $ids)
    {
        $columns = ['entity_id', 'attribute_id', 'value'];
        $attributeTypes = ['varchar', 'int', 'datetime'];

        $selects = [];
        foreach($attributeTypes as $type)
        {
            if (count($attributes[$type]) > 0) {
                $selects[] = $this->getSqlForCustomerAttributesUnion($this->adapter->getTableName('customer_entity_'. $type), $columns, $attributes[$type], $ids);
            }
        }

        if(count($selects)) {
            $select = $this->adapter->select()
                ->union(
                    $selects,
                    Zend_Db_Select::SQL_UNION_ALL
                );

            return $this->adapter->fetchAll($select);
        }
    }

    protected function getSqlForCustomerAttributesUnion($table, $columns, $attributes, $ids)
    {
        return $this->adapter->select()
            ->from(['ce' => $table], $columns)
            ->joinLeft(
                ['ea' => $this->adapter->getTableName('eav_attribute')],
                'ce.attribute_id = ea.attribute_id',
                'ea.attribute_code'
            )
            ->where('ce.attribute_id IN(?)', $attributes)
            ->where('ea.entity_type_id = ?', $this->getEntityTypeId("customer"))
            ->where('ce.entity_id IN (?)', $ids);
    }

    /**
     * @return int
     */
    public function _getLastIndex()
    {
        if ($this->_lastIndex == 0) {
            $this->_setLastIndex();
        }
        return $this->_lastIndex;
    }

    /**
     *
     */
    public function _setLastIndex()
    {
        $dates = array();
        $indexes = Mage::getModel('index/indexer')->getProcessesCollection()->getData();
        foreach ($indexes as $index) {
            if ($index['indexer_code'] == 'boxalinoexporter_indexer' && !empty($index['started_at'])) {
                $dates[] = DateTime::createFromFormat('Y-m-d H:i:s', $index['started_at']);
            } elseif ($index['indexer_code'] == 'boxalinoexporter_delta' && !empty($index['ended_at'])) {
                $dates[] = DateTime::createFromFormat('Y-m-d H:i:s', $index['ended_at']);
            }
        }
        if (count($dates) == 2) {
            if ($dates[0] > $dates[1]) {
                $date = $dates[0]->format('Y-m-d H:i:s');
            } else {
                $date = $dates[1]->format('Y-m-d H:i:s');
            }
        } else {
            $date = $dates[0]->format('Y-m-d H:i:s');
        }

        $this->_lastIndex = $date;
    }


    public function getTransactionColumnsAsAttributes()
    {
        return $this->getColumnsByTableName('sales_flat_order_address');
    }


    public function getColumnsByTableName($table)
    {
        $setupConfig = Mage::getConfig()->getResourceConnectionConfig("default_setup");
        $select = $this->adapter->select()
            ->from(
                'INFORMATION_SCHEMA.COLUMNS',
                ['COLUMN_NAME', 'name'=>'COLUMN_NAME']
            )
            ->where('TABLE_SCHEMA=?', $setupConfig->dbname)
            ->where('TABLE_NAME=?', $this->adapter->getTableName($table));

        $columns =  $this->adapter->fetchPairs($select);
        if(empty($columns))
        {
            throw new \Exception("{$table} does not exist.");
        }

        return $columns;
    }

    public function getTableContent($table)
    {
        try {
            $select = $this->adapter->select()
                ->from($table, array('*'));

            return $this->adapter->fetchAll($select);
        } catch(\Exception $exc)
        {
            return [];
        }

    }

    public function isDelta($isDelta)
    {
        $this->isDelta = $isDelta;
        return $this;
    }

    public function setExportIds($exportIds = [])
    {
        $this->exportIds = $exportIds;
        return $this;
    }

    public function getExportIds()
    {
        return $this->exportIds;
    }

}
