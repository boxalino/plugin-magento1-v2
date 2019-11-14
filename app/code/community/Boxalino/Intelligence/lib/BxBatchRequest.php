<?php
namespace com\boxalino\bxclient\v1;

use com\boxalino\p13n\api\thrift\RequestContext;
use \com\boxalino\bxclient\v1\BxFacets;
use \com\boxalino\bxclient\v1\BxSortFields;
use \com\boxalino\p13n\api\thrift\ChoiceInquiry;
use \com\boxalino\p13n\api\thrift\ProfileContext;

class BxBatchRequest extends BxRequest
{
    protected $profileIds = [];
    protected $choiceInquiryList = [];
    protected $isTest = null;
    protected $isDev = false;
    protected $profileContextList = [];
    protected $requestContextParameters = [];
    protected $sameInquiry = true;
    protected $contextItems = [];

    public function __construct($language, $choiceId, $max=10, $min=0) {
        if($choiceId == ''){
            throw new \Exception('BxBatchRequest created with null choiceId');
        }
        parent::__construct($language, $choiceId, $max, $min);

      #configurations from parent initialize
      $this->bxFacets = new \com\boxalino\bxclient\v1\BxFacets();
      $this->bxSortFields =  new \com\boxalino\bxclient\v1\BxSortFields();
    }

    public function getChoiceInquiryList()
    {
        if(empty($this->profileIds))
        {
            return [];
        }

        if($this->sameInquiry)
        {
            $this->choiceInquiryList[] = $this->createMainInquiry();
        }

        return $this->choiceInquiryList;
    }

    public function getProfileContextList($setOfProfileIds = [])
    {
        if(empty($this->profileIds) && empty($setOfProfileIds))
        {
            return [];
        }

        $profileIds = $setOfProfileIds;
        if(empty($setOfProfileIds))
        {
            $profileIds = $this->getProfileIds();
        }

        foreach($profileIds as $id)
        {
            $this->addProfileContext($id);
        }

        return $this->profileContextList;
    }

    /**
     * very like the original
     * @return \com\boxalino\p13n\api\thrift\SimpleSearchQuery
     */
    public function getSimpleSearchQuery()
    {
        $searchQuery = new \com\boxalino\p13n\api\thrift\SimpleSearchQuery();
        $searchQuery->indexId = $this->getIndexId();
        $searchQuery->language = $this->getLanguage();
        $searchQuery->returnFields = $this->getReturnFields();
        $searchQuery->offset = $this->getOffset();
        $searchQuery->hitCount = $this->getMax();
        $searchQuery->queryText = $this->getQueryText();
        $searchQuery->groupBy = $this->getGroupBy();
        if(!is_null($this->hitsGroupsAsHits)) {
            $searchQuery->hitsGroupsAsHits = $this->hitsGroupsAsHits;
        }
        if(sizeof($this->getFilters()) > 0) {
            $searchQuery->filters = [];
            foreach($this->getFilters() as $filter) {
                $searchQuery->filters[] = $filter->getThriftFilter();
            }
        }
        $searchQuery->orFilters = $this->getOrFilters();
        if($this->getFacets()) {
            $searchQuery->facetRequests = $this->getFacets()->getThriftFacets();
        }
        if($this->getSortFields()) {
            $searchQuery->sortFields = $this->getSortFields()->getThriftSortFields();
        }

        return $searchQuery;
    }

    public function getRequestContext($id)
    {
        $requestContext = new \com\boxalino\p13n\api\thrift\RequestContext();
        $requestContext->parameters = [];
        $requestContext->parameters['customerId'] = [$id];
        if(!empty($this->requestContextParameters))
        {
            foreach($this->getRequestContextParameters() as $key=>$value)
            {
                $requestContext->parameters[$key] = $value;
            }
        }

        return $requestContext;
    }

    public function createMainInquiry()
    {
        $choiceInquiry = new \com\boxalino\p13n\api\thrift\ChoiceInquiry();
        $choiceInquiry->choiceId = $this->getChoiceId();
        if($this->isTest || ($this->isDev && empty($this->isTest)))
        {
            $choiceInquiry->choiceId = $choiceInquiry->choiceId . "_debugtest";
        }

        $choiceInquiry->simpleSearchQuery = $this->getSimpleSearchQuery();
        $choiceInquiry->contextItems = $this->getContextItems();
        $choiceInquiry->minHitCount = $this->getMin();
        $choiceInquiry->withRelaxation = $this->getWithRelaxation();

        return $choiceInquiry;
    }

    public function addProfileContext($id, $requestContext = null)
    {
        if(empty($requestContext))
        {
            $requestContext = $this->getRequestContext($id);
        }

        $profileContext = new \com\boxalino\p13n\api\thrift\ProfileContext();
        $profileContext->profileId = $id;
        $profileContext->requestContext = $requestContext;

        $this->profileContextList[] = $profileContext;
        return $this->profileContextList;
    }

    public function addChoiceInquiry($newChoiceInquiry)
    {
        $this->choiceInquiryList[] = $newChoiceInquiry;
        return $this->choiceInquiryList;
    }

    public function setUseSameChoiceInquiry($sameInquiry)
    {
        $this->sameInquiry = $sameInquiry;
        return $this;
    }

    public function setProfileIds($ids)
    {
        $this->profileIds = $ids;
        return $this;
    }

    public function getProfileIds()
    {
        return $this->profileIds;
    }

    public function setRequestContextParameters($requestParams)
    {
        $this->requestContextParameters = $requestParams;
        return $this;
    }

    public function setIsDev($dev)
    {
        $this->isDev = $dev;
        return $this;
    }

}
