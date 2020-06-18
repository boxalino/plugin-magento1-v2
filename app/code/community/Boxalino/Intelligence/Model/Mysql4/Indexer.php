<?php

/**
 * Class Boxalino_Intelligence_Model_Mysql4_Indexer
 */
abstract class Boxalino_Intelligence_Model_Mysql4_Indexer extends Mage_Core_Model_Mysql4_Abstract
{
    const BOXALINO_LOG_FILE = 'boxalino_exporter.log';

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
     * @var array
     */
    protected $deltaIds = [];

    /**
     * @var Boxalino_Intelligence_Model_Resource_Exporter
     */
    protected $exporterResource;


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
        if(!Mage::helper('core')->isModuleOutputEnabled('Boxalino_Intelligence'))
        {
            Mage::log("bxLog: the Boxalino module output is disabled. Exporter cancelled.", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            return;
        }

        $this->exporterResource = Mage::getResourceModel("boxalino_intelligence/exporter");
        $this->_helperExporter = Mage::helper('boxalino_intelligence');
        $this->config = Mage::helper('boxalino_intelligence/bxIndexConfig');
        $configurations = $this->config->toString();
        if(empty($configurations))
        {
            Mage::log("bxLog: no active configurations found on either of the stores. Process cancelled.", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            return;
        }

        Mage::log("bxLog: starting {$this->indexType} Boxalino export", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        Mage::log("bxLog: retrieved index config: " . $configurations, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        try {
            foreach ($this->config->getAccounts() as $account) {
                Mage::log("bxLog: initialize files on account: " . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                $files = Mage::helper('boxalino_intelligence/bxFiles')->init($account, $this->indexType);

                $bxClient = new \com\boxalino\bxclient\v1\BxClient($account, $this->config->getAccountPassword($account), "");

                // Indicate index type as boolean variable.
                $isDelta = ($this->indexType == 'delta');
                $this->exporterResource->isDelta($isDelta);
                $this->bxData = new \com\boxalino\bxclient\v1\BxData($bxClient, $this->config->getAccountLanguages($account), $this->config->isAccountDev($account), $isDelta);

                Mage::log("bxLog: verify credentials for account: " . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                $this->bxData->verifyCredentials();

                Mage::log('bxLog: Export the product files for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                $exportProducts = $this->exportProducts($account, $files);

                Mage::log('bxLog: Start exportCategories', Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                $categories = $this->exportCategories($account);
                $this->addCategoriesData($account, $files, $categories);

                Mage::log('bxLog: Export the customers and transactions for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                if($this->indexType == 'full'){
                    $this->exportCustomers($account, $files);
                    $this->exportTransactions($account, $files);
                }

                if(!$exportProducts){
                    Mage::log('bxLog: No Products found for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                    Mage::log('bxLog: Finished account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                } else {
                    if($this->indexType == 'full'){
                        Mage::log('bxLog: Prepare the final files: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                        Mage::log('bxLog: Prepare XML configuration file: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                        try {
                            Mage::log('bxLog: Push the XML configuration file to the Data Indexing server for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
                            $this->bxData->pushDataSpecifications();
                        } catch(\LogicException $e){
                            Mage::log('bxLog: publishing XML configurations returned a timeout: ' . $e->getMessage(), Zend_Log::WARN, self::BOXALINO_LOG_FILE);
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
                        $tmpPath = $this->config->getExporterTemporaryArchivePath($account);
                        $this->bxData->pushData($tmpPath, $timeout);
                    } catch(\LogicException $e){
                        Mage::log('bxLog: pushing data stopped due to the configured timeout: ' . $e->getMessage(), Zend_Log::WARN, self::BOXALINO_LOG_FILE);
                    } catch(\Exception $e){
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
     * @param $account
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
     * @return bool
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Exception
     */
    protected function exportProducts($account, $files)
    {
        $languages = $this->config->getAccountLanguages($account);

        Mage::log('bxLog: Products - start of export for account ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $attrs = $this->getStoreProductAttributes($account);

        Mage::log('bxLog: Products - get info about attributes - before for account ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
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

            $data=[];
            $fetchedResult = $this->exporterResource->getProductEntityByLimitPage($limit, $page, $website_id);
            if(sizeof($fetchedResult)){
                foreach ($fetchedResult as $r) {
                    if($this->indexType == 'delta') $this->deltaIds[] = $r['entity_id'];
                    if($r['group_id'] == null) $r['group_id'] = $r['entity_id'];
                    $data[] = $r;
                    $totalCount++;
                    if(isset($duplicateIds[$r['entity_id']])){
                        $r['group_id'] = $r['entity_id'];
                        $r['entity_id'] = 'duplicate' . $r['entity_id'];
                        $data[] = $r;
                    }
                }
            } else {
                if($totalCount == 0){
                    return false;
                }
                break;
            }

            if($header && count($data) > 0) {
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

        $productAttributes = $this->exporterResource->getProductAttributesByCodes($attrs);
        $attrsFromDb = ['int'=>[], 'varchar'=>[], 'text'=>[], 'decimal'=>[], 'datetime'=>[]];
        foreach ($productAttributes as $r) {
            $type = $r['backend_type'];
            if (isset($attrsFromDb[$type])) {
                $attrsFromDb[$type][$r['attribute_id']] =[
                    'attribute_code' => $r['attribute_code'],
                    'is_global' => $r['is_global'],
                    'frontend_input' => $r['frontend_input']
                ];
            }
        }

        $this->exporterResource->setExportIds($this->deltaIds);

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
     * @param $mainSourceKey
     * @param $duplicateIds
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Db_Statement_Exception
     */
    protected function exportProductAttributes($attrs, $languages, $account, $files, $mainSourceKey, $duplicateIds)
    {
        $paramPriceLabel = '';
        $paramSpecialPriceLabel = '';

        $db = Mage::getModel('core/resource')->getConnection('core_read');
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
                $optionSelect = in_array($attribute['frontend_input'],['multiselect','select']);
                $data = [];
                $additionalData = [];
                $global = false;
                $getValueForDuplicate = false;
                $d = [];
                $headerLangRow = [];
                $optionValues = [];
                $labelColumns = [];

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
                    $storeObject=null;

                    if ($attribute['attribute_code'] == 'price' || $attribute['attribute_code'] == 'special_price') {
                        if($langIndex == 0) {
                            $priceData = $this->exporterResource->getPriceByType($attributeType, $attributeID);
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

                    $languagesForLabels = [];
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

                        if(!$optionSelect)
                        {
                            unset($languagesForLabels[Mage_Core_Model_Store::ADMIN_CODE]);
                            unset($labelColumns[Mage_Core_Model_Store::ADMIN_CODE]);
                        }
                    }

                    $select->where('t_d.attribute_id = ?', $attributeID)
                        ->where('t_d.store_id = 0 OR t_d.store_id = ?',$storeId);

                    if ($attribute['attribute_code'] == 'visibility') {
                        $getValueForDuplicate = true;
                        $select = $this->exporterResource->getProductAttributeValueSqlByCodeTypeStore($attribute['attribute_code'], $attributeType, $storeId);
                    }

                    if ($attribute['attribute_code'] == 'status') {
                        $getValueForDuplicate = true;
                        $select = $this->exporterResource->getProductStatusParentDependabilityByStore($storeId);
                    }

                    $fetchedResult = $db->fetchAll($select);

                    if (sizeof($fetchedResult))
                    {
                        foreach ($fetchedResult as $i => $row)
                        {
                            if (isset($data[$row['entity_id']]) && !$optionSelect)
                            {
                                if(isset($data[$row['entity_id']]['value_' . $lang]))
                                {
                                    if($row['store_id'] > 0){
                                        $data[$row['entity_id']]['value_' . $lang] = $row['value'];
                                        if(isset($duplicateIds[$row['entity_id']])){
                                            $data['duplicate'.$row['entity_id']]['value_' . $lang] = $getValueForDuplicate ?
                                                $this->exporterResource->getProductAttributeValue($row['entity_id'], $attributeID, $storeId) :
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
                                } else {
                                    $data[$row['entity_id']]['value_' . $lang] = $row['value'];
                                    if(isset($duplicateIds[$row['entity_id']])){
                                        $data['duplicate'.$row['entity_id']]['value_' . $lang] = $getValueForDuplicate ?
                                            $this->exporterResource->getProductAttributeValue($row['entity_id'], $attributeID, $storeId) :
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
                                if ($attribute['is_global'] != 1 || ($attribute['is_global']==1 && in_array($attribute['attribute_code'], $this->getRequiredLocalizedProperties()))) {
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
                                                    $this->exporterResource->getProductAttributeValue($row['entity_id'], $attributeID, $storeId)
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
                                                    $this->exporterResource->getProductAttributeValue($row['entity_id'], $attributeID, $storeId)
                                                    : $row['value']
                                            );
                                        }
                                    }
                                }
                            }
                        }
                        if($attribute['is_global'] == 1 && !$optionSelect && !in_array($attribute['attribute_code'], $this->getRequiredLocalizedProperties())){
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

                if (sizeof($data) || in_array($attribute['attribute_code'], $this->getRequiredProductAttributes($account))) {
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
                        } else {
                            $d = array_merge(array(array('entity_id',$attribute['attribute_code'] . '_id')), $data);
                        }
                    } else {
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
                            $lc = [];
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

        $files->clearEmptyFiles("product_");
    }

    /**
     * Required localized properties
     *
     * @return array
     */
    public function getRequiredLocalizedProperties()
    {
        return array('name', 'description');
    }

    /**
     * @param $files
     * @param $duplicateIds
     * @param $account
     * @param $languages
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Db_Statement_Exception
     */
    protected function exportProductInformation($files, $duplicateIds, $account, $languages)
    {
        //product stock
        $productStockData = $this->exporterResource->getProductStockInformation();
        $duplicateProductStockData = $this->exporterResource->getDuplicateProductStockByIds($duplicateIds);
        if(sizeof($productStockData)){
            foreach ($productStockData as $r) {
                $data[] = array('entity_id'=>$r['entity_id'], 'qty'=>$r['qty'], 'is_in_stock'=>$r['is_in_stock']);
            }

            foreach($duplicateProductStockData as $r) {
                $data[] = array('entity_id'=>$r['entity_id'], 'qty'=>$r['qty'], 'is_in_stock'=>$r['is_in_stock']);
            }
            $d = array_merge(array(array_keys(end($data))), $data);
            $files->savePartToCsv('product_stock.csv', $d);
            $data = null; $d = null;$productStockData = null;
            $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_stock.csv'), 'entity_id');
            $this->bxData->addSourceNumberField($attributeSourceKey, 'qty', 'qty');
            $this->bxData->addSourceStringField($attributeSourceKey, 'is_in_stock', 'is_in_stock');
        }

        //product parent categories -- !always added!
        $productParentCategory = $this->exporterResource->getProductParentCategoriesInformation();
        $duplicateResult = $this->exporterResource->getProductParentCategoriesInformationByDuplicateIds($duplicateIds);
        foreach ($duplicateResult as $r){
            $r['entity_id'] = 'duplicate'.$r['entity_id'];
            $productParentCategory[] = $r;
        }
        $duplicateResult = null;
        if (empty($productParentCategory))
        {
            $d = [['entity_id', 'category_id']];
        } else {
            $d = array_merge(array(array_keys(end($productParentCategory))), $productParentCategory);
        }
        $files->savePartToCsv('product_categories.csv', $d);
        $d = null;$productParentCategory = null;

        //product super link
        $superLink = $this->exporterResource->getProductSuperLinkInformation();
        if(sizeof($superLink)) {
            foreach ($superLink as $r) {
                $data[] = $r;
                if(isset($duplicateIds[$r['entity_id']])){
                    $r['entity_id'] = 'duplicate'.$r['entity_id'];
                    $data[] = $r;
                }
            }

            $d = array_merge(array(array_keys(end($data))), $data);
            $files->savePartToCsv('product_parent.csv', $d);
            $data = null;$d = null;$superLink=null;
            $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_parent.csv'), 'entity_id');
            $this->bxData->addSourceStringField($attributeSourceKey, 'parent_id', 'parent_id');
            $this->bxData->addSourceStringField($attributeSourceKey, 'link_id', 'link_id');
        }

        //product link
        $linkData = $this->exporterResource->getProductLinksInformation();
        if(sizeof($linkData)) {
            foreach ($linkData as $r) {
                $data[] = $r;
                if(isset($duplicateIds[$r['entity_id']])){
                    $r['entity_id'] = 'duplicate'.$r['entity_id'];
                    $data[] = $r;
                }
            }
            $d = array_merge(array(array_keys(end($data))), $data);
            $files->savePartToCsv('product_links.csv', $d);
            $data = null;$linkData=null;$d = null;
            $attributeSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_links.csv'), 'entity_id');
            $this->bxData->addSourceStringField($attributeSourceKey, 'code', 'code');
            $this->bxData->addSourceStringField($attributeSourceKey, 'linked_product_id', 'linked_product_id');
        }

        //product parent title
        $lvh = [];
        $data=[];
        foreach ($languages as $language) {
            $lvh[$language] = 'value_'.$language;
            $store = $this->config->getStore($account, $language);
            $storeId = $store->getId();
            $store = null;

            $productTitleData = $this->exporterResource->getProductParentTitleInformationByStore($storeId);
            if(sizeof($productTitleData))
            {
                foreach($productTitleData as $row)
                {
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
                $productTitleData = null;

                $duplicateResult = $this->exporterResource->getProductParentTitleInformationByStoreAttrDuplicateIds($storeId, $duplicateIds);
                foreach ($duplicateResult as $row)
                {
                    $row['entity_id'] = 'duplicate'.$row['entity_id'];
                    if (isset($data[$row['entity_id']])) {
                        $data[$row['entity_id']]['value_' . $language] = $row['value'];
                        continue;
                    }
                    $data[$row['entity_id']] = array('entity_id' => $row['entity_id'], 'value_' . $language => $row['value']);
                }
                $duplicateResult = null;
            }
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
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    protected function getStoreProductAttributes($account)
    {
        $attributes = $this->exporterResource->getProductAttributes();

        Mage::log('bxLog: get configured product attributes.', Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $attributes = $this->config->getAccountProductsProperties($account, $attributes, $this->getRequiredProductAttributes($account));

        Mage::log('bxLog: returning configured product attributes: ' . implode(',', array_values($attributes)), Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        return $attributes;
    }

    /**
     * @param $account
     * @return array
     */
    public function getRequiredProductAttributes($account)
    {
        Mage::log('bxLog: get all product attributes.', Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $properties = [
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
        ];

        if($this->config->exportProductImages($account)) {
            $properties[] = 'image';
        }
        if($this->config->exportProductUrl($account)) {
            $properties[] = 'url_key';
        }

        return $properties;
    }


    /**
     * @param $account
     * @param $files
     * @return void|null
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Db_Statement_Exception
     */
    protected function exportCustomers($account, $files)
    {
        if(!$this->config->isCustomersExportEnabled($account))
        {
            return;
        }

        $limit = 1000;
        $count = $limit;
        $page = 1;
        $header = true;

        $attrsFromDb = ['int'=>[], 'static'=>[], 'varchar'=>[], 'datetime'=>[]];
        $customer_attributes = $this->getCustomerAttributes($account);

        $result = $this->exporterResource->getCustomerAttributesByCodes($customer_attributes);
        foreach ($result as $attr) {
            if (isset($attrsFromDb[$attr['backend_type']])) {
                $attrsFromDb[$attr['backend_type']][] = $attr['backend_type'] == 'static' ? $attr['attribute_code'] : $attr['aid'];
            }
        }

        $fieldsForCustomerSelect =  array_merge(['entity_id'], $attrsFromDb['static']);
        do{
            $customers_to_save = [];
            $customers = $this->exporterResource->getCustomerAddressByFieldsAndLimit($limit, $page, $fieldsForCustomerSelect);
            $ids = array_keys($customers);
            $customerAttributesValues = $this->exporterResource->getUnionCustomerAttributesByAttributesAndIds($attrsFromDb, $ids);
            if(!empty($customerAttributesValues))
            {
                foreach ($customerAttributesValues as $r) {
                    $customers[$r['entity_id']][$r['attribute_code']] = $r['value'];
                }
            }

            foreach ($customers as $customer) {
                $id = $customer['entity_id'];
                $countryCode = $customer['country_id'];
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
                    'zip' => array_key_exists('postcode', $customer) ? $customer['postcode'] : '',
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

        } while($count >= $limit);

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
        Mage::log("bxLog: Customers - end of exporting for account: {$account}", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
    }

    /**
     * @param $account
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    protected function getCustomerAttributes($account)
    {
        Mage::log('bxLog: get all customer attributes for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $attributes = $this->exporterResource->getCustomerAttributes();

        Mage::log('bxLog: get configured customer attributes for account: ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $filteredAttributes = $this->config->getAccountCustomersProperties($account, $attributes, array('dob', 'gender'));

        $attributes = array_intersect($attributes, $filteredAttributes);
        Mage::log('bxLog: returning configured customer attributes for account ' . $account . ': ' . implode(',', array_values($attributes)), Zend_Log::INFO, self::BOXALINO_LOG_FILE);

        return $attributes;
    }

    /**
     * @param $account
     * @param $files
     * @throws Zend_Db_Statement_Exception
     */
    protected function exportTransactions($account, $files)
    {
        if(!$this->config->isTransactionsExportEnabled($account)){
            return;
        }

        $limit = 5000;
        $page = 1;
        $header = true;
        $transactions_to_save = [];

        $transaction_attributes = $this->getTransactionAttributes($account);
        if (count($transaction_attributes)) {
            $billing_columns = $shipping_columns = [];
            foreach ($transaction_attributes as $attribute) {
                $billing_columns['billing_' . $attribute] = $attribute;
                $shipping_columns['shipping_' . $attribute] = $attribute;
            }
        }

        $tempSelect = $this->exporterResource->prepareTransactionsSelectByShippingBillingModeSql($account, $billing_columns, $shipping_columns, $this->config->getTransactionMode($account));
        while(true){
            $configurable = [];
            $transactions = $this->exporterResource->getTransactionsByLimitPage($limit, $page, $tempSelect);
            if(sizeof($transactions) < 1 && $page == 1){
                return;
            } elseif (sizeof($transactions) < 1 && $page > 1) {
                break;
            }

            foreach ($transactions as $transaction) {
                if ($transaction['product_type'] == 'configurable') {
                    $configurable[$transaction['product_id']] = $transaction;
                    continue;
                }

                $productOptions = unserialize($transaction['product_options']);
                if($productOptions === FALSE) {
                    $productOptions = @json_decode($transaction['product_options'], true);
                    if(is_null($productOptions)) {
                        Mage::log("bxLog: failed to unserialize and json decode product_options for order with entity_id: " . $transaction['entity_id'], Zend_Log::INFO, self::BOXALINO_LOG_FILE );
                        continue;
                    }
                }

                if (intval($transaction['price']) == 0 && $transaction['product_type'] == 'simple' && isset($productOptions['info_buyRequest']['product']))
                {
                    if (isset($configurable[$productOptions['info_buyRequest']['product']])) {
                        $pid = $configurable[$productOptions['info_buyRequest']['product']];

                        $transaction['original_price'] = $pid['original_price'];
                        $transaction['price'] = $pid['price'];
                    } else {
                        try {
                            $pid = Mage::getModel('catalog/product')->load($productOptions['info_buyRequest']['product']);

                            $transaction['original_price'] = ($pid->getPrice());
                            $transaction['price'] = ($pid->getPrice());

                            $tmp = [];
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

                $status = 0; // 0 - pending, 1 - confirmed, 2 - shipping
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
                    'increment_id' => $transaction['increment_id'],
                    'entity_id' => $transaction['product_id'],
                    'customer_id' => $transaction['customer_id'],
                    'email' => $transaction['customer_email'],
                    'guest_id' => $transaction['guest_id'],
                    'price' => $transaction['original_price'],
                    'discounted_price' => $transaction['price'],
                    'tax_amount'=> $transaction['tax_amount'],
                    'coupon_code' => $transaction['coupon_code'],
                    'currency' => $transaction['order_currency_code'],
                    'quantity' => $transaction['qty_ordered'],
                    'subtotal' => $transaction['base_subtotal'],
                    'total_order_value' => $transaction['grand_total'],
                    'discount_amount' => $transaction['discount_amount'],
                    'discount_percent' => $transaction['discount_percent'],
                    'shipping_costs' => $transaction['shipping_amount'],
                    'order_date' => $transaction['created_at'],
                    'confirmation_date' => $status == 1 ? $transaction['updated_at'] : null,
                    'shipping_date' => $status == 2 ? $transaction['updated_at'] : null,
                    'status' => $transaction['status'],
                    'state' => $transaction['state'],
                    'shipping_method' => $transaction['shipping_method'],
                    'shipping_description' => $transaction['shipping_description'],
                    'payment_method' => $transaction['payment_method'],
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
        $sourceKey = $this->bxData->setCSVTransactionFile($files->getPath('transactions.csv'), 'order_id', 'entity_id', 'customer_id', 'order_date', 'total_order_value', 'price', 'discounted_price', 'currency', 'email');
        $this->bxData->addSourceCustomerGuestProperty($sourceKey,'guest_id');

        Mage::log("bxLog: Transactions - exporting additional tables for account: {$account}", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
        $this->exportExtraTables('transactions', $files, $this->config->getAccountExtraTablesByEntityType($account,'transactions'));

        Mage::log('bxLog: Transactions - end of export for account ' . $account, Zend_Log::INFO, self::BOXALINO_LOG_FILE);
    }

    /**
     * @param $account
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    protected function getTransactionAttributes($account)
    {
        $setupConfig = Mage::getConfig()->getResourceConnectionConfig("default_setup");
        if(!isset($setupConfig->dbname)) {
            Mage::log("default_setup configuration doesn't provide a dbname in getResourceConnectionConfig", Zend_Log::WARN, self::BOXALINO_LOG_FILE);
            return [];
        }
        try {
            $attributes = $this->exporterResource->getTransactionColumnsAsAttributes();
            $requiredProperties = [];
            $filteredAttributes = $this->config->getAccountTransactionsProperties($account, $attributes, $requiredProperties);
            $attributes = array_intersect($attributes, $filteredAttributes);

            return $attributes;
        } catch (\Exception $exception) {
            Mage::log("bxLog: transactions table error: ". $exception->getMessage(), Zend_Log::ERR, self::BOXALINO_LOG_FILE);
            return [];
        }
    }

    /**
     * @param $account
     * @return []
     * @throws Zend_Db_Statement_Exception
     * @throws Exception
     */
    protected function exportCategories($account)
    {
        $categories = [];
        foreach ($this->config->getAccountLanguages($account) as $language)
        {
            $store = $this->config->getStore($account, $language);
            Mage::log("bxLog: Start exportCategories for language .  $language on store:" . $store->getId(), Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            $categories = $this->exportCategoriesByStoreLanguage($store, $language, $categories);
        }

        return $categories;
    }

    /**
     * @param $account
     * @param $files
     * @param $categories
     */
    protected function addCategoriesData($account, $files, $categories)
    {
        $languages = $this->config->getAccountLanguages($account);
        $categories = array_merge(array(array_keys(end($categories))), $categories);
        $files->savePartToCsv('categories.csv', $categories);
        $labelColumns = [];
        foreach ($languages as $lang) {
            $labelColumns[$lang] = 'value_' . $lang;
        }
        $this->bxData->addCategoryFile($files->getPath('categories.csv'), 'category_id', 'parent_id', $labelColumns);
        $productToCategoriesSourceKey = $this->bxData->addCSVItemFile($files->getPath('product_categories.csv'), 'entity_id');
        $this->bxData->setCategoryField($productToCategoriesSourceKey, 'category_id');
    }

    /**
     * @param $store
     * @param $language
     * @param $transformedCategories
     * @return mixed
     * @throws \Exception
     */
    protected function exportCategoriesByStoreLanguage($store, $language, $transformedCategories)
    {
        $categories = $this->exporterResource->getCategoriesByStoreId($store->getId(), $store->getRootCategoryId());
        foreach($categories as $r){
            if (!$r['parent_id'])  {
                continue;
            }
            if(isset($transformedCategories[$r['entity_id']])) {
                $transformedCategories[$r['entity_id']]['value_' .$language] = $r['value'];
                continue;
            }
            $transformedCategories[$r['entity_id']] = ['category_id' => $r['entity_id'], 'parent_id' => $r['parent_id'], 'value_' . $language => $r['value']];
        }

        return $transformedCategories;
    }

    /**
     * @param $account
     * @param $languages
     * @return array
     * @throws \Exception
     */
    protected function getDuplicateIds($account, $languages)
    {
        $ids = [];
        $entity_type = $this->exporterResource->getEntityTypeId('catalog_product');
        $attrId = $this->exporterResource->getAttributeId('visibility', $entity_type);
        foreach ($languages as $language){
            $storeObject = $this->config->getStore($account, $language);
            $ids = $this->exporterResource->getProductDuplicateIds($storeObject->getId(), $attrId, Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
            $storeObject = null;
        }
        return $ids;
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
                $columns = $this->exporterResource->getColumnsByTableName($table);
                $tableContent = $this->exporterResource->getTableContent($table);
                if(!is_array($tableContent))
                {
                    throw new Exception("Extra table {$table} content empty.");
                }
                $dataToSave = array_merge(array(array_keys(end($tableContent))), $tableContent);

                $fileName = "extra_". $table . ".csv";
                $files->savePartToCsv($fileName, $dataToSave);

                $this->bxData->addExtraTableToEntity($files->getPath($fileName), $entity, reset($columns), $columns);
                Mage::log("bxLog: {$entity} - additional table {$table} exported.", Zend_Log::INFO, self::BOXALINO_LOG_FILE);
            } catch (\Exception $exception) {
                Mage::log("bxLog: {$entity} additional table error: ". $exception->getMessage(), Zend_Log::ERR, self::BOXALINO_LOG_FILE);
                continue;
            }
        }

        return $this;
    }


    /**
     * loading Boxalino SDK
     */
    protected function loadBxLibrary(){
        $libPath = __DIR__ . '/../../lib';
        require_once($libPath . '/BxClient.php');
        \com\boxalino\bxclient\v1\BxClient::LOAD_CLASSES($libPath);
    }

}
