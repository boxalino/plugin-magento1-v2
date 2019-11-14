<?php

class Boxalino_Intelligence_BxBatch
{
    /**
     * @var \com\boxalino\bxclient\v1\BxBatchClient
     */
    private static $bxClient = null;

    public function __construct()
    {
        $libPath = Mage::getModuleDir('','Boxalino_Intelligence') . DIRECTORY_SEPARATOR . 'lib';
        require_once($libPath . DIRECTORY_SEPARATOR . 'BxBatchClient.php');
        \com\boxalino\bxclient\v1\BxBatchClient::LOAD_CLASSES($libPath);

        $this->initializeBXClient();
        if(isset($_REQUEST['dev_bx_test_mode']) && $_REQUEST['dev_bx_test_mode'] == 'true') {
            self::$bxClient->setTestMode(true);
        }
    }

    /**
     * Initialize BxBatchClient
     */
    protected function initializeBXClient()
    {
        $account = Mage::getStoreConfig('bxGeneral/general/account_name');
        $isDev = Mage::getStoreConfig('bxGeneral/general/dev');
        $apiKey = Mage::getStoreConfig('bxGeneral/general/apiKey');
        $apiSecret = Mage::getStoreConfig('bxGeneral/general/apiSecret');
        self::$bxClient = new \com\boxalino\bxclient\v1\BxClient($account, $apiKey, $apiSecret, $isDev);
        self::$bxClient->setTimeout(Mage::getStoreConfig('bxGeneral/advanced/thrift_timeout'));

        foreach(Mage::app()->getRequest()->getParams() as $param=>$value)
        {
            self::$bxClient->addRequestContextParameter($param, $value);
        }
    }

    /**
     * @param $choiceId
     * @param $language
     * @param $customerIds
     * @param $hitCount
     * @param $returnFields
     * @param $productsGroupBy
     * @param int $offset
     * @return \com\boxalino\bxclient\v1\BxBatchResponse
     * @throws Exception
     */
    public function getRecommendationsResponse($choiceId, $language, $customerIds, $hitCount, $returnFields, $productsGroupBy, $offset=0)
    {
        $bxRequest = new \com\boxalino\bxclient\v1\BxBatchRequest($language, $choiceId);
        $bxRequest->setMax($hitCount);
        $bxRequest->setGroupBy($productsGroupBy);
        $bxRequest->setReturnFields($returnFields);
        $bxRequest->setOffset($offset);
        $bxRequest->setProfileIds($customerIds);

        self::$bxClient->setRequest($bxRequest);
        return self::$bxClient->getBatchChooseResponse();
    }

    /**
     * @param $field
     * @param $value
     */
    public function addFilter($field, $value)
    {
        self::$bxClient->addRequestContextParameter($field, $value);
        return $this;
    }

}
