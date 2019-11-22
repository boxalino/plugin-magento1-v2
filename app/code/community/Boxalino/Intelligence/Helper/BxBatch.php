<?php

/**
 * Class Boxalino_Intelligence_Helper_BxBatch
 *
 * initialize the helper with your account credentials:
 * $bxBatchHelper = Mage::helper('boxalino_intelligence/bxBatch')->setAccount($account)->setApiKey($apiKey)->setApiSecret($apiSecret)->setIsDev($isDev);
 *
 * making the request to the BxBatch helper:
 * $productRecommendations = $bxBatchHelper->getRecommendationsResponse($choiceId, $language, $customerIds, $hitCount, $returnFields, $productsGroupBy, $offset);
 *
 * viewing the data
 * @return [customer_id=> [[field1=>value, field2=>value,..], [field1=>value, field2=>value, ..],..], customer_id=>[[], [], []]]
 * $productDetails = $productRecommendations->getHitFieldValuesForProfileIds();
 *
 */
class Boxalino_Intelligence_Helper_BxBatch
{
    /**
     * @var \com\boxalino\bxclient\v1\BxBatchClient
     */
    private static $bxClient = null;

    protected $account = null;
    protected $apiKey = null;
    protected $apiSecret = null;
    protected $language = null;
    protected $isDev = null;

    public function __construct()
    {
        $libPath = Mage::getModuleDir('','Boxalino_Intelligence') . DIRECTORY_SEPARATOR . 'lib';
        require_once($libPath . DIRECTORY_SEPARATOR . 'BxBatchClient.php');
        \com\boxalino\bxclient\v1\BxBatchClient::LOAD_CLASSES($libPath);
    }

    /**
     * Initialize BxBatchClient
     */
    protected function initializeBXClient()
    {
        self::$bxClient = new \com\boxalino\bxclient\v1\BxBatchClient($this->getAccount(), $this->getApiKey(), $this->getApiSecret(), $this->getIsDev());
        foreach(Mage::app()->getRequest()->getParams() as $param=>$value)
        {
            self::$bxClient->addRequestContextParameter($param, $value);
        }
    }

    /**
     * @param string $choiceId
     * @param string $language
     * @param [] $customerIds
     * @param integer $hitCount
     * @param [] $returnFields
     * @param string $productsGroupBy
     * @param int $offset
     * @return \com\boxalino\bxclient\v1\BxBatchResponse
     * @throws Exception
     */
    public function getRecommendationsResponse($choiceId, $language, $customerIds, $hitCount, $returnFields, $productsGroupBy='products_group_id', $offset=0)
    {
        $this->initializeBXClient();

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

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
        return $this;
    }

    public function setAccount($account)
    {
        $this->account = $account;
        return $this;
    }

    public function setIsDev($isDev)
    {
        $this->isDev = $isDev;
        return $this;
    }

    public function getAccount()
    {
        if(is_null($this->account))
        {
            return Mage::getStoreConfig('bxGeneral/general/account_name');
        }

        return $this->account;
    }

    public function getApiKey()
    {
        if(is_null($this->apiKey))
        {
            $this->apiKey = Mage::getStoreConfig('bxGeneral/general/apiKey');
        }

        return $this->apiKey;
    }

    public function getApiSecret()
    {
        if(is_null($this->apiSecret))
        {
            $this->apiSecret = Mage::getStoreConfig('bxGeneral/general/apiSecret');
        }

        return $this->apiSecret;
    }

    public function getIsDev()
    {
        if(is_null($this->isDev))
        {
            $this->isDev = Mage::getStoreConfig('bxGeneral/general/dev');
        }

        return $this->isDev;
    }

}
