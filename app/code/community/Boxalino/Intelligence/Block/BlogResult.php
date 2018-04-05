<?php

/**
 * Class Boxalino_Intelligence_Block_BlogResult
 */
Class Boxalino_Intelligence_Block_BlogResult extends Mage_Core_Block_Template{

  private $bxHelperData;
  private $p13nHelper;
  private $blogCollection = null;
  private $blog_page_param = 'bx_blog_page';

  public function _construct()
  {
    $this->bxHelperData = Mage::helper('boxalino_intelligence');
    $this->p13nHelper = $this->bxHelperData->getAdapter();

    parent::_construct();
  }

    public function getBlogs() {
        if(is_null($this->blogCollection)) {
            $this->prepareBlogCollection();
        }
        return $this->blogCollection;
    }

    public function getPreviousPageUrl() {
        return $this->getPageUrl($this->getPage()-1);
    }

    protected function prepareBlogCollection() {
        $blogs = array();
        $blog_ids = $this->p13nHelper->getBlogIds();
        foreach ($blog_ids as $id) {
            $blog = array();
            foreach ($this->bxHelperData->getBlogReturnFields() as $field) {
                $value = $this->p13nHelper->getHitVariable($id, $field, true);
                $blog[$field] = is_array($value) ? reset($value) : $value;
            }

            if($blog['products_blog_excerpt']) {
              $excerpt = strip_tags($blog['products_blog_excerpt']);
              $excerpt = str_replace('[&hellip;]', '', $excerpt);
              $blog['products_blog_excerpt'] = $excerpt;
            }

            $blogs[$id] = $blog;
        }
        $this->blogCollection = $blogs;
    }

    public function getBlogPageParam() {
        return $this->blog_page_param;
    }

    public function getPage() {
        // return $this->_request->getParam($this->getBlogPageParam(), 1);
    }

    public function getFirstNum() {
        return ($this->getPageSize() * ($this->getPage() -1)) + 1;
    }

    public function canShowLast() {
        return $this->getPage() == $this->getLastPageNum();
    }

    public function getNextPageUrl() {
        return $this->getPageUrl($this->getPage() + 1);
    }

    public function isPageCurrent($page) {
        return $this->getPage() == $page;
    }

    public function isFirstPage() {
        return $this->getPage() == 1;
    }

    public function getFramePages() {
        return range(1, $this->getLastPageNum());
    }

    public function getLastPageUrl() {
        return $this->getPageUrl($this->getLastPageNum());
    }

    public function canShowFirst() {
        return $this->getPage() > 1 && $this->getLastPageNum() > 1;
    }

    public function canShowNextJump() {
        return $this->getPage() > $this->getLastPageNum();
    }

    public function getNextJumpUrl() {
        return $this->getPageUrl($this->getPage() + 1);
    }

    public function getPageUrl($page) {
        $query = [$this->getBlogPageParam() => $page, 'bx_active_tab' => 'blog'];
        return $this->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true, '_query' => $query]);
    }

    public function canShowPreviousJump() {
        return $this->canShowFirst();
    }

    public function getPageSize() {
      // var_dump('<pre>' ,Mage::app()->getRequest()->getParams());exit;
      // $size = $this->_request->getParam('product_list_limit', $this->p13nHelper->getMagentoStoreConfigPageSize());
        $size = $this->p13nHelper->getMagentoStoreConfigPageSize();
        return $size;
    }

    public function getLastNum() {
        return ($this->getPageSize() * ($this->getPage() - 1)) + sizeof($this->getBlogs());
    }

    public function isLastPage() {
        return $this->getPage() == $this->getLastPageNum();
    }

    // public function getAnchorTextForPrevious() {
    //     return $this->_scopeConfig->getValue(
    //         'design/pagination/anchor_text_for_previous',
    //         \Magento\Store\Model\ScopeInterface::SCOPE_STORE
    //     );
    // }

    // public function getAnchorTextForNext() {
    //     return $this->_scopeConfig->getValue(
    //         'design/pagination/anchor_text_for_next',
    //         \Magento\Store\Model\ScopeInterface::SCOPE_STORE
    //     );
    // }

    public function getLastPageNum() {
        $total = $this->getTotalHitCount();
        return ceil($total / $this->getPageSize());
    }

    public function getLinkFieldName() {
        return $this->bxHelperData->getLinkFieldName();
    }

    public function getBlogArticleImageWidth() {
        return $this->bxHelperData->getBlogArticleImageWidth();
    }

    public function getBlogArticleImageHeight() {
        return $this->bxHelperData->getBlogArticleImageHeight();
    }

    public function getMediaUrlFieldName() {
        return $this->bxHelperData->getMediaUrlFieldName();
    }

    public function getExcerptFieldName() {
        return $this->bxHelperData->getExcerptFieldName();
    }

    public function getTotalHitCount(){
        return $this->p13nHelper->getBlogTotalHitCount();
    }

}
