<?php

/**
 * Class Boxalino_Intelligence_Block_Product_List_Blog
 */
class Boxalino_Intelligence_Block_Product_List_Blog extends Boxalino_Intelligence_Block_Recommendation
{

    protected $bxHelperData;

    protected $p13nHelper;

    public function _construct(){
        if($this->getBxRewriteAllowed())
        {
            $this->bxHelperData = Mage::helper('boxalino_intelligence');
            $this->p13nHelper = $this->bxHelperData->getAdapter();
        }

        return parent::_construct();
    }

    protected function _prepareData()
    {
        return $this;
    }

    public function getCmsRecommendationBlocks($content) {
        if($this->isActive()) {
            return $this->bxHelperData->getCmsRecommendationBlocks($content);
        }
        return array();
    }

    public function getReturnFields()
    {
        $fields = array(
            'title',
            $this->getExcerptFieldName(),
            $this->getLinkFieldName(),
            $this->getMediaUrlFieldName(),
            $this->getDateFieldName()

        );

        $extraFields = $this->getExtraFieldNames();

        return array_merge($fields, $extraFields);
    }

    public function getExcerptFieldName(){
        return $this->bxHelperData->getExcerptFieldName();
    }

    public function getLinkFieldName(){
        return $this->bxHelperData->getLinkFieldName();
    }

    public function getMediaUrlFieldName(){
        return $this->bxHelperData->getMediaUrlFieldName();
    }

    public function getDateFieldName(){
        return $this->bxHelperData->getDateFieldName();
    }

    public function getExtraFieldNames(){
        return $this->bxHelperData->getExtraFieldNames();
    }

    public function getBlogArticleImageWidth(){
        return $this->bxHelperData->getBlogArticleImageWidth();
    }

    public function getBlogArticleImageHeight(){
        return $this->bxHelperData->getBlogArticleImageHeight();
    }

    public function getBlogArticleTitle(){
        return $this->p13nHelper->getResponse()->getResultTitle($this->bxHelperData->getBlogArticleWidget());
    }

    public function isActive(){
        if($this->getBxRewriteAllowed())
        {
            return $this->bxHelperData->isBlogRecommendationActive();
        }

        return false;
    }

    public function getBlogArticles()
    {
        $articles = array();
        foreach($this->p13nHelper->getResponse()->getHitFieldValues($this->getReturnFields(), $this->bxHelperData->getBlogArticleWidget()) as $article) {
            $a = array();
            foreach($article as $k => $v) {
                $a[$k] = isset($v[0]) ? $v[0] : '';
            }
            $excerpt = strip_tags($a['products_blog_excerpt']);
            $excerpt = str_replace('[&hellip;]', '', $excerpt);
            $a['products_blog_excerpt'] = $excerpt;
            $articles[] = $a;
        }
        return $articles;
    }

}
