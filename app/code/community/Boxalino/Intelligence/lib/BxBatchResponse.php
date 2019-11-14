<?php
namespace com\boxalino\bxclient\v1;

class BxBatchResponse
{
    protected $bxBatchRequests = [];
    protected $response = null;
    protected $profileItemsFromVariants = null;
    protected $bxBatchProfileContextsIds = [];

    public function __construct($response, $bxBatchProfileIds = [], $bxBatchRequests = [])
    {
        $this->response = $response;
        $this->bxBatchRequests = is_array($bxBatchRequests) ? $bxBatchRequests : [$bxBatchRequests];
        $this->bxBatchProfileContextsIds = $bxBatchProfileIds;
    }

    public function getBatchResponse()
    {
        return $this->response;
    }

    public function getHitFieldValuesByProfileId($profileId)
    {
        if(empty($this->profileItemsFromVariants))
        {
            $this->getResultsFromVariants();
        }

        if(!empty($this->profileItemsFromVariants) && isset($this->profileItemsFromVariants[$profileId]) && !empty($this->profileItemsFromVariants[$profileId]))
        {
            return $this->profileItemsFromVariants[$profileId];
        }

        return [];
    }

    public function getHitFieldValuesForProfileIds()
    {
        $profileItems = [];
        $key = 0;
        foreach($this->response->variants as $variant)
        {
            $items = [];
            foreach($variant->searchResult->hitsGroups as $hitGroup)
            {
                foreach($hitGroup->hits as $hit)
                {
                    $items[] = $hit->values;
                }
            }

            $context = $this->bxBatchProfileContextsIds[$key];
            $profileItems[$context] = $items;
            $key+=1;
        }

        $this->profileItemsFromVariants = $profileItems;
        return $this->profileItemsFromVariants;
    }

    public function getHitFieldValueByField($field)
    {
        $profileHits = [];
        $key = 0;
        foreach($this->response->variants as $variant)
        {
            $values = [];
            foreach($variant->searchResult->hitsGroups as $hitGroup)
            {
                foreach($hitGroup->hits as $hit)
                {
                    $values[] = $hit->values[$field][0];
                }
            }

            $context = $this->bxBatchProfileContextsIds[$key];
            $profileHits[$context] = $values;
            $key+=1;
        }

        return $profileHits;
    }

    public function getHitIds($field='id')
    {
        $profileHits = [];
        $key = 0;
        foreach($this->response->variants as $variant)
        {
            $values = [];
            foreach($variant->searchResult->hitsGroups as $hitGroup)
            {
                foreach($hitGroup->hits as $hit)
                {
                    $values[] = $hit->values[$field][0];
                }
            }

            $context = $this->bxBatchProfileContextsIds[$key];
            $profileHits[$context] = $values;
            $key+=1;
        }

        return $profileHits;
    }

    public function getResultsFromVariants()
    {
        return [];
    }

}
