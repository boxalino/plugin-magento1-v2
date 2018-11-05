<?php

/**
 * Class Boxalino_Intelligence_Model_Mysql4_Indexer
 */
abstract class Boxalino_Intelligence_Model_Mysql4_Indexer extends Mage_Core_Model_Mysql4_Abstract
{
    /**
     * @var
     */
    protected $_prefix;

    /**
     * @var
     */
    protected $config;

    /**
     * @var
     */
    protected $bxData;

    /**
     * @var
     */
    protected $indexType;

    /**
     * @var
     */
    protected $_helperExporter;

    /**
     * @var null
     */
    protected $_entityTypeIds = null;

    /**
     * @var int
     */
    protected $_lastIndex = 0;

    /**
     * @var array
     */
    protected $deltaIds = array();

    /**
     *
     */
    const BOXALINO_LOG_FILE = 'boxalino_exporter.log';

    /**
     * @return $this
     */
    public function reindexAll()
    {
        $this->loadBxLibrary();
        $this->indexType = static::INDEX_TYPE;
        $this->_prefix = Mage::getConfig()->getTablePrefix();
        $this->exportStores();
        return $this;
    }

    /**
     *
     */
    protected function exportStores()
    {
        $this->_helperExporter = Mage::helper('boxalino_intelligence');
        Mage::log("bxLog: starting {$this->indexType} Boxalino export", Zend_Log::INFO, self::BOXALINO_LOG_FILE);

        $this->config = Mage::helper('boxalino_intelligence/bxIndexConfig');
        Mage::log("bxLog: retrieved index config: " . $this->config->toString(), Zend_Log::INFO, self::BOXALINO_LOG_FILE);

        try {
            foreach ($this->config->getAccounts() as $account) {
                Mage::log("bxLog: initialize files on account: " . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                $files = Mage::helper('boxalino_intelligence/bxFiles')->init($account, $this->indexType);

                $bxClient = new \com\boxalino\bxclient\v1\BxClient($account, $this->config->getAccountPassword($account), "");

                // Indicate index type as boolean variable.
                $isDelta = ($this->indexType == 'delta');
                $this->bxData = new \com\boxalino\bxclient\v1\BxData($bxClient, $this->config->getAccountLanguages($account), $this->config->isAccountDev($account), $isDelta);

                Mage::log("bxLog: verify credentials for account: " . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                $this->bxData->verifyCredentials();

                Mage::log('bxLog: Export the product files for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                $exportProducts = $this->exportProducts($account, $files);

                Mage::log('bxLog: Start exportCategories', Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                $categories = array();
                foreach ($this->config->getAccountLanguages($account) as $language) {
                    $store = $this->config->getStore($account, $language);
                    Mage::log('bxLog: Start getStoreProductAttributes for language: ' . $language . ' on store: ' . $store->getId(), Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                    Mage::log('bxLog: Start exportCategories for language: ' . $language . ' on store:' . $store->getId(), Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                    $categories = $this->exportCategories($store, $language, $categories);
                }
                $this->prepareData($account, $files, $categories);

                Mage::log('bxLog: Export the customers and transactions for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                if($this->indexType == 'full'){
                    $this->exportCustomers($account, $files);
                    $this->exportTransactions($account, $files);
                }

                if(!$exportProducts){
                    Mage::log('bxLog: No Products found for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                    Mage::log('bxLog: Finished account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                }else{
                    if($this->indexType == 'full'){
                        Mage::log('bxLog: Prepare the final files: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                        Mage::log('bxLog: Prepare XML configuration file: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                        try {
                            Mage::log('bxLog: Push the XML configuration file to the Data Indexing server for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                            $this->bxData->pushDataSpecifications();
                        } catch(\Exception $e) {
                            $value = @json_decode($e->getMessage(), true);
                            if(isset($value['error_type_number']) && $value['error_type_number'] == 3) {
                                Mage::log('bxLog: Try to push the XML file a second time, error 3 happens always at the very first time but not after: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                                $this->bxData->pushDataSpecifications();
                            } else {
                                throw $e;
                            }
                        }
                        Mage::log('bxLog: Publish the configuration changes from the magento2 owner for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                        $publish = $this->config->publishConfigurationChanges($account);
                        $changes = $this->bxData->publishOwnerChanges($publish);
                        if(sizeof($changes['changes']) > 0 && !$publish) {
                            Mage::log("changes in configuration detected but not published as publish configuration automatically option has not been activated for account: " . $account, Zend_Log::WARN, self::BOXALINO_LOG_FILE);
                        }
                        Mage::log('bxLog: Push the Zip data file to the Data Indexing server for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                    }

                    try{
                        $timeout = $this->getTimeoutForExporter($account);
                        $this->bxData->pushData(null, $timeout);
                    }catch(\RuntimeException $e){
                        Mage::log('bxLog: pushing data stopped due to the configured timeout: ' . $e->getMessage(), Zend_Log::WARN, self::BOXALINO_LOG_FILE);
                    }catch(\Exception $e){
                        Mage::logException($e);
                        Mage::log('bxLog: pushData failed with exception: ' . $e->getMessage(), Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                    }
                    Mage::log('bxLog: Finished account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                }
            }

            Mage::log("bxLog: finished Boxalino export", Zend_Log::INFO, self::BOXALINO_LOG_FILE);

        }catch(\Exception $e){
            Mage::logException($e);
            Mage::log("bxLog: failed with exception: " . $e->getMessage(), Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        }
    }

    /**
     * Get timeout for exporter
     * @return bool|int
     */
    protected function getTimeoutForExporter($account)
    {
        if($this->indexType == "delta")
        {
            return 60;
        }

        $customTimeout = $this->config->getExporterTimeout($account);
        if($customTimeout)
        {
            return $customTimeout;
        }

        return 3000;
    }

    /**
     * @param $account
     * @param $files
     * @param $categories
     * @param null $tags
     * @param null $productTags
     */
    protected function prepareData($account, $files, $categories, $tags = null, $productTags = null){
        $withTag = ($tags != null && $productTags != null) ? true : false;
        $languages = $this->config->getAccountLanguages($account);
        $categories = array_merge(array(array_keys(end($categories))), $categories);
        $files->savePartToCsv('categories.csv', $categories);
        $labelColumns = array();
        foreach ($languages as $lang) {
            $labelColumns[$lang] = 'value_' . $lang;
        }
        $this->bxData->addCategoryFile($files->getPath('categories.csv'), 'category_id', 'parent_id', $labelColumns);
        $productToCategoriesSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_categories.csv'), 'entity_id');
        $this->bxData->setCategoryField($productToCategoriesSourceKey, 'category_id');
    }

    /**
     * @param $account
     * @return array
     */
    protected function getStoreProductAttributes($account)
    {
        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('a_t' => $db->getTableName($this->_prefix . 'eav_attribute')),
                array('a_t.attribute_id', 'a_t.attribute_code')
            )
            ->joinInner(
                array('ca_t' => $db->getTableName($this->_prefix . 'catalog_eav_attribute')),
                'ca_t.attribute_id = a_t.attribute_id'
            );

        $attributes = array();
        $result = $db->query($select);
        if($result->rowCount()){
            while($attribute = $result->fetch()){
                $attributes[$attribute['attribute_id']] = $attribute['attribute_code'];
            }
        }

        Mage::log('bxLog: get all product attributes.', Zend_Log::INFO, self::BOXALINO_LOG_FILE);

        $requiredProperties = array(
            'entity_id',
            'name',
            'description',
            'short_description',
            'sku',
            'price',
            'special_price',
            'special_from_date',
            'special_to_date',
            'category_ids',
            'visibility',
            'status'
        );
        if($this->config->exportProductImages($account)) {
            $requiredProperties[] = 'image';
        }
        if($this->config->exportProductUrl($account)) {
            $requiredProperties[] = 'url_key';
        }
        Mage::log('bxLog: get configured product attributes.', Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $attributes = $this->config->getAccountProductsProperties($account, $attributes, $requiredProperties);
        Mage::log('bxLog: returning configured product attributes: ' . implode(',', array_values($attributes)), Zend_Log::INFO, self::BOXALINO_LOG_FILE);

        return $attributes;
    }

    /**
     * @param $account
     * @param $files
     * @return bool
     */
    protected function exportProducts($account, $files){
        $languages = $this->config->getAccountLanguages($account);

        Mage::log('bxLog: Products - start of export for account ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $attrs = $this->getStoreProductAttributes($account);
        Mage::log('bxLog: Products - get info about attributes - before for account ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);

        $db = $this->_getReadAdapter();

        $countMax = 1000000; //$this->_storeConfig['maximum_population'];
        $limit = 1000; //$this->_storeConfig['export_chunk'];
        $totalCount = 0;
        $page = 1;
        $header = true;
        $duplicateIds = $this->getDuplicateIds($account, $languages);
        $website_id = $this->config->getWebsite($account)->getId();
        while (true) {

            if ($countMax > 0 && $totalCount >= $countMax) {
                break;
            }

            $select = $db->select()
                ->from(
                    array('e' => $db->getTableName($this->_prefix . 'catalog_product_entity'))
                )
                ->limit($limit, ($page - 1) * $limit)
                ->joinLeft(
                    array('p_t' => $db->getTableName($this->_prefix . 'catalog_product_relation')),
                    'e.entity_id = p_t.child_id', array('group_id' => 'parent_id')
                )
                ->join(array('c_p_w' => $db->getTableName($this->_prefix . 'catalog_product_website')), 'e.entity_id = c_p_w.product_id', array('website_id'))
                ->where('c_p_w.website_id = ?', $website_id);
            if($this->indexType == 'delta')$select->where('created_at >= ? OR updated_at >= ?', $this->_getLastIndex());

            $data = array();
            $result = $db->query($select);
            if($result->rowCount()){
                while($row = $result->fetch()){
                    if($this->indexType == 'delta') $this->deltaIds[] = $row['entity_id'];
                    if($row['group_id'] == null) $row['group_id'] = $row['entity_id'];
                    $data[] = $row;
                    $totalCount++;
                    if(isset($duplicateIds[$row['entity_id']])){
                        $row['group_id'] = $row['entity_id'];
                        $row['entity_id'] = 'duplicate' . $row['entity_id'];
                        $data[] = $row;
                    }
                }
            }else{
                if($totalCount == 0){
                    return false;
                }
                break;
            }

            if ($header && count($data) > 0) {
                $data = array_merge(array(array_keys(end($data))), $data);
                $header = false;
            }

            $files->savePartToCsv('products.csv', $data);
            $data = null;
            $page++;
        }
        $attributeSourceKey = $this->bxData->addMainCSVItemFile($files->getPath('products.csv'), 'entity_id');
        $this->bxData->addSourceStringField($attributeSourceKey, 'group_id', 'group_id');
        $this->bxData->addFieldParameter($attributeSourceKey, 'group_id', 'multiValued', 'false');

        $select = $db->select()
            ->from(
                array('main_table' => $db->getTableName($this->_prefix . 'eav_attribute')),
                array(
                    'attribute_id',
                    'attribute_code',
                    'backend_type',
                    'frontend_input',
                )
            )
            ->joinInner(
                array('additional_table' => $db->getTableName($this->_prefix . 'catalog_eav_attribute'), 'is_global'),
                'additional_table.attribute_id = main_table.attribute_id'
            )
            ->where('main_table.entity_type_id = ?', $this->getEntityTypeId('catalog_product'))
            ->where('main_table.attribute_code IN(?)', $attrs);

        $attrsFromDb = array(
            'int' => array(),
            'varchar' => array(),
            'text' => array(),
            'decimal' => array(),
            'datetime' => array()
        );
        $result = $db->query($select);

        if($result->rowCount()){
            while($row = $result->fetch()){
                $type = $row['backend_type'];
                if (isset($attrsFromDb[$type])) {
                    $attrsFromDb[$type][$row['attribute_id']] = array(
                        'attribute_code' => $row['attribute_code'], 'is_global' => $row['is_global'],
                        'frontend_input' => $row['frontend_input']
                    );
                }
            }
        }

        $this->exportProductAttributes($attrsFromDb, $languages, $account, $files, $attributeSourceKey, $duplicateIds);
        $this->exportProductInformation($files, $duplicateIds, $account, $languages);

        Mage::log("bxLog: Products - exporting additional tables for account: {$account}", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $this->exportExtraTables('products', $files, $this->config->getAccountExtraTablesByEntityType($account,'products'));
        return true;
    }

    /**
     * @param array $attrs
     * @param $languages
     * @param $account
     * @param $files
     */
    protected function exportProductAttributes($attrs = array(), $languages, $account, $files, $mainSourceKey, $duplicateIds){
        $paramPriceLabel = '';
        $paramSpecialPriceLabel = '';

        $db = $this->_getReadAdapter();
        $columns = array(
            'entity_id',
            'attribute_id',
            'value',
            'store_id'
        );

        $files->prepareProductFiles($attrs);

        foreach($attrs as $attributeType => $types){

            foreach ($types as $attributeID => $attribute) {
                Mage::log('bxLog: Products - exporting attribute: ' . $attribute['attribute_code']  . ' for ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                $optionSelect = in_array($attribute['frontend_input'], array('multiselect','select'));
                $data = array();
                $additionalData = array();
                $global = false;
                $getValueForDuplicate = false;
                $d = array();
                $headerLangRow = array();
                $optionValues = array();

                foreach ($languages as $langIndex => $lang) {

                    $select = $db->select()->from(
                        array('t_d' => $db->getTableName($this->_prefix . 'catalog_product_entity_' . $attributeType)),
                        $columns
                    );
                    if($this->indexType == 'delta')$select->where('t_d.entity_id IN(?)', $this->deltaIds);

                    $labelColumns[$lang] = 'value_' . $lang;
                    $storeObject = $this->config->getStore($account, $lang);
                    $storeId = $storeObject->getId();

                    if($attribute['attribute_code'] == 'url_key' || $attribute['attribute_code'] == 'image'){
                        $storeBaseUrl = $storeObject->getBaseUrl();
                        $imageBaseUrl = $storeObject->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . "catalog/product";
                    }
                    $storeObject = null;

                    if ($attribute['attribute_code'] == 'price' || $attribute['attribute_code'] == 'special_price') {
                        if($langIndex == 0) {
                            $priceSelect = $db->select()
                                ->from(
                                    array('c_p_r' => $db->getTableName($this->_prefix . 'catalog_product_relation')),
                                    array('parent_id')
                                )
                                ->join(
                                    array('t_d' => $db->getTableName($this->_prefix . 'catalog_product_entity_' . $attributeType)),
                                    't_d.entity_id = c_p_r.child_id',
                                    array(
                                        'value' => 'MIN(value)'
                                    )
                                )->group(array('parent_id'))->where('t_d.attribute_id = ?', $attributeID);
                            if($this->indexType == 'delta')$priceSelect->where('c_p_r.parent_id IN(?)', $this->deltaIds);

                            $priceData = array();
                            foreach ($db->fetchAll($priceSelect) as $row) {
                                $priceData[] = $row;
                            }

                            if (sizeof($priceData)) {
                                $priceData = array_merge(array(array_keys(end($priceData))), $priceData);
                            } else {
                                $priceData = array(array('parent_id', 'value'));
                            }
                            $files->savePartToCsv($attribute['attribute_code'] . '.csv', $priceData);
                            $priceData = null;
                        }
                    }
                    if ($attribute['attribute_code'] == 'url_key') {
                        if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
                            $select = $db->select()
                                ->joinLeft(
                                    array('t_d' => $db->getTableName($this->_prefix . 'catalog_product_entity_url_key')),
                                    array('entity_id', 'attribute_id')
                                )
                                ->joinLeft(
                                    array('t_s' => $db->getTableName($this->_prefix . 'catalog_product_entity_url_key')),
                                    't_s.attribute_id = t_d.attribute_id AND t_s.entity_id = t_d.entity_id',
                                    array('value' => 'IF(t_s.store_id IS NULL, t_d.value, t_s.value)')
                                );
                        }
                    }

                    if($optionSelect){
                        $languagesForLabels[$lang] = $storeId;
                        if($langIndex == 0)
                        {
                            $languagesForLabels[Mage_Core_Model_Store::ADMIN_CODE] = 0;
                            $labelColumns[Mage_Core_Model_Store::ADMIN_CODE] = 'value_' . Mage_Core_Model_Store::ADMIN_CODE;
                        }
                        foreach($languagesForLabels as $labelLanguage => $store)
                        {
                            $attributeSourceModel = Mage::getModel('eav/config')->getAttribute('catalog_product', $attribute['attribute_code'])
                                ->setStoreId($store)->getSource();
                            $fetchedOptionValues = null;

                            if ($attributeSourceModel instanceof Mage_Eav_Model_Entity_Attribute_Source_Abstract) {
                                // Fetch attribute options through method to respect source model implementation.
                                $fetchedOptionValues = $attributeSourceModel->getAllOptions(false);
                            }

                            if($fetchedOptionValues){
                                foreach($fetchedOptionValues as $v){
                                    if (isset($v['value']) && !is_array($v['value'])) {
                                        if (isset($optionValues[$v['value']])) {
                                            $optionValues[$v['value']]['value_' . $labelLanguage] = $v['label'];
                                        } else {
                                            $optionValues[$v['value']] = array(
                                                $attribute['attribute_code'] . '_id' => $v['value'],
                                                'value_' . $labelLanguage => $v['label']
                                            );
                                        }
                                    }
                                }
                            }else{
                                $optionSelect = false;
                            }
                            $fetchedOptionValues = null;
                        }
                    }

                    $select
                        ->where('t_d.attribute_id = ?', $attributeID)
                        ->where('t_d.store_id = 0 OR t_d.store_id = ?',$storeId);

                    if ($attribute['attribute_code'] == 'visibility' || $attribute['attribute_code'] ==  'status') {
                        $getValueForDuplicate = true;
                        $select1 = $db->select()
                            ->from(
                                array('c_p_e' => $db->getTableName($this->_prefix . 'catalog_product_entity')),
                                array('c_p_e.entity_id',)
                            )
                            ->joinLeft(
                                array('c_p_r' => $db->getTableName($this->_prefix . 'catalog_product_relation')),
                                'c_p_e.entity_id = c_p_r.child_id',
                                array('parent_id')
                            );

                        $select1->where('t_d.attribute_id = ?', $attributeID)->where('t_d.store_id = 0 OR t_d.store_id = ?',$storeId);
                        if($this->indexType == 'delta') $select1->where('c_p_e.entity_id IN(?)', $this->deltaIds);

                        $select2 = clone $select1;
                        $select2->join(array('t_d' => $db->getTableName($this->_prefix . 'catalog_product_entity_' . $attributeType)),
                            't_d.entity_id = c_p_e.entity_id AND c_p_r.parent_id IS NULL',
                            array(
                                't_d.attribute_id',
                                't_d.value',
                                't_d.store_id'
                            )
                        );
                        $select1->join(array('t_d' => $db->getTableName($this->_prefix . 'catalog_product_entity_' . $attributeType)),
                            't_d.entity_id = c_p_r.parent_id',
                            array(
                                't_d.attribute_id',
                                't_d.value',
                                't_d.store_id'
                            )
                        );
                        $select = $db->select()->union(
                            array($select1, $select2),
                            \Zend_Db_Select::SQL_UNION
                        );
                    }
                    $result = $db->query($select);

                    if ($result->rowCount()) {
                        while ($row = $result->fetch()) {
                            if (isset($data[$row['entity_id']]) && !$optionSelect) {

                                if(isset($data[$row['entity_id']]['value_' . $lang])){
                                    if($row['store_id'] > 0){
                                        $data[$row['entity_id']]['value_' . $lang] = $row['value'];
                                        if(isset($duplicateIds[$row['entity_id']])){
                                            $data['duplicate'.$row['entity_id']]['value_' . $lang] = $getValueForDuplicate ?
                                                $this->getProductAttributeFromId($row['entity_id'], $attributeID, $storeId) :
                                                $row['value'];
                                        }
                                        if(isset($additionalData[$row['entity_id']])){
                                            if ($attribute['attribute_code'] == 'url_key') {
                                                $url = $storeBaseUrl . $row['value'] . '.html';
                                            } else {
                                                $url = $imageBaseUrl . $row['value'];
                                            }
                                            $additionalData[$row['entity_id']]['value_' . $lang] = $url;
                                            if(isset($duplicateIds[$row['entity_id']])){
                                                $additionalData['duplicate'.$row['entity_id']]['value_' . $lang] = $url;
                                            }
                                        }
                                    }
                                }else{
                                    $data[$row['entity_id']]['value_' . $lang] = $row['value'];
                                    if(isset($duplicateIds[$row['entity_id']])){
                                        $data['duplicate'.$row['entity_id']]['value_' . $lang] = $getValueForDuplicate ?
                                            $this->getProductAttributeFromId($row['entity_id'], $attributeID, $storeId) :
                                            $row['value'];
                                    }
                                    if (isset($additionalData[$row['entity_id']])) {
                                        if ($attribute['attribute_code'] == 'url_key') {
                                            $url = $storeBaseUrl . $row['value'] . '.html';

                                        } else {
                                            $url = $imageBaseUrl . $row['value'];
                                        }
                                        $additionalData[$row['entity_id']]['value_' . $lang] = $url;
                                        if(isset($duplicateIds[$row['entity_id']])){
                                            $additionalData['duplicate'.$row['entity_id']]['value_' . $lang] = $url;
                                        }
                                    }
                                }
                                continue;
                            } else {
                                if ($attribute['attribute_code'] == 'url_key') {
                                    if ($this->config->exportProductUrl($account)) {
                                        $url = $storeBaseUrl . $row['value'] . '.html';
                                        $additionalData[$row['entity_id']] = array('entity_id' => $row['entity_id'],
                                            'store_id' => $row['store_id'],
                                            'value_' . $lang => $url);
                                        if(isset($duplicateIds[$row['entity_id']])){
                                            $additionalData['duplicate'.$row['entity_id']] = array(
                                                'entity_id' => 'duplicate'.$row['entity_id'],
                                                'value_' . $lang => $url);
                                        }
                                    }
                                }
                                if ($attribute['attribute_code'] == 'image') {
                                    if ($this->config->exportProductImages($account)) {
                                        $url = $imageBaseUrl . $row['value'];
                                        $additionalData[$row['entity_id']] = array('entity_id' => $row['entity_id'],
                                            'store_id' => $row['store_id'],
                                            'value_' . $lang => $url);
                                        if(isset($duplicateIds[$row['entity_id']])){
                                            $additionalData['duplicate'.$row['entity_id']] = array(
                                                'entity_id' => 'duplicate'.$row['entity_id'],
                                                'value_' . $lang => $url);
                                        }
                                    }
                                }
                                if ($attribute['is_global'] != 1) {
                                    if($optionSelect){
                                        $values = explode(',',$row['value']);
                                        foreach($values as $v){
                                            $data[] = array('entity_id' => $row['entity_id'],
                                                $attribute['attribute_code'] . '_id' => $v);
                                            if(isset($duplicateIds[$row['entity_id']])){
                                                $data[] = array('entity_id' => 'duplicate'.$row['entity_id'],
                                                    $attribute['attribute_code'] . '_id' => $v);
                                            }
                                        }
                                    }else{
                                        $data[$row['entity_id']] = array('entity_id' => $row['entity_id'],
                                            'store_id' => $row['store_id'],'value_' . $lang => $row['value']);
                                        if(isset($duplicateIds[$row['entity_id']])){
                                            $data['duplicate'.$row['entity_id']] = array(
                                                'entity_id' => 'duplicate'.$row['entity_id'],
                                                'store_id' => $row['store_id'],
                                                'value_' . $lang => $getValueForDuplicate ?
                                                    $this->getProductAttributeFromId($row['entity_id'], $attributeID, $storeId)
                                                    : $row['value']
                                            );
                                        }
                                    }
                                    continue;
                                }else{
                                    if($optionSelect){
                                        $values = explode(',',$row['value']);
                                        foreach($values as $v){
                                            if(!isset($data[$row['entity_id'].$v])){
                                                $data[$row['entity_id'].$v] = array('entity_id' => $row['entity_id'],
                                                    $attribute['attribute_code'] . '_id' => $v);
                                                if(isset($duplicateIds[$row['entity_id']])){
                                                    $data[] = array('entity_id' => 'duplicate'.$row['entity_id'],
                                                        $attribute['attribute_code'] . '_id' => $v);
                                                }
                                            }
                                        }
                                    }else{
                                        $valueLabel = $attribute['attribute_code'] == 'visibility' ||
                                        $attribute['attribute_code'] == 'status' ||
                                        $attribute['attribute_code'] == 'special_from_date' ||
                                        $attribute['attribute_code'] == 'special_to_date' ? 'value_' . $lang : 'value';
                                        $data[$row['entity_id']] = array('entity_id' => $row['entity_id'],
                                            'store_id' => $row['store_id'],
                                            $valueLabel => $row['value']);
                                        if(isset($duplicateIds[$row['entity_id']])){
                                            $data['duplicate'.$row['entity_id']] = array(
                                                'entity_id' => 'duplicate'.$row['entity_id'],
                                                'store_id' => $row['store_id'],
                                                $valueLabel => $getValueForDuplicate ?
                                                    $this->getProductAttributeFromId($row['entity_id'], $attributeID, $storeId)
                                                    : $row['value']
                                            );
                                        }
                                    }
                                }
                            }
                        }
                        if($attribute['is_global'] == 1 && !$optionSelect){
                            $global = true;
                            if($attribute['attribute_code'] != 'visibility' &&
                                $attribute['attribute_code'] != 'status' &&
                                $attribute['attribute_code'] != 'special_from_date' &&
                                $attribute['attribute_code'] != 'special_to_date'
                            )
                            {
                                break;
                            }
                        }
                    }
                }
                if($optionSelect){
                    $optionHeader = array_merge(array($attribute['attribute_code'] . '_id'),$labelColumns);
                    $a = array_merge(array($optionHeader), $optionValues);
                    $files->savepartToCsv($attribute['attribute_code'].'.csv', $a);
                    $optionValues = null;
                    $a = null;
                    $optionSourceKey = $this->bxData->addResourceFile(
                        $files->getPath($attribute['attribute_code'] . '.csv'), $attribute['attribute_code'] . '_id',
                        $labelColumns);
                    if(sizeof($data) == 0){
                        $d = array(array('entity_id',$attribute['attribute_code'] . '_id'));
                        $files->savepartToCsv('product_' . $attribute['attribute_code'] . '.csv',$d);
                        $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_' . $attribute['attribute_code'] . '.csv'), 'entity_id');
                        $this->bxData->addSourceLocalizedTextField($attributeSourceKey,$attribute['attribute_code'],
                            $attribute['attribute_code'] . '_id', $optionSourceKey);
                    }
                }
                if (sizeof($data)) {
                    if(!$global || $attribute['attribute_code'] == 'visibility' ||
                        $attribute['attribute_code'] == 'status' ||
                        $attribute['attribute_code'] == 'special_from_date' ||
                        $attribute['attribute_code'] == 'special_to_date')
                    {
                        if(!$optionSelect){
                            $headerLangRow = array_merge(array('entity_id','store_id'), $labelColumns);
                            if(sizeof($additionalData)){
                                $additionalHeader = array_merge(array('entity_id','store_id'), $labelColumns);
                                $d = array_merge(array($additionalHeader), $additionalData);
                                if ($attribute['attribute_code'] == 'url_key') {
                                    $files->savepartToCsv('product_default_url.csv', $d);
                                    $sourceKey = $this->bxData->addCSVItemFile($files->getPath('product_default_url.csv'), 'entity_id');
                                    $this->bxData->addSourceLocalizedTextField($sourceKey, 'default_url', $labelColumns);
                                } else {
                                    $files->savepartToCsv('product_cache_image_url.csv', $d);
                                    $sourceKey = $this->bxData->addCSVItemFile($files->getPath('product_cache_image_url.csv'), 'entity_id');
                                    $this->bxData->addSourceLocalizedTextField($sourceKey, 'cache_image_url',$labelColumns);
                                }
                            }
                            $d = array_merge(array($headerLangRow), $data);
                        }else{
                            $d = array_merge(array(array('entity_id',$attribute['attribute_code'] . '_id')), $data);
                        }
                    }else {
                        $d = array_merge(array(array('entity_id', 'store_id', 'value')), $data);
                    }


                    $files->savepartToCsv('product_' . $attribute['attribute_code'] . '.csv', $d);
                    $fieldId = $this->_helperExporter->sanitizeFieldName($attribute['attribute_code']);
                    $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_' . $attribute['attribute_code'] . '.csv'), 'entity_id');

                    switch($attribute['attribute_code']){
                        case $optionSelect == true:
                            $this->bxData->addSourceLocalizedTextField($attributeSourceKey,$attribute['attribute_code'],
                                $attribute['attribute_code'] . '_id', $optionSourceKey);
                            break;
                        case 'name':
                            $this->bxData->addSourceTitleField($attributeSourceKey, $labelColumns);
                            break;
                        case 'description':
                            $this->bxData->addSourceDescriptionField($attributeSourceKey, $labelColumns);
                            break;
                        case 'visibility':
                        case 'status':
                        case 'special_from_date':
                        case 'special_to_date':
                            $lc = array();
                            foreach ($languages as $lcl) {
                                $lc[$lcl] = 'value_' . $lcl;
                            }
                            $this->bxData->addSourceLocalizedTextField($attributeSourceKey, $fieldId, $lc);
                            break;
                        case 'price':
                            if(!$global){
                                $col = null;
                                foreach($labelColumns as $k => $v) {
                                    $col = $v;
                                    break;
                                }
                                $this->bxData->addSourceListPriceField($mainSourceKey, 'entity_id');
                            }else {
                                $this->bxData->addSourceListPriceField($mainSourceKey, 'entity_id');
                            }

                            if(!$global){
                                $this->bxData->addSourceLocalizedTextField($attributeSourceKey, "price_localized", $labelColumns);
                            } else {
                                $this->bxData->addSourceStringField($attributeSourceKey, "price_localized", 'value');
                            }

                            $paramPriceLabel = $global ? 'value' : reset($labelColumns);
                            $this->bxData->addFieldParameter($mainSourceKey,'bx_listprice', 'pc_fields', 'CASE WHEN (price.'.$paramPriceLabel.' IS NULL OR price.'.$paramPriceLabel.' <= 0) AND ref.value IS NOT NULL then ref.value ELSE price.'.$paramPriceLabel.' END as price_value');
                            $this->bxData->addFieldParameter($mainSourceKey,'bx_listprice', 'pc_tables', 'LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_product_price` as price ON t.entity_id = price.entity_id, LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_resource_price` as ref ON t.entity_id = ref.parent_id');

                            $this->bxData->addResourceFile($files->getPath($attribute['attribute_code'] . '.csv'), 'parent_id','value');
                            break;
                        case 'special_price':
                            if(!$global){
                                $col = null;
                                foreach($labelColumns as $k => $v) {
                                    $col = $v;
                                    break;
                                }
                                $this->bxData->addSourceDiscountedPriceField($mainSourceKey, 'entity_id');
                            }else {
                                $this->bxData->addSourceDiscountedPriceField($mainSourceKey, 'entity_id');
                            }
                            if(!$global){
                                $this->bxData->addSourceLocalizedTextField($attributeSourceKey, "special_price_localized", $labelColumns);
                            } else {
                                $this->bxData->addSourceStringField($attributeSourceKey, "special_price_localized", 'value');
                            }

                            $paramSpecialPriceLabel = $global ? 'value' : reset($labelColumns);
                            $this->bxData->addFieldParameter($mainSourceKey,'bx_discountedprice', 'pc_fields', 'CASE WHEN (price.'.$paramSpecialPriceLabel.' IS NULL OR price.'.$paramSpecialPriceLabel.' <= 0) AND ref.value IS NOT NULL then ref.value ELSE price.'.$paramSpecialPriceLabel.' END as price_value');
                            $this->bxData->addFieldParameter($mainSourceKey,'bx_discountedprice', 'pc_tables', 'LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_product_special_price` as price ON t.entity_id = price.entity_id, LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_resource_special_price` as ref ON t.entity_id = ref.parent_id');

                            $this->bxData->addResourceFile($files->getPath($attribute['attribute_code'] . '.csv'), 'parent_id','value');
                            break;
                        case (($attributeType == 'int' || $attributeType == 'decimal')) && $attribute['is_global'] == 1:
                            $this->bxData->addSourceNumberField($attributeSourceKey, $fieldId, 'value');
                            break;
                        default:
                            if(!$global){
                                $this->bxData->addSourceLocalizedTextField($attributeSourceKey, $fieldId, $labelColumns);
                            }else {
                                $this->bxData->addSourceStringField($attributeSourceKey, $fieldId, 'value');
                            }
                            break;
                    }
                }
                $data = null;
                $additionalData = null;
                $d = null;
                $labelColumns = null;
            }
        }
        $this->bxData->addSourceNumberField($mainSourceKey, 'bx_grouped_price', 'entity_id');
        $this->bxData->addFieldParameter($mainSourceKey,'bx_grouped_price', 'pc_fields', 'CASE WHEN sref.value IS NOT NULL AND sref.value > 0 AND (ref.value IS NULL OR sref.value+0 < ref.value+0) THEN sref.value WHEN ref.value IS NOT NULL then ref.value WHEN sprice.'.$paramSpecialPriceLabel.' IS NOT NULL AND sprice.'.$paramSpecialPriceLabel.' > 0 AND price.'.$paramPriceLabel.'+0 > sprice.'.$paramSpecialPriceLabel.'+0 THEN sprice.'.$paramSpecialPriceLabel.' ELSE price.'.$paramPriceLabel.' END as price_value');
        $this->bxData->addFieldParameter($mainSourceKey,'bx_grouped_price', 'pc_tables', 'LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_product_price` as price ON t.entity_id = price.entity_id, LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_resource_price` as ref ON t.group_id = ref.parent_id, LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_product_special_price` as sprice ON t.entity_id = sprice.entity_id, LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_resource_special_price` as sref ON t.group_id = sref.parent_id');
        $this->bxData->addFieldParameter($mainSourceKey,'bx_grouped_price', 'multiValued', 'false');
    }

    /**
     * @param $account
     * @param $languages
     * @param $files
     */
    protected function exportProductInformation($files, $duplicateIds, $account, $languages){

        $result = array();
        $db = $this->_getReadAdapter();
        //product stock
        $select = $db->select()
            ->from(
                $db->getTableName($this->_prefix . 'cataloginventory_stock_item'),
                array('entity_id' => 'product_id', 'qty', 'is_in_stock')
            );
        if($this->indexType == 'delta')$select->where('product_id IN(?)', $this->deltaIds);

        $result = $db->query($select);

        if($result->rowCount()){
            while($row = $result->fetch()){
                $data[] = array('entity_id'=>$row['entity_id'], 'qty'=>$row['qty'], 'is_in_stock'=>$row['is_in_stock']);
                if(isset($duplicateIds[$row['entity_id']])){
                    $data[] = array('entity_id'=>'duplicate'.$row['entity_id'], 'qty'=>$row['qty'], 'is_in_stock'=>$row['is_in_stock']);
                }
            }
            $d = array_merge(array(array('entity_id', 'qty', 'is_in_stock')), $data);
            $files->savePartToCsv('product_stock.csv', $d);
            $data = null;
            $d = null;
            $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_stock.csv'), 'entity_id');
            $this->bxData->addSourceNumberField($attributeSourceKey, 'qty', 'qty');
            $this->bxData->addSourceStringField($attributeSourceKey, 'is_in_stock', 'is_in_stock');
        }
        $result = null;

        //product parent categories
        $select1 = $db->select()
            ->from(
                array('c_p_e' => $db->getTableName($this->_prefix . 'catalog_product_entity')),
                array('c_p_e.entity_id',)
            )
            ->joinLeft(
                array('c_p_r' => $db->getTableName($this->_prefix . 'catalog_product_relation')),
                'c_p_e.entity_id = c_p_r.child_id',
                array()
            );

        if($this->indexType == 'delta')$select1->where('c_p_e.entity_id IN(?)', $this->deltaIds);
        $select2 = clone $select1;
        $select2->join(array('c_c_p' => $db->getTableName($this->_prefix . 'catalog_category_product')),
            'c_c_p.product_id = c_p_r.parent_id',
            array(
                'category_id'
            )
        );
        $select1->join(array('c_c_p' => $db->getTableName($this->_prefix . 'catalog_category_product')),
            'c_c_p.product_id = c_p_e.entity_id AND c_p_r.parent_id IS NULL',
            array(
                'category_id'
            )
        );
        $select = $db->select()->union(
            array($select1, $select2),
            \Zend_Db_Select::SQL_UNION
        );

        $result = $db->query($select);

        if($result->rowCount()){
            while($row = $result->fetch()){
                $data[] = $row;
            }
            $result = null;

            $select = $db->select()
                ->from(
                    array('c_p_e' => $db->getTableName($this->_prefix . 'catalog_product_entity')),
                    array('entity_id')
                )->join(
                    array('c_c_p' => $db->getTableName($this->_prefix . 'catalog_category_product')),
                    'c_c_p.product_id = c_p_e.entity_id',
                    array(
                        'category_id'
                    )
                )->where('c_p_e.entity_id IN(?)', $duplicateIds);

            $result = $db->fetchAll($select);
            foreach ($result as $row){
                $row['entity_id'] = 'duplicate'.$row['entity_id'];
                $data[] = $row;
            }
            $duplicateResult = null;
            $d = array_merge(array(array('entity_id', 'category_id')), $data);
            $files->savePartToCsv('product_categories.csv', $d);
            $data = null;
            $d = null;
        }
        $result = null;

        //product super link
        $select = $db->select()
            ->from(
                $db->getTableName($this->_prefix . 'catalog_product_super_link'),
                array('entity_id' => 'product_id', 'parent_id', 'link_id')
            );
        if($this->indexType == 'delta')$select->where('product_id IN(?)', $this->deltaIds);


        $result = $db->query($select);

        if($result->rowCount()){
            while($row = $result->fetch()){
                $data[] = $row;
                if(isset($duplicateIds[$row['entity_id']])){
                    $row['entity_id'] = 'duplicate'.$row['entity_id'];
                    $data[] = $row;
                }
            }
            $d = array_merge(array(array('entity_id', 'parent_id', 'link_id')), $data);
            $files->savePartToCsv('product_parent.csv', $d);
            $data = null;
            $d = null;
            $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_parent.csv'), 'entity_id');
            $this->bxData->addSourceStringField($attributeSourceKey, 'parent_id', 'parent_id');
            $this->bxData->addSourceStringField($attributeSourceKey, 'link_id', 'link_id');
        }
        $result = null;

        //product link
        $select = $db->select()
            ->from(
                array('pl'=> $db->getTableName($this->_prefix . 'catalog_product_link')),
                array(
                    'entity_id' => 'product_id',
                    'linked_product_id',
                    'lt.code'
                )
            )
            ->joinLeft(
                array('lt' => $db->getTableName($this->_prefix . 'catalog_product_link_type')),
                'pl.link_type_id = lt.link_type_id', array()
            )
            ->where('lt.link_type_id = pl.link_type_id');
        if($this->indexType == 'delta')$select->where('pl.product_id IN(?)', $this->deltaIds);


        $result = $db->query($select);

        if($result->rowCount()){
            while($row = $result->fetch()){
                $data[] = $row;
                if(isset($duplicateIds[$row['entity_id']])){
                    $row['entity_id'] = 'duplicate'.$row['entity_id'];
                    $data[] = $row;
                }
            }
            $d = array_merge(array(array('entity_id', 'linked_product_id', 'code')), $data);
            $files->savePartToCsv('product_links.csv', $d);
            $data = null;
            $d = null;
            $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_links.csv'), 'entity_id');
            $this->bxData->addSourceStringField($attributeSourceKey, 'code', 'code');
            $this->bxData->addSourceStringField($attributeSourceKey, 'linked_product_id', 'linked_product_id');
        }
        $result = null;

        //product parent title
        $entity_type = $this->getEntityTypeId('catalog_product');
        $attrId = $this->getAttributeId('name', $entity_type);
        $lvh = array();
        foreach ($languages as $language) {
            $lvh[$language] = 'value_'.$language;
            $store = $this->config->getStore($account, $language);
            $storeId = $store->getId();
            $store = null;

            $select1 = $db->select()
                ->from(
                    array('c_p_e' => $db->getTableName($this->_prefix . 'catalog_product_entity')),
                    array('entity_id')
                )
                ->joinLeft(
                    array('c_p_r' => $db->getTableName($this->_prefix . 'catalog_product_relation')),
                    'c_p_e.entity_id = c_p_r.child_id',
                    array('parent_id')
                );

            $select1->where('t_d.attribute_id = ?', $attrId)->where('t_d.store_id = 0 OR t_d.store_id = ?',$storeId);
            if($this->indexType == 'delta')$select1->where('c_p_e.entity_id IN(?)', $this->deltaIds);

            $select2 = clone $select1;
            $select2->join(
                array('t_d' => $db->getTableName($this->_prefix . 'catalog_product_entity_varchar')),
                't_d.entity_id = c_p_e.entity_id AND c_p_r.parent_id IS NULL',
                array(
                    't_d.value',
                    't_d.store_id'
                )
            );
            $select1->join(
                array('t_d' => $db->getTableName($this->_prefix . 'catalog_product_entity_varchar')),
                't_d.entity_id = c_p_r.parent_id',
                array(
                    't_d.value',
                    't_d.store_id'
                )
            );
            $select = $db->select()->union(
                array($select1, $select2),
                \Zend_Db_Select::SQL_UNION
            );

            $result = $db->query($select);

            if($result->rowCount()){
                while($row = $result->fetch()){
                    if (isset($data[$row['entity_id']])) {
                        if(isset($data[$row['entity_id']]['value_' . $language])){
                            if($row['store_id'] > 0){
                                $data[$row['entity_id']]['value_' . $language] = $row['value'];
                            }
                        }else{
                            $data[$row['entity_id']]['value_' . $language] = $row['value'];
                        }
                        continue;
                    }
                    $data[$row['entity_id']] = array('entity_id' => $row['entity_id'], 'value_' . $language => $row['value']);
                }

                $result = null;
                $select = $db->select()
                    ->from(
                        array('c_p_e' => $db->getTableName($this->_prefix . 'catalog_product_entity')),
                        array('entity_id', new \Zend_Db_Expr("CASE WHEN c_p_e_v_b.value IS NULL THEN c_p_e_v_a.value ELSE c_p_e_v_b.value END as value"))
                    )->joinLeft(
                        array('c_p_e_v_a' => $db->getTableName($this->_prefix . 'catalog_product_entity_varchar')),
                        '(c_p_e_v_a.attribute_id = ' . $attrId . ' AND c_p_e_v_a.store_id = 0) AND (c_p_e_v_a.entity_id = c_p_e.entity_id)',
                        array()
                    )->joinLeft(
                        array('c_p_e_v_b' => $db->getTableName($this->_prefix . 'catalog_product_entity_varchar')),
                        '(c_p_e_v_b.attribute_id = ' . $attrId . ' AND c_p_e_v_b.store_id = ' . $storeId . ') AND (c_p_e_v_b.entity_id = c_p_e.entity_id)',
                        array()
                    )->where('c_p_e.entity_id IN (?)', $duplicateIds);

                $result = $db->fetchAll($select);
                foreach ($result as $row){
                    $row['entity_id'] = 'duplicate'.$row['entity_id'];
                    if (isset($data[$row['entity_id']])) {
                        $data[$row['entity_id']]['value_' . $language] = $row['value'];
                        continue;
                    }
                    $data[$row['entity_id']] = array('entity_id' => $row['entity_id'], 'value_' . $language => $row['value']);
                }
            }
            $result = null;
        }
        $headerLangRow = array_merge(array('entity_id'), $lvh);
        $data = array_merge(array($headerLangRow), $data);
        $files->savePartToCsv('product_bx_parent_title.csv', $data);
        $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_bx_parent_title.csv'), 'entity_id');
        $this->bxData->addSourceLocalizedTextField($attributeSourceKey, 'bx_parent_title', $lvh);
        $this->bxData->addFieldParameter($attributeSourceKey,'bx_parent_title', 'multiValued', 'false');
    }

    /**
     * @param $account
     * @param $files
     * @throws Zend_Db_Select_Exception
     */
    protected function exportCustomers($account, $files){
        if(!$this->config->isCustomersExportEnabled($account)) {
            return;
        }

        $limit = 1000;
        $count = $limit;
        $page = 1;
        $header = true;

        $attrsFromDb = array(
            'int' => array(),
            'static' => array(), // only supports email
            'varchar' => array(),
            'datetime' => array(),
        );

        $customer_attributes = $this->getCustomerAttributes($account);

        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('main_table' => $db->getTableName($this->_prefix . 'eav_attribute')),
                array(
                    'aid' => 'attribute_id',
                    'attribute_code',
                    'backend_type',
                )
            )
            ->joinInner(
                array('additional_table' => $db->getTableName($this->_prefix . 'customer_eav_attribute')),
                'additional_table.attribute_id = main_table.attribute_id',
                array()
            )
            ->where('main_table.entity_type_id = ?', $this->getEntityTypeId('customer'))
            ->where('main_table.attribute_code IN (?)', $customer_attributes);

        $result = $db->query($select);
        if($result->rowCount()){
            while($row = $result->fetch()){
                if (isset($attrsFromDb[$row['backend_type']])) {
                    $attrsFromDb[$row['backend_type']][] = $row['backend_type'] == 'static' ? $row['attribute_code'] : $row['aid'];
                }
            }
        }
        do{
            $customers_to_save = array();

            $select = $db->select()
                ->from(
                    array('c_e' => $db->getTableName($this->_prefix . 'customer_entity')),
                    array_merge(['entity_id'], $attrsFromDb['static'])
                )->join(
                    array('c_a' => $db->getTableName($this->_prefix . 'customer_address_entity')),
                    'c_e.entity_id = c_a.parent_id',
                    array('address_id' => 'c_a.entity_id')
                )
                ->group('c_e.entity_id')
                ->limit($limit, ($page - 1) * $limit);

            $ids = array();
            $customers = array();
            foreach ($db->fetchAll($select) as $customer) {
                $id =  $customer['entity_id'];
                $ids[] = $id;
                $customers[$id] = $customer;
            }

            $columns = array(
                'entity_id',
                'attribute_id',
                'value',
            );

            $select = $db->select()
                ->where('ce.entity_type_id = ?', 1)
                ->where('ce.entity_id IN (?)', $ids);

            $select1 = null;
            $select2 = null;
            $select3 = null;
            $select4 = null;

            $selects = array();

            if (count($attrsFromDb['varchar']) > 0) {
                $select1 = clone $select;
                $select1->from(array('ce' => $db->getTableName($this->_prefix . 'customer_entity_varchar')), $columns)
                    ->joinLeft(array('ea' => $db->getTableName($this->_prefix . 'eav_attribute')), 'ce.attribute_id = ea.attribute_id', 'ea.attribute_code')
                    ->where('ce.attribute_id IN(?)', $attrsFromDb['varchar']);
                $selects[] = $select1;
            }

            if (count($attrsFromDb['int']) > 0) {
                $select2 = clone $select;
                $select2->from(array('ce' => $db->getTableName($this->_prefix . 'customer_entity_int')), $columns)
                    ->joinLeft(array('ea' => $db->getTableName($this->_prefix . 'eav_attribute')), 'ce.attribute_id = ea.attribute_id', 'ea.attribute_code')
                    ->where('ce.attribute_id IN(?)', $attrsFromDb['int']);
                $selects[] = $select2;
            }

            if (count($attrsFromDb['datetime']) > 0) {
                $select3 = clone $select;
                $select3->from(array('ce' => $db->getTableName($this->_prefix . 'customer_entity_datetime')), $columns)
                    ->joinLeft(array('ea' => $db->getTableName($this->_prefix . 'eav_attribute')), 'ce.attribute_id = ea.attribute_id', 'ea.attribute_code')
                    ->where('ce.attribute_id IN(?)', $attrsFromDb['datetime']);
                $selects[] = $select3;
            }

            $select = $db->select()
                ->union(
                    $selects,
                    Zend_Db_Select::SQL_UNION_ALL
                );

            $result = $db->query($select);
            if($result->rowCount()){
                while($row = $result->fetch()){
                    $customers[$row['entity_id']][$row['attribute_code']] = $row['value'];
                }
            }

            $customer_entity_type_id = $this->getEntityTypeId('customer_address');
            $select = null;
            $select1 = null;
            $select2 = null;
            $select3 = null;
            $select4 = null;
            $selects = null;

            $select = $db->select()
                ->from(
                    $db->getTableName($this->_prefix . 'eav_attribute'),
                    array(
                        'attribute_id',
                        'attribute_code',
                    )
                )
                ->where('entity_type_id = ?', $customer_entity_type_id)
                ->where('attribute_code IN (?)', array('country_id', 'postcode'));

            $addressAttr = array();

            $result = $db->query($select);
            if($result->rowCount()){
                while($row = $result->fetch()){
                    $addressAttr[$row['attribute_id']] = $row['attribute_code'];
                }
            }

            $addressIds = array_keys($addressAttr);

            foreach ($customers as $customer) {
                $id = $customer['entity_id'];


                $select = $db->select()
                    ->from($db->getTableName($this->_prefix . 'customer_address_entity_varchar'),
                        array('attribute_id', 'value')
                    )
                    ->where('entity_type_id = ?', $customer_entity_type_id)
                    ->where('entity_id = ?', $customer['address_id'])
                    ->where('attribute_id IN(?)', $addressIds);

                $billingResult = array();
                $result = $db->query($select);
                if($result->rowCount()){
                    while($row = $result->fetch()){
                        $billingResult[$addressAttr[$row['attribute_id']]] = $row['value'];
                    }
                }

                $countryCode = null;
                if(isset($billingResult['country_id'])){
                    $countryCode = $billingResult['country_id'];
                }

                if (array_key_exists('gender', $customer)) {
                    if ($customer['gender'] % 2 == 0) {
                        $customer['gender'] = 'female';
                    } else {
                        $customer['gender'] = 'male';
                    }
                }

                $customer_to_save = array(
                    'customer_id' => $id,
                    'country' => !empty($countryCode) ? $this->_helperExporter->getCountry($countryCode)->getName() : '',
                    'zip' => array_key_exists('postcode', $billingResult) ? $billingResult['postcode'] : '',
                );

                foreach($customer_attributes as $attr) {
                    $customer_to_save[$attr] = array_key_exists($attr, $customer) ? $customer[$attr] : '';
                }
                $customers_to_save[] = $customer_to_save;
            }

            $data = $customers_to_save;

            if (count($customers) == 0 && $header) {
                return null;
            }

            if ($header) {
                $data = array_merge(array(array_keys(end($customers_to_save))), $customers_to_save);
                $header = false;
            }
            $files->savePartToCsv('customers.csv', $data);
            $data = null;

            $count = count($customers_to_save);
            $page++;

        }while($count >= $limit);
        $customers = null;

        if ($this->config->isCustomersExportEnabled($account)) {

            $customerSourceKey = $this->bxData->addMainCSVCustomerFile($files->getPath('customers.csv'), 'customer_id');

            foreach ($customer_attributes as $prop) {
                if($prop == 'id') {
                    continue;
                }
                $this->bxData->addSourceStringField($customerSourceKey, $prop, $prop);
            }

            Mage::log("bxLog: Customers - exporting additional tables for account: {$account}", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            $this->exportExtraTables('customers', $files, $this->config->getAccountExtraTablesByEntityType($account,'customers'));
        }
    }

    /**
     * @param $account
     * @return array
     */
    protected function getCustomerAttributes($account)
    {
        $attributes = array();
        Mage::log('bxLog: get all customer attributes for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);

        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('main_table' => $db->getTableName($this->_prefix . 'eav_attribute')),
                array(
                    'attribute_code',
                )
            )
            ->where('main_table.entity_type_id = ?', $this->getEntityTypeId('customer'));

        $result = $db->query($select);
        if($result->rowCount()){
            while($row = $result->fetch()){
                $attributes[$row['attribute_code']] = $row['attribute_code'];
            }
        }

        $requiredProperties = array('dob', 'gender');
        Mage::log('bxLog: get configured customer attributes for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $filteredAttributes = $this->config->getAccountCustomersProperties($account, $attributes, $requiredProperties);

        foreach($attributes as $k => $attribute) {
            if(!in_array($attribute, $filteredAttributes)) {
                unset($attributes[$k]);
            }
        }
        Mage::log('bxLog: returning configured customer attributes for account ' . $account . ': ' . implode(',', array_values($attributes)), Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        return $attributes;
    }

    /**
     * @param $account
     * @param $files
     */
    protected function exportTransactions($account, $files){

        if(!$this->config->isTransactionsExportEnabled($account)){
            return;
        }

        $db = $this->_getReadAdapter();
        $limit = 5000;
        $page = 1;
        $header = true;
        $transactions_to_save = array();

        $salt = $db->quote(
            ((string) Mage::getConfig()->getNode('global/crypt/key')) .
            $account
        );

        $export_mode = $this->config->getTransactionMode($account);
        $date = date("Y-m-d H:i:s", strtotime("-1 month"));
        $transaction_attributes = $this->getTransactionAttributes($account);
        $sales_order_table = $db->getTableName($this->_prefix . 'sales_flat_order');
        $sales_order_item = $db->getTableName($this->_prefix . 'sales_flat_order_item');
        $sales_order_address = $db->getTableName($this->_prefix . 'sales_flat_order_address');
        $temp_select = $db
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
            );

        if ($export_mode == 0) {
            $temp_select->where('order.created_at >= ?', $date);
        }

        if (count($transaction_attributes)) {
            $billing_columns = $shipping_columns = array();
            foreach ($transaction_attributes as $attribute) {
                $billing_columns['billing_' . $attribute] = $attribute;
                $shipping_columns['shipping_' . $attribute] = $attribute;
            }
            $temp_select
                ->joinLeft(
                    array('billing_address' => $sales_order_address),
                    'order.billing_address_id = billing_address.entity_id',
                    $billing_columns
                )
                ->joinLeft(
                    array('shipping_address' => $sales_order_address),
                    'order.shipping_address_id = shipping_address.entity_id',
                    $shipping_columns
                );
        }

        while(true){

            $configurable = array();
            $select = clone $temp_select;
            $select->limit($limit, ($page - 1) * $limit);
            $result = $db->query($select);
            $select = null;

            if($result->rowCount()){
                while($transaction = $result->fetch()){

                    if ($transaction['product_type'] == 'configurable') {
                        $configurable[$transaction['product_id']] = $transaction;
                        continue;
                    }

                    $productOptions = unserialize($transaction['product_options']);
                    if (intval($transaction['price']) == 0 && $transaction['product_type'] == 'simple' && isset($productOptions['info_buyRequest']['product'])) {
                        if (isset($configurable[$productOptions['info_buyRequest']['product']])) {
                            $pid = $configurable[$productOptions['info_buyRequest']['product']];

                            $transaction['original_price'] = $pid['original_price'];
                            $transaction['price'] = $pid['price'];
                        } else {
                            try {
                                $pid = Mage::getModel('catalog/product')->load($productOptions['info_buyRequest']['product']);

                                $transaction['original_price'] = ($pid->getPrice());
                                $transaction['price'] = ($pid->getPrice());

                                $tmp = array();
                                $tmp['original_price'] = $transaction['original_price'];
                                $tmp['price'] = $transaction['price'];

                                $configurable[$productOptions['info_buyRequest']['product']] = $tmp;
                                $tmp = null;
                            } catch (\Exception $e) {
                                Mage::log($e, Zend_Log::CRIT, self::BOXALINO_LOG_FILE);
                            }
                        }
                        $pid = null;
                    }

                    $status = 0;
                    if ($transaction['updated_at'] != $transaction['created_at']) {
                        switch ($transaction['status']) {
                            case 'canceled':
                                continue;
                                break;
                            case 'processing':
                                $status = 1;
                                break;
                            case 'complete':
                                $status = 2;
                                break;
                        }
                    }
                    $final_transaction = array(
                        'order_id' => $transaction['entity_id'],
                        'entity_id' => $transaction['product_id'],
                        'customer_id' => $transaction['customer_id'],
                        'guest_id' => $transaction['guest_id'],
                        'price' => $transaction['original_price'],
                        'discounted_price' => $transaction['price'],
                        'quantity' => $transaction['qty_ordered'],
                        'total_order_value' => ($transaction['base_subtotal'] + $transaction['shipping_amount']),
                        'shipping_costs' => $transaction['shipping_amount'],
                        'order_date' => $transaction['created_at'],
                        'confirmation_date' => $status == 1 ? $transaction['updated_at'] : null,
                        'shipping_date' => $status == 2 ? $transaction['updated_at'] : null,
                        'status' => $transaction['status'],
                    );
                    $status = null;
                    if (count($transaction_attributes)) {
                        foreach ($transaction_attributes as $attribute) {
                            $final_transaction['billing_' . $attribute] = $transaction['billing_' . $attribute];
                            $final_transaction['shipping_' . $attribute] = $transaction['shipping_' . $attribute];
                        }
                    }

                    $transactions_to_save[] = $final_transaction;
                    $productOptions = null;
                    $guest_id_transaction = null;
                    $final_transaction = null;
                }
            }else{
                if($page == 1) {
                    return ;
                }
                break;
            }

            $data = $transactions_to_save;
            $transactions_to_save = null;
            $configurable = null;
            $transactions = null;
            $transaction = null;

            if ($header) {
                $data = array_merge(array(array_keys(end($data))), $data);
                $header = false;
            }
            Mage::log('bxLog: Transactions - save to file for account ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            $files->savePartToCsv('transactions.csv', $data);
            $data = null;
            $result = null;
            $page++;
        }
        $sourceKey = $this->bxData->setCSVTransactionFile($files->getPath('transactions.csv'), 'order_id', 'entity_id', 'customer_id', 'order_date', 'total_order_value', 'price', 'discounted_price');
        $this->bxData->addSourceCustomerGuestProperty($sourceKey,'guest_id');

        Mage::log("bxLog: Transactions - exporting additional tables for account: {$account}", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $this->exportExtraTables('transactions', $files, $this->config->getAccountExtraTablesByEntityType($account,'transactions'));

        Mage::log('bxLog: Transactions - end of export for account ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
    }

    /**
     * @param $account
     * @return array
     */
    protected function getTransactionAttributes($account){

        $setupConfig = Mage::getConfig()->getResourceConnectionConfig("default_setup");

        if(!isset($setupConfig->dbname)) {
            Mage::log("default_setup configuration doesn't provide a dbname in getResourceConnectionConfig", Zend_Log::WARN, self::BOXALINO_LOG_FILE);
            return array();
        }
        $attributes = array();
        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                'INFORMATION_SCHEMA.COLUMNS',
                array('COLUMN_NAME')
            )
            ->where('TABLE_SCHEMA=?', $setupConfig->dbname)
            ->where('TABLE_NAME=?', $db->getTableName($this->_prefix . 'sales_flat_order_address'));
        $this->_entityIds = array();

        $result = $db->query($select);
        if($result->rowCount()){
            while($row = $result->fetch()){
                $attributes[$row['COLUMN_NAME']] = $row['COLUMN_NAME'];
            }
        }

        $requiredProperties = array();
        $filteredAttributes = $this->config->getAccountTransactionsProperties($account, $attributes, $requiredProperties);

        foreach($attributes as $k => $attribute) {
            if(!in_array($attribute, $filteredAttributes)) {
                unset($attributes[$k]);
            }
        }
        return $attributes;
    }

    /**
     * @param $attr_code
     * @return mixed
     * @throws \Exception
     */
    protected function getProductCategoryAttributeId($attr_code){

        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('a_t' => $db->getTableName($this->_prefix . 'eav_attribute'))
            )->where('a_t.entity_type_id = 3 AND a_t.attribute_code = ?', $attr_code);

        try{
            return $db->fetchRow($select)['attribute_id'];
        }catch(\Exception $e){
            throw $e;
        }
    }

    /**
     * @param $store
     * @param $language
     * @param $transformedCategories
     * @return mixed
     */
    protected function exportCategories($store, $language, $transformedCategories)
    {

        $rootid = $store->getRootCategoryId();
        $categoryTypeId = $this->getEntityTypeId('catalog_category');
        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('c_t' => $db->getTableName($this->_prefix . 'catalog_category_entity')),
                array('entity_id', 'parent_id')
            )
            ->joinInner(
                array('c_v' => $db->getTableName($this->_prefix . 'catalog_category_entity_varchar')),
                'c_v.entity_id = c_t.entity_id',
                array('c_v.value', 'c_v.store_id')
            )
            ->where('c_v.attribute_id = ? ', $this->getAttributeId('name', $categoryTypeId))
            ->where('c_v.store_id = ? OR c_v.store_id = 0', $store->getId())
            ->where('c_t.path like \'1/'.$rootid.'%\'');
        $result = $db->query($select);
        if($result->rowCount()){
            while($row = $result->fetch()){
                if (!$row['parent_id'])  {
                    continue;
                }
                if(isset($transformedCategories[$row['entity_id']])) {
                    if(isset($transformedCategories[$row['entity_id']]['value_' .$language]) && $row['store_id'] == 0){
                        continue;
                    }
                    $transformedCategories[$row['entity_id']]['value_' .$language] = $row['value'];
                    continue;
                }
                $transformedCategories[$row['entity_id']] = array(
                    'category_id' => $row['entity_id'],
                    'parent_id' => $row['parent_id'],
                    'value_' . $language => $row['value']
                );
            }
        }
        return $transformedCategories;
    }

    /**
     * @param $entity_type_code
     * @return null
     */
    protected function getEntityTypeId($entity_type_code)
    {
        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('e_e_t' => $db->getTableName($this->_prefix . 'eav_entity_type')),
                array('entity_type_id')
            )->where('e_e_t.entity_type_code = ?', $entity_type_code);

        $result = $db->query($select);
        if($result->rowCount()){
            while ($row = $result->fetch()) {
                return $row['entity_type_id'];
            }
        }
        return null;
    }

    /**
     * @param $attr_code
     * @return null
     */
    protected function getAttributeId($attr_code, $type_id){
        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('e_a' => $db->getTableName($this->_prefix . 'eav_attribute')),
                array('attribute_id')
            )->where('e_a.attribute_code = ?', $attr_code)->where('e_a.entity_type_id = ?', $type_id);

        $result = $db->query($select);
        if($result->rowCount()){
            while ($row = $result->fetch()) {
                return $row['attribute_id'];
            }

        }
        return null;
    }


    /**
     * @param $account
     * @param $languages
     * @return array
     * @throws \Exception
     */
    protected function getDuplicateIds($account, $languages){
        $ids = array();
        $db = $this->_getReadAdapter();
        $entity_type = $this->getEntityTypeId('catalog_product');
        $attrId = $this->getAttributeId('visibility', $entity_type);
        foreach ($languages as $language){
            $storeObject = $this->config->getStore($account, $language);
            $storeId = $storeObject->getId();
            $storeObject = null;
            $select = $db->select()
                ->from(
                    array('c_p_r' => $db->getTableName($this->_prefix . 'catalog_product_relation')),
                    array(
                        'parent_id',
                        'child_id',
                        new \Zend_Db_Expr("CASE WHEN c_p_e_b.value IS NULL THEN c_p_e_a.value ELSE c_p_e_b.value END as value")
                    )
                )->joinLeft(
                    array('c_p_e_a' => $db->getTableName($this->_prefix . 'catalog_product_entity_int')),
                    'c_p_e_a.entity_id = c_p_r.child_id AND c_p_e_a.store_id = 0 AND c_p_e_a.attribute_id = ' . $attrId,
                    array('c_p_e_a.store_id')
                )->joinLeft(
                    array('c_p_e_b' => $db->getTableName($this->_prefix . 'catalog_product_entity_int')),
                    'c_p_e_b.entity_id = c_p_r.child_id AND c_p_e_b.store_id = ' . $storeId . ' AND c_p_e_b.attribute_id = ' . $attrId,
                    array('c_p_e_b.store_id')
                );
            $fetchedResult = $db->fetchAll($select);

            foreach ($fetchedResult as $r){
                if($r['value'] != Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE) {
                    $ids[$r['child_id']] = $r['child_id'];
                }
            }
        }
        return $ids;
    }

    /**
     * @return int
     */
    protected function _getLastIndex()
    {
        if ($this->_lastIndex == 0) {
            $this->_setLastIndex();
        }
        return $this->_lastIndex;
    }

    /**
     *
     */
    protected function _setLastIndex()
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

    /**
     *
     */
    protected function loadBxLibrary(){
        $libPath = __DIR__ . '/../../lib';
        require_once($libPath . '/BxClient.php');
        \com\boxalino\bxclient\v1\BxClient::LOAD_CLASSES($libPath);
    }

    /**
     * @param $id
     * @param $attributeId
     * @param $storeId
     * @return mixed
     */
    protected function getProductAttributeFromId($id, $attributeId, $storeId){
        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('c_p_e' => $db->getTableName($this->_prefix . 'catalog_product_entity')),
                array(new \Zend_Db_Expr("CASE WHEN c_p_e_b.value IS NULL THEN c_p_e_a.value ELSE c_p_e_b.value END as value"))
            )
            ->joinLeft(
                array('c_p_e_a' => $db->getTableName($this->_prefix . 'catalog_product_entity_int')),
                'c_p_e_a.entity_id = c_p_e.entity_id AND c_p_e_a.store_id = 0 AND c_p_e_a.attribute_id = ' . $attributeId,
                array()
            )
            ->joinLeft(
                array('c_p_e_b' => $db->getTableName($this->_prefix . 'catalog_product_entity_int')),
                'c_p_e_b.entity_id = c_p_e.entity_id AND c_p_e_b.store_id = ' . $storeId . ' AND c_p_e_b.attribute_id = ' . $attributeId,
                array()
            )
            ->where('c_p_e.entity_id = ?', $id);

        return $db->fetchRow($select)['value'];
    }

    /**
     * Exporting additional tables that are related to entities
     * No logic on the connection is defined
     * To be added in the ETL
     *
     * @param $entity
     * @param $files
     * @param array $tables
     * @return $this
     */
    public function exportExtraTables($entity, $files, $tables = [])
    {
        if(empty($tables))
        {
            Mage::log("bxLog: {$entity} no additional tables have been found.", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            return $this;
        }

        foreach($tables as $table)
        {
            Mage::log("bxLog: Extra table - {$table}.", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            try{
                $columns = $this->getColumnsByTableName($table);
                $tableContent = $this->getTableContent($table);
                if(!is_array($tableContent))
                {
                    throw new Exception("Extra table {$table} content empty.");
                }
                $dataToSave = array_merge(array(array_keys(end($tableContent))), $tableContent);

                $fileName = "extra_". $table . ".csv";
                $files->savePartToCsv($fileName, $dataToSave);

                $this->bxData->addExtraTableToEntity($files->getPath($fileName), $entity, reset($columns), $columns);
                Mage::log("bxLog: {$entity} - additional table {$table} exported.", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            } catch (\Exception $exception)
            {
                Mage::log("bxLog: {$entity} additional table error: ". $exception->getMessage(), Zend_Log::ERR, self::BOXALINO_LOG_FILE);
                continue;
            }
        }

        return $this;
    }

    protected function getColumnsByTableName($table)
    {
        $setupConfig = Mage::getConfig()->getResourceConnectionConfig("default_setup");
        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                'INFORMATION_SCHEMA.COLUMNS',
                ['COLUMN_NAME', 'name'=>'COLUMN_NAME']
            )
            ->where('TABLE_SCHEMA=?', $setupConfig->dbname)
            ->where('TABLE_NAME=?', $db->getTableName($table));

        $columns =  $db->fetchPairs($select);
        if(empty($columns))
        {
            throw new \Exception("{$table} does not exist.");
        }

        return $columns;
    }

    protected function getTableContent($table)
    {
        $db = $this->_getReadAdapter();
        try {
            $select = $db->select()
                ->from($table, array('*'));

            return $db->fetchAll($select);
        } catch(\Exception $exc)
        {
            Mage::log("bxLog: {$entity} additional table error: ". $exception->getMessage(), Zend_Log::WARN, self::BOXALINO_LOG_FILE);
            return array();
        }

    }
}
