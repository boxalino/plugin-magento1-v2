<?php

abstract class Boxalino_Intelligence_Model_Mysql4_Indexer extends Mage_Core_Model_Mysql4_Abstract{

    protected $_prefix;

    protected $config;

    protected $bxData;

    protected $indexType;

    protected $_helperExporter;

    protected $_entityTypeIds = null;

    protected $_lastIndex = 0;

    const BOXALINO_LOG_FILE = 'boxalino_exporter.log';

    public function reindexAll()
    {
        $this->loadBxLibrary();
        $this->indexType = static::INDEX_TYPE;
        $this->_prefix = Mage::getConfig()->getTablePrefix();
        $this->exportStores();
        return $this;
    }
    
    protected function exportStores(){
        $this->_helperExporter = Mage::helper('intelligence');

        Mage::log("bxLog: starting exportStores", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $this->config = new Boxalino_Intelligence_Helper_BxIndexConfig();
        Mage::log("bxLog: retrieved index config: " . $this->config->toString(), Zend_Log::INFO, self::BOXALINO_LOG_FILE);

        foreach ($this->config->getAccounts() as $account) {

            Mage::log("bxLog: initialize files on account: " . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            $files = new Boxalino_Intelligence_Helper_BxFiles( $account, $this->config);

            $bxClient = new \com\boxalino\bxclient\v1\BxClient($account, $this->config->getAccountPassword($account), "");
            $this->bxData = new \com\boxalino\bxclient\v1\BxData($bxClient, $this->config->getAccountLanguages($account), $this->config->isAccountDev($account), false);
            Mage::log("bxLog: verify credentials for account: " . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            $this->bxData->verifyCredentials();

            Mage::log("bxLog: verify credentials for account: " . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            $categories = array();
            foreach ($this->config->getAccountLanguages($account) as $language) {
                $store = $this->config->getStore($account, $language);
                Mage::log('bxLog: Start getStoreProductAttributes for language: ' . $language . ' on store: ' . $store->getId(), Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                if($this->indexType == 'full'){
                    Mage::log('bxLog: Start exportCategories for language: ' . $language . ' on store:' . $store->getId(), Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                    $categories = $this->exportCategories($store, $language, $categories);
                }
            }
            Mage::log('bxLog: Export the customers, transactions and product files for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);

            $exportProducts = $this->exportProducts($account, $files);
            if($this->indexType == 'full'){
                $this->exportCustomers($account, $files);
                $this->exportTransactions($account, $files);
                $this->prepareData($account, $files, $categories);
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
                    $changes = $this->bxData->publishChanges($publish);
                    if(sizeof($changes['changes']) > 0 && !$publish) {
                        Mage::log("changes in configuration detected but not published as publish configuration automatically option has not been activated for account: " . $account, Zend_Log::WARN, self::BOXALINO_LOG_FILE);
                    }
                    Mage::log('bxLog: Push the Zip data file to the Data Indexing server for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                }
                try{
                    $this->bxData->pushData();
                }catch(\Exception $e){
                    throw $e;
                }
                Mage::log('bxLog: Finished account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            }
        }
        Mage::log("bxLog: finished exportStores", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
    }

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
        Mage::log('bxLog: get configured product attributes.', Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $attributes = $this->config->getAccountProductsProperties($account, $attributes, $requiredProperties);
        Mage::log('bxLog: returning configured product attributes: ' . implode(',', array_values($attributes)), Zend_Log::INFO, self::BOXALINO_LOG_FILE);

        return $attributes;
    }

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
                    array('p_t' => $db->getTableName($this->_prefix . 'catalog_product_super_link')),
                    'e.entity_id = p_t.product_id', array('group_id' => 'parent_id')
                );
            $this->indexType == 'delta' ? $select->where('created_at >= ? OR updated_at >= ?', $this->_getLastIndex()) : '';


            $data = array();
            $result = $db->query($select);
            if($result->rowCount()){
                while($row = $result->fetch()){
                    if($row['group_id'] == null) $row['group_id'] = $row['entity_id'];
                    $data[$row['entity_id']] = $row;
                    $totalCount++;
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

        $this->exportProductAttributes($attrsFromDb, $languages, $account, $files);
        $this->exportProductInformation($files);
        return true;
    }
    
    protected function exportProductAttributes($attrs = array(), $languages, $account, $files){
        $db = $this->_getReadAdapter();
        $columns = array(
            'entity_id',
            'attribute_id',
            'value',
            'store_id'
        );
        $attrs['misc'][] = array('attribute_code' => 'categories');
        $files->prepareProductFiles($attrs);
        unset($attrs['misc']);

        foreach($attrs as $attributeType => $types){
            $select = $db->select()->from(
                array('t_d' => $db->getTableName($this->_prefix . 'catalog_product_entity_' . $attributeType)),
                $columns
            );
            $this->indexType == 'delta' ? $select->where('created_at >= ? OR updated_at >= ?', $this->_getLastIndex()) : '';

            foreach ($types as $attributeID => $attribute) {
                $optionSelect = in_array($attribute['frontend_input'], array('multiselect','select'));
                $data = array();
                $additionalData = array();
                $global = false;
                $d = array();
                $headerLangRow = array();
                $optionValues = array();

                foreach ($languages as $lang) {

                    $labelColumns[$lang] = 'value_' . $lang;
                    $storeObject = $this->config->getStore($account, $lang);
                    $storeId = $storeObject->getId();
                    if($attribute['attribute_code'] == 'url_key' || $attribute['attribute_code'] == 'image'){
                        $storeBaseUrl = $storeObject->getBaseUrl();
                        $imageBaseUrl = $storeObject->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . "catalog/product";
                    }
                    if ($attribute['attribute_code'] == 'url_key') {
                        if (Mage::getEdition() == Mage::EDITION_ENTERPRISE) {
                            $select1 = $db->select()
                                ->from(
                                    array('t_g' => $db->getTableName($this->_prefix . 'catalog_product_entity_url_key')),
                                    array('entity_id', 'attribute_id')
                                )
                                ->joinLeft(
                                    array('t_s' => $db->getTableName($this->_prefix . 'catalog_product_entity_url_key')),
                                    't_s.attribute_id = t_g.attribute_id AND t_s.entity_id = t_g.entity_id',
                                    array('value' => 'IF(t_s.store_id IS NULL, t_g.value, t_s.value)')
                                )
                                ->where('t_g.attribute_id = ?', $attributeID)->where('t_g.store_id = 0 OR t_g.store_id = ?', $storeId);
                            $this->indexType == 'delta' ? $select->where('created_at >= ? OR updated_at >= ?', $this->_getLastIndex()) : '';
                            $result = $db->query($select1);
                            if($result->rowCount()){
                                while($row = $result->fetch()){
                                    $data[] = $row;
                                }
                            }
                            continue;
                        }
                    }
                    $attributeSelect = clone $select;
                    $attributeSelect
                        ->where('t_d.attribute_id = ?', $attributeID)
                        ->where('t_d.store_id = 0 OR t_d.store_id = ?',$storeId);

                    $result = $db->query($attributeSelect);

                    if ($result->rowCount()) {
                        if($optionSelect){

                            $optionValueSelect = $db->select()
                                ->from(
                                    array('a_o' => $db->getTableName($this->_prefix . 'eav_attribute_option')),
                                    array(
                                        'option_id',
                                        new \Zend_Db_Expr(
                                            "CASE WHEN c_o.value IS NULL THEN b_o.value ELSE c_o.value END as value")
                                    )
                                )->joinLeft(
                                    array('b_o' => $db->getTableName($this->_prefix . 'eav_attribute_option_value')),
                                    'b_o.option_id = a_o.option_id AND b_o.store_id = 0',
                                    array()
                                )->joinLeft(
                                    array('c_o' => $db->getTableName($this->_prefix . 'eav_attribute_option_value')),
                                    'c_o.option_id = a_o.option_id AND c_o.store_id = ' . $storeId,
                                    array()
                                )->where('a_o.attribute_id = ?', $attributeID);

                            $optionResult = $db->query($optionValueSelect);
                            if($optionResult->rowCount()){
                                while($row = $optionResult->fetch()){
                                    if(isset($optionValues[$row['option_id']])){
                                        $optionValues[$row['option_id']]['value_' . $lang] = $row['value'];
                                    }else{
                                        $optionValues[$row['option_id']] = array(
                                            $attribute['attribute_code'] . '_id' => $row['option_id'],
                                            'value_' . $lang => $row['value']);
                                    }
                                }
                            }else{
                                $optionSelect = false;
                            }
                        }

                        while ($row = $result->fetch()) {
                            if (isset($data[$row['entity_id']]) && !$optionSelect) {
                                if($row['store_id'] > $data[$row['entity_id']]['store_id']) {
                                    $data[$row['entity_id']]['value_' . $lang] = $row['value'];
                                    $data[$row['entity_id']]['store_id'] = $row['store_id'];
                                    if(isset($additionalData[$row['entity_id']])){
                                        if ($attribute['attribute_code'] == 'url_key') {
                                            $url = $storeBaseUrl . $row['value'] . '.html';
                                            $additionalData[$row['entity_id']]['value_' . $lang] = $url;
                                        } else {
                                            $url = $imageBaseUrl . $row['value'];
                                            $additionalData[$row['entity_id']]['value_' . $lang] = $url;
                                        }
                                    }
                                    continue;
                                }
                                $data[$row['entity_id']]['value_' . $lang] = $row['value'];

                                if (isset($additionalData[$row['entity_id']])) {
                                    if ($attribute['attribute_code'] == 'url_key') {
                                        $url = $storeBaseUrl . $row['value'] . '.html';
                                        $additionalData[$row['entity_id']]['value_' . $lang] = $url;
                                    } else {
                                        $url = $imageBaseUrl . $row['value'];
                                        $additionalData[$row['entity_id']]['value_' . $lang] = $url;
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
                                    }
                                }
                                if ($attribute['attribute_code'] == 'image') {
                                    if ($this->config->exportProductImages($account)) {
                                        $url = $imageBaseUrl . $row['value'];
                                        $additionalData[$row['entity_id']] = array('entity_id' => $row['entity_id'],
                                            'value_' . $lang => $url);
                                    }
                                }
                                if ($attribute['is_global'] != 1) {
                                    if($optionSelect){
                                        $values = explode(',',$row['value']);
                                        foreach($values as $v){
                                            $data[] = array('entity_id' => $row['entity_id'],
                                                $attribute['attribute_code'] . '_id' => $v);
                                        }
                                    }else{
                                        if(!isset($data[$row['entity_id']]) || $data[$row['entity_id']]['store_id'] < $row['store_id']) {
                                            $data[$row['entity_id']] = array('entity_id' => $row['entity_id'],
                                                'store_id' => $row['store_id'],'value_' . $lang => $row['value']);
                                        }
                                    }
                                    continue;
                                }else{
                                    if($optionSelect){
                                        $values = explode(',',$row['value']);
                                        foreach($values as $v){
                                            $data[] = array('entity_id' => $row['entity_id'],
                                                $attribute['attribute_code'] . '_id' => $v);
                                        }
                                    }else{
                                        $data[$row['entity_id']] = array('entity_id' => $row['entity_id'],
                                            'store_id' => $row['store_id'],
                                            'value' => $row['value']);
                                    }
                                }
                            }
                        }
                        if($attribute['is_global'] == 1){
                            $global = true;
                            break;
                        }
                    }
                }

                if (sizeof($data)) {
                    if($optionSelect){
                        $optionHeader = array_merge(array($attribute['attribute_code'] . '_id'),$labelColumns);
                        $a = array_merge(array($optionHeader), $optionValues);
                        $files->savepartToCsv( $attribute['attribute_code'].'.csv', $a);
                        $optionValues = null;
                        $a = null;
                    }

                    if(!$global){
                        if(!$optionSelect){
                            $headerLangRow = array_merge(array('entity_id','store_id'), $labelColumns);
                            if(sizeof($additionalData)){
//                                if($attribute['attribute_code'] == 'url_key'){
//
//                                    print_r($attributeID);print_r($attribute);
//                                    print_r($additionalData);exit;
//                                }
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
                        $d = array_merge(array(array_keys(end($data))), $data);
                    }


                    $files->savepartToCsv('product_' . $attribute['attribute_code'] . '.csv', $d);
                    $fieldId = $this->_helperExporter->sanitizeFieldName($attribute['attribute_code']);
                    $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_' . $attribute['attribute_code'] . '.csv'), 'entity_id');

                    switch($attribute['attribute_code']){
                        case $optionSelect == true:
                            $optionSourceKey = $this->bxData->addResourceFile(
                                $files->getPath($attribute['attribute_code'] . '.csv'), $attribute['attribute_code'] . '_id',
                                $labelColumns);
                            $this->bxData->addSourceLocalizedTextField($attributeSourceKey,$attribute['attribute_code'],
                                $attribute['attribute_code'] . '_id', $optionSourceKey);
                            break;
                        case 'name':
                            $this->bxData->addSourceTitleField($attributeSourceKey, $labelColumns);
                            break;
                        case 'description':
                            $this->bxData->addSourceDescriptionField($attributeSourceKey, $labelColumns);
                            break;
                        case 'price':
                            if(!$global){
                                $col = null;
                                foreach($labelColumns as $k => $v) {
                                    $col = $v;
                                    break;
                                }
                                $this->bxData->addSourceListPriceField($attributeSourceKey, $col);
                            }else {
                                $this->bxData->addSourceListPriceField($attributeSourceKey, 'value');
                            }

                            if(!$global){
                                $this->bxData->addSourceLocalizedTextField($attributeSourceKey, "price_localized", $labelColumns);
                            } else {
                                $this->bxData->addSourceStringField($attributeSourceKey, "price_localized", 'value');
                            }
                            break;
                        case 'special_price':
                            if(!$global){
                                $col = null;
                                foreach($labelColumns as $k => $v) {
                                    $col = $v;
                                    break;
                                }
                                $this->bxData->addSourceDiscountedPriceField($attributeSourceKey, $col);
                            }else {
                                $this->bxData->addSourceDiscountedPriceField($attributeSourceKey, 'value');
                            }
                            if(!$global){
                                $this->bxData->addSourceLocalizedTextField($attributeSourceKey, "special_price_localized", $labelColumns);
                            } else {
                                $this->bxData->addSourceStringField($attributeSourceKey, "special_price_localized", 'value');
                            }
                            break;
                        case ($attributeType == ('int' || 'decimal')) && $attribute['is_global'] == 1:
                            $this->bxData->addSourceNumberField($attributeSourceKey, $fieldId, 'value');
                            break;
                        case $attributeType == 'datetime':
                            if(!$global){
                                $this->bxData->addSourceLocalizedTextField($attributeSourceKey, $fieldId, $labelColumns);
                            }else{
                                $this->bxData->addSourceStringField($attributeSourceKey, $fieldId, 'value');
                            }
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
    }

    protected function exportProductInformation($files){

        $fetchedResult = array();
        $db = $this->_getReadAdapter();
        //product stock
        $select = $db->select()
            ->from(
                $db->getTableName($this->_prefix . 'cataloginventory_stock_status'),
                array(
                    'entity_id' => 'product_id',
                    'stock_status',
                    'qty'
                )
            )
            ->where('stock_id = ?', 1);
        $this->indexType == 'delta' ? $select->where('created_at >= ? OR updated_at >= ?', $this->_getLastIndex()) : '';

        $result = $db->query($select);

        if($result->rowCount()){
            while($row = $result->fetch()){
                $data[] = array('entity_id'=>$row['entity_id'], 'qty'=>$row['qty']);
            }

            $d = array_merge(array(array_keys(end($data))), $data);
            $files->savePartToCsv('product_stock.csv', $d);
            $data = null;
            $d = null;
            $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_stock.csv'), 'entity_id');
            $this->bxData->addSourceNumberField($attributeSourceKey, 'qty', 'qty');
        }

        //product website
//        $select = $db->select()
//            ->from(
//                array('c_p_w' => $db->getTableName($this->_prefix . 'catalog_product_website')),
//                array(
//                    'entity_id' => 'product_id',
//                    'website_id',
//                )
//            )->joinLeft(array('s_w' => $db->getTableName($this->_prefix . 'store_website')),
//                's_w.website_id = c_p_w.website_id',
//                array('s_w.name')
//            );
//        $this->indexType == 'delta' ? $select->where('created_at >= ? OR updated_at >= ?', $this->_getLastIndex()) : '';
//
//        $result = $db->query($select);
//
//        if($result->rowCount()){
//            while($row = $result->fetch()){
//                $data[] = $row;
//            }
//            $d = array_merge(array(array_keys(end($data))), $data);
//            $files->savePartToCsv('product_website.csv', $d);
//            $data = null;
//            $d = null;
//            $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_website.csv'), 'entity_id');
//            $this->bxData->addSourceStringField($attributeSourceKey, 'website_name', 'name');
//            $this->bxData->addSourceStringField($attributeSourceKey, 'website_id', 'website_id');
//        }

        //product categories
        $select = $db->select()
            ->from(
                $db->getTableName($this->_prefix . 'catalog_category_product'),
                array(
                    'entity_id' => 'product_id',
                    'category_id',
                    'position'
                )
            );
        $this->indexType == 'delta' ? $select->where('created_at >= ? OR updated_at >= ?', $this->_getLastIndex()) : '';

        $result = $db->query($select);

        if($result->rowCount()){
            while($row = $result->fetch()){
                $data[] = $row;
            }
            $d = array_merge(array(array_keys(end($data))), $data);
            $files->savePartToCsv('product_categories.csv', $d);
            $data = null;
            $d = null;
        }

        //product super link
        $select = $db->select()
            ->from(
                $db->getTableName($this->_prefix . 'catalog_product_super_link'),
                array(
                    'entity_id' => 'product_id',
                    'parent_id',
                    'link_id'
                )
            );
        $this->indexType == 'delta' ? $select->where('created_at >= ? OR updated_at >= ?', $this->_getLastIndex()) : '';


        $result = $db->query($select);

        if($result->rowCount()){
            while($row = $result->fetch()){
                $data[] = $row;
            }

            $d = array_merge(array(array_keys(end($data))), $data);
            $files->savePartToCsv('product_parent.csv', $d);
            $data = null;
            $d = null;
            $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_parent.csv'), 'entity_id');
            $this->bxData->addSourceStringField($attributeSourceKey, 'parent_id', 'parent_id');
            $this->bxData->addSourceStringField($attributeSourceKey, 'link_id', 'link_id');
        }

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
        $this->indexType == 'delta' ? $select->where('created_at >= ? OR updated_at >= ?', $this->_getLastIndex()) : '';


        $result = $db->query($select);

        if($result->rowCount()){
            while($row = $result->fetch()){
                $data[] = $row;
            }
            $d = array_merge(array(array_keys(end($data))), $data);
            $files->savePartToCsv('product_links.csv', $d);
            $data = null;
            $d = null;
            $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_links.csv'), 'entity_id');
            $this->bxData->addSourceStringField($attributeSourceKey, 'code', 'code');
            $this->bxData->addSourceStringField($attributeSourceKey, 'linked_product_id', 'linked_product_id');
        }
    }

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
                $attrsFromDb[$row['backend_type']][] = $row['aid'];
            }
        }

        do{
            $customers_to_save = array();
            $customers = array();

            $select = $db->select()
                ->from(
                    $db->getTableName($this->_prefix . 'customer_entity'),
                    array('entity_id', 'created_at', 'updated_at')
                )
                ->limit($limit, ($page - 1) * $limit);

            $result = $db->query($select);
            if($result->rowCount()){
                while($row = $result->fetch()){
                    $customers[$row['entity_id']] = array('id' => $row['entity_id']);
                }
            }

            $ids = array_keys($customers);

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
                    ->joinLeft(array('ea' => $db->getTableName('eav_attribute')), 'ce.attribute_id = ea.attribute_id', 'ea.attribute_code')
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

            // only supports email
            if (count($attrsFromDb['static']) > 0) {
                $attributeId = current($attrsFromDb['static']);
                $select4 = $db->select()
                    ->from(array('ce' => $db->getTableName($this->_prefix . 'customer_entity')), array(
                        'entity_id' => 'entity_id',
                        'attribute_id' =>  new \Zend_Db_Expr($attributeId),
                        'value' => 'email',
                    ))
                    ->joinLeft(array('ea' => $db->getTableName($this->_prefix . 'eav_attribute')), 'ea.attribute_id = ' . $attributeId, 'ea.attribute_code')
                    ->where('ce.entity_id IN (?)', $ids);
                $selects[] = $select4;
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
                ->where('entity_type_id = ?', $this->getEntityTypeId('customer_address'))
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
                $id = $customer['id'];

                $select = $db->select()
                    ->from($db->getTableName($this->_prefix . 'customer_address_entity'),
                        array('entity_id')
                    )
                    ->where('entity_type_id = ?', $this->getEntityTypeId('customer_address'))
                    ->where('parent_id = ?', $id)
                    ->order('entity_id DESC')
                    ->limit(1);

                $select = $db->select()
                    ->from($db->getTableName($this->_prefix . 'customer_address_entity_varchar'),
                        array('attribute_id', 'value')
                    )
                    ->where('entity_type_id = ?', $this->getEntityTypeId('customer_address'))
                    ->where('entity_id = ?', $select)
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
        }
    }



    protected function getCustomerAttributes($account)
    {
        $attributes = array();
        Mage::log('bxLog: get all customer attributes for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);

        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('main_table' => $db->getTableName('eav_attribute')),
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

    protected function exportTransactions($account, $files){
        if(!$this->config->isTransactionsExportEnabled($account)){
            return;
        }

        $db = $this->_getReadAdapter();
        $limit = 1000;
        $count = $limit;
        $page = 1;
        $header = true;
        $transactions_to_save = array();

        $salt = $db->quote(
            ((string) Mage::getConfig()->getNode('global/crypt/key')) .
            $account
        );

        while($count >= $limit){

            $configurable = array();
            $select = $db
                ->select()
                ->from(
                    array('order' => $db->getTableName($this->_prefix . 'sales_flat_order')),
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
                    array('item' => $db->getTableName($this->_prefix . 'sales_flat_order_item')),
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
                    array('guest' => $db->getTableName($this->_prefix . 'sales_flat_order_address')),
                    'order.billing_address_id = guest.entity_id',
                    array(
                        'guest_id' => 'IF(guest.email IS NOT NULL, SHA1(CONCAT(guest.email, ' . $salt . ')), NULL)'
                    )
                )
                ->where('order.status <> ?', 'canceled')
                ->order(array('order.entity_id', 'item.product_type'))
                ->limit($limit, ($page - 1) * $limit);

            $transaction_attributes = $this->getTransactionAttributes($account);
            
            if (count($transaction_attributes)) {
                $billing_columns = $shipping_columns = array();
                foreach ($transaction_attributes as $attribute) {
                    $billing_columns['billing_' . $attribute] = $attribute;
                    $shipping_columns['shipping_' . $attribute] = $attribute;
                }
                $select->joinLeft(
                    array('billing_address' => $db->getTableName($this->_prefix . 'sales_flat_order_address')),
                    'order.billing_address_id = billing_address.entity_id',
                    $billing_columns
                )
                    ->joinLeft(
                        array('shipping_address' => $db->getTableName($this->_prefix . 'sales_flat_order_address')),
                        'order.shipping_address_id = shipping_address.entity_id',
                        $shipping_columns
                    );
            }

            $transactions = array();
            $result = $db->query($select);
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
                            $product = $this->productFactory->create();
                            try {
                                $pid = Mage::getModel('catalog/product')->load($productOptions['info_buyRequest']['product']);

                                $transaction['original_price'] = ($pid->getPrice());
                                $transaction['price'] = ($pid->getPrice());

                                $tmp = array();
                                $tmp['original_price'] = $transaction['original_price'];
                                $tmp['price'] = $transaction['price'];

                                $configurable[$productOptions['info_buyRequest']['product']] = $tmp;

                                $pid = null;
                                $tmp = null;
                            } catch (\Exception $e) {
                                Mage::log($e, Zend_Log::CRIT, self::BOXALINO_LOG_FILE);
                            }
                        }
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

                    if (count($transaction_attributes)) {
                        foreach ($transaction_attributes as $attribute) {
                            $final_transaction['billing_' . $attribute] = $transaction['billing_' . $attribute];
                            $final_transaction['shipping_' . $attribute] = $transaction['shipping_' . $attribute];
                        }
                    }

                    $transactions_to_save[] = $final_transaction;
                    $guest_id_transaction = null;
                    $final_transaction = null;
                }
            }else{
                return ;
            }

            $data[] = $transactions_to_save;
            $count = count($transactions);
            $configurable = null;
            $transactions = null;

            if ($header) {
                $data = array_merge(array(array_keys(end($transactions_to_save))), $transactions_to_save);
                $header = false;
                $transactions_to_save = null;
            }
            Mage::log('bxLog: Transactions - save to file for account ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            $files->savePartToCsv('transactions.csv', $data);
            $data = null;
            $page++;
        }
        $sourceKey = $this->bxData->setCSVTransactionFile($files->getPath('transactions.csv'), 'order_id', 'entity_id', 'customer_id', 'order_date', 'total_order_value', 'price', 'discounted_price');
        $this->bxData->addSourceCustomerGuestProperty($sourceKey,'guest_id');
        Mage::log('bxLog: Transactions - end of export for account ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
    }


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

    protected function exportCategories($store, $language, $transformedCategories)
    {
        $categoryTypeId = $this->getEntityTypeId('catalog_category');
        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('c_t' => $db->getTableName('catalog_category_entity')),
                array('entity_id', 'parent_id')
            )
            ->joinInner(
                array('c_v' => $db->getTableName('catalog_category_entity_varchar')),
                'c_v.entity_id = c_t.entity_id',
                array('c_v.value', 'c_v.store_id')
            )
            ->where('c_v.attribute_id = ?', $this->getAttributeId('name',$categoryTypeId))
            ->where('c_v.store_id = ? OR c_v.store_id = 0', $store->getId());


        $result = $db->query($select);
        if($result->rowCount()){
            while($row = $result->fetch()){
                if (!$row['parent_id'])  {
                    continue;
                }
                if(isset($transformedCategories[$row['entity_id']])) {
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


    protected function getEntityTypeId($entityType)
    {
        if ($this->_entityTypeIds == null) {
            $db = $this->_getReadAdapter();
            $select = $db->select()
                ->from(
                    $db->getTableName('eav_entity_type'),
                    array('entity_type_id', 'entity_type_code')
                );

            $result = $db->query($select);
            if($result->rowCount()){
                while ($row = $result->fetch()) {
                    $this->_entityTypeIds[$row['entity_type_code']] = $row['entity_type_id'];
                }
            }
        }
        return array_key_exists($entityType, $this->_entityTypeIds) ? $this->_entityTypeIds[$entityType] : null;
    }


    protected function getAttributeId($attr_code){
        $db = $this->_getReadAdapter();
        $select = $db->select()
            ->from(
                array('a_t' => $db->getTableName('eav_attribute')),
                array('attribute_id')
            )->where('a_t.attribute_code = ?', $attr_code);

        $result = $db->query($select);
        if($result->rowCount()){
            while ($row = $result->fetch()) {
                return $row['attribute_id'];
            }

        }
        return null;
    }

    protected function _getLastIndex()
    {
        if ($this->_lastIndex == 0) {
            $this->_setLastIndex();
        }
        return $this->_lastIndex;
    }

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


    protected function loadBxLibrary(){
        $libPath = __DIR__ . '/../../Lib';
        require_once($libPath . '/BxClient.php');
        \com\boxalino\bxclient\v1\BxClient::LOAD_CLASSES($libPath);
    }
}