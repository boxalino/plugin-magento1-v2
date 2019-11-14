<?php
namespace com\boxalino\bxclient\v1;

use com\boxalino\bxclient\v1\BxBatchRequest;
use com\boxalino\p13n\api\thrift\BatchChoiceRequest;
use com\boxalino\p13n\api\thrift\UserRecord;
use \com\boxalino\bxclient\v1\BxBatchResponse;
use \com\boxalino\p13n\api\thrift\BatchChoiceResponse;

class BxBatchClient
{

    protected $isTest = null;
    protected $batchChooseResponse = null;
    /**
     * @var null | BxBatchRequest
     */
    protected $batchRequest = null;
    protected $batchChooseRequest = null;
    protected $batchChooseRequests = [];
    protected $requestContextParameters = [];

    protected $profileId = null;
    protected $isDev = false;
    protected $account = null;
    protected $apiKey = null;
    protected $apiSecret = null;
    protected $transport = null;

    protected $timeout = 2;
    protected $curl_timeout = 2000;
    protected $schema = "https";
    protected $port = 443;
    protected $batchSize = 500;
    protected $host = 'track.bx-cloud.com';
    protected $uri = '/p13n.web/p13n';

    public function __construct($account, $apiKey, $apiSecret, $isDev=false)
    {
        $this->account = $account;
        $this->isDev = $isDev;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    public static function LOAD_CLASSES($libPath)
    {
        require_once($libPath . '/Thrift/ClassLoader/ThriftClassLoader.php');
        $cl = new \Thrift\ClassLoader\ThriftClassLoader(false);
        $cl->registerNamespace('Thrift', $libPath);
        $cl->register(true);
        require_once($libPath . '/P13nService.php');
        require_once($libPath . '/Types.php');

        require_once($libPath . "/BxFacets.php");
        require_once($libPath . "/BxFilter.php");
        require_once($libPath . "/BxSortFields.php");
        require_once($libPath . "/BxRequest.php");
        require_once($libPath . "/BxBatchResponse.php");
        require_once($libPath . "/BxBatchRequest.php");
        require_once($libPath . "/BxChooseResponse.php");
    }

    public function setRequest(BxBatchRequest $request)
    {
        $request->setDefaultIndexId($this->getAccount($this->isDev));
        $request->setRequestContextParameters($this->requestContextParameters);
        $request->setIsDev($this->isDev);

        $this->batchRequest = $request;
    }

    public function getBatchChooseResponse()
    {
        if((is_null($this->batchChooseResponse) || empty($this->batchChooseResponse)))
        {
            $this->batchChooseResponse = $this->batchChoose();
        }

        $bxBatchChooseResponse = new \com\boxalino\bxclient\v1\BxBatchResponse($this->batchChooseResponse, $this->batchRequest->getProfileIds());
        return $bxBatchChooseResponse;
    }

    public function batchChoose()
    {
        $requests = $this->getThriftBatchChoiceRequest();
        if(is_array($requests))
        {
            $variants = [];
            $selectedVariants = [];
            foreach($requests as $request)
            {
                $response = $this->p13nBatch($request);
                foreach($response->variants as $variant)
                {
                    $variants[] = $variant;
                }

                foreach($response->selectedVariants as $selectedVariant)
                {
                    $selectedVariants[] = $selectedVariant;
                }
            }

            $this->batchChooseResponse = new \com\boxalino\p13n\api\thrift\BatchChoiceResponse(['variants'=> $variants, 'selectedVariants'=>$selectedVariants]);
            return $this->batchChooseResponse;
        }

        $this->batchChooseResponse = $this->p13nBatch($requests);
        return $this->batchChooseResponse;
    }

    protected function getThriftBatchChoiceRequest()
    {
        $requestProfiles = $this->batchRequest->getProfileIds();
        if(count($requestProfiles) > $this->batchSize)
        {
            $chunks = array_chunk($requestProfiles, $this->batchSize);
            foreach($chunks as $chunk)
            {
                $request = $this->getBatchChooseRequest($this->batchRequest, $chunk);
                $this->addBatchChooseRequest($request);
            }

            return $this->batchChooseRequests;
        }

        $this->batchChooseRequest = $this->getBatchChooseRequest($this->batchRequest);
        return $this->batchChooseRequest;
    }

    public function addBatchChooseRequest($request)
    {
        if(empty($this->batchChooseRequests))
        {
            $this->batchChooseRequests = [];
        }

        $this->batchChooseRequests[] = $request;
    }

    protected function p13nBatch($batchChoiceRequest)
    {
        try{
            $batchChooseResponse = $this->getP13n($this->timeout)->batchChoose($batchChoiceRequest);
            if(isset($this->requestContextParameters['dev_bx_disp']) && $this->requestContextParameters['dev_bx_disp'][0] == 'true') {
                $this->debug($batchChoiceRequest, $batchChooseResponse, "p13nBatchChoose");
            }
            return $batchChooseResponse;
        } catch(\Exception $exc)
        {
            $this->throwCorrectP13nException($exc);
        }
    }

    public function getBatchChooseRequest(BxBatchRequest $request, $profileIds = [])
    {
        $batchRequest = new \com\boxalino\p13n\api\thrift\BatchChoiceRequest();
        $batchRequest->userRecord = $this->getUserRecord();
        $batchRequest->profileIds = [$this->getAccount()];
        $batchRequest->choiceInquiry = new \com\boxalino\p13n\api\thrift\ChoiceInquiry();
        $batchRequest->requestContext = new \com\boxalino\p13n\api\thrift\RequestContext();
        $batchRequest->profileContexts = $request->getProfileContextList($profileIds);
        $batchRequest->choiceInquiries = $request->getChoiceInquiryList();

        return $batchRequest;
    }

    protected function getP13n()
    {
        if(empty($this->apiKey) || empty($this->apiSecret))
        {
            $this->host = 'api.bx-cloud.com';
            $this->apiKey = 'boxalino';
            $this->apiSecret = 'tkZ8EXfzeZc6SdXZntCU';
        }

        $this->profileId = $this->getProfileId();
        if(function_exists('curl_version')) {
            $transport = new \Thrift\Transport\P13nTCurlClient($this->host, $this->port, $this->uri, $this->schema);
            $transport->setTimeout($this->curl_timeout);
        } else {
            $transport = new \Thrift\Transport\P13nTHttpClient($this->host, $this->port, $this->uri, $this->schema);
        }

        $transport->setProfileId($this->profileId);
        $transport->setTimeoutSecs($this->timeout);
        $client = new \com\boxalino\p13n\api\thrift\P13nServiceClient(new \Thrift\Protocol\TCompactProtocol($transport));
        $transport->open();

        return $client;
    }

    protected function getUserRecord()
    {
        $userRecord = new \com\boxalino\p13n\api\thrift\UserRecord();
        $userRecord->username = $this->getAccount();
        $userRecord->apiKey = $this->getApiKey();
        $userRecord->apiSecret = $this->getApiSecret();

        return $userRecord;
    }

    public function resetBatchRequests()
    {
        $this->batchChooseRequests = [];
        return $this;
    }

    public function flushResponses()
    {
        $this->batchChooseResponse = null;
        return $this;
    }

    protected function throwCorrectP13nException($e) {
        if(strpos($e->getMessage(), 'Could not connect ') !== false) {
            throw new \Exception('The connection to our server failed before checking your credentials. This might be typically caused by 2 possible things: wrong values in host/schema/port (for exports), api key or api secret (your values are : host=' . $this->host . ', schema=' . $this->schema . ', uri=' . $this->uri .  ', api key =' . $this->getApiKey() . '). Full error message=' . $e->getMessage());
        }

        if(strpos($e->getMessage(), 'Bad protocol id in TCompact message') !== false) {
            throw new \Exception('The connection to our server has worked, but your credentials were refused. Provided credentials  account=' . $this->account . ', host=' . $this->host .  ', api key =' . $this->getApiKey() . '. Full error message=' . $e->getMessage());
        }

        if(strpos($e->getMessage(), 'choice not found') !== false) {
            $parts = explode('choice not found', $e->getMessage());
            $pieces = explode('	at ', $parts[1]);
            $choiceId = str_replace(':', '', trim($pieces[0]));
            throw new \Exception("Configuration IS not live on account " . $this->getAccount() . ": choice/widget $choiceId doesn't exist. NB: If you get a message indicating that the choice doesn't exist, go to http://intelligence.bx-cloud.com, log in your account and make sure that the choice ID you want to use is published.");
        }
        if(strpos($e->getMessage(), 'Solr returned status 404') !== false) {
            throw new \Exception("Data is not live on account " . $this->getAccount() . ": index returns status 404. Please publish your data first, like in example backend_data_basic.php.");
        }

        if(strpos($e->getMessage(), 'undefined field') !== false) {
            $parts = explode('undefined field', $e->getMessage());
            $pieces = explode('	at ', $parts[1]);
            $field = str_replace(':', '', trim($pieces[0]));
            throw new \Exception("The request is done on a filter or facets of a non-existing field of your account " . $this->getAccount() . ": field $field doesn't exist.");
        }

        if(strpos($e->getMessage(), 'All choice variants are excluded') !== false) {
            throw new \Exception("You have an invalid configuration for a choice defined. This is a quite unusual case, please contact support@boxalino.com to get support. ");
        }

        throw $e;
    }

    protected function debug($request, $response, $type)
    {
        ini_set('xdebug.var_display_max_children', -1);
        ini_set('xdebug.var_display_max_data', -1);
        ini_set('xdebug.var_display_max_depth', -1);
        echo "<pre><h1>Batch Request {$type}</h1>" . var_export($request, true) .  "<br><h1>Batch Response</h1>" . var_export($response, true) . "</pre>";;
        exit;
    }

    /**
     * the profile ID of the request must be random to secure node-pining
     */
    public function getProfileId()
    {
        $uuid = bin2hex(random_bytes(16));
        $hyphen = chr(45);
        return substr($uuid, 0, 8).$hyphen
            .substr($uuid, 8, 4).$hyphen
            .substr($uuid,12, 4).$hyphen
            .substr($uuid,16, 4).$hyphen
            .substr($uuid,20,12);
    }

    public function addRequestContextParameter($name, $values)
    {
        if(!is_array($values))
        {
            $values = [$values];
        }

        $this->requestContextParameters[$name] = $values;
        return $this;
    }

    public function resetRequestContextParameter()
    {
        $this->requestContextParameters = [];
    }

    public function setCurlTimeout($timeout)
    {
        $this->curl_timeout = $timeout;
    }

    public function setTestMode($isTest)
    {
        $this->isTest = $isTest;
        return $this;
    }

    public function getAccount($checkDev = true) {
        if($checkDev && $this->isDev) {
            return $this->account . '_dev';
        }
        return $this->account;
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

    public function getApiSecret()
    {
        return $this->apiSecret;
    }

    public function setUri($uri)
    {
        $this->uri = $uri;
        return $this;
    }

    public function setHost($host)
    {
        $this->host = $host;
        return $this;
    }

}
