<?php
require_once "Mage/CatalogSearch/controllers/ResultController.php";
class Boxalino_Intelligence_ResultController extends Mage_CatalogSearch_ResultController
{

    public function indexAction()
    {
        $bxHelperData = Mage::helper('boxalino_intelligence');
        try{
            if($bxHelperData->isSearchEnabled())
            {
                $adapter = $bxHelperData->getAdapter();
                $adapter->setIsSearch(true);
                $start = microtime(true);
                $adapter->addNotification('debug', "request start at " . $start);
                $redirect_link = $adapter->getResponse()->getRedirectLink();
                $adapter->addNotification('debug',
                    "request end, time: " . (microtime(true) - $start) * 1000 . "ms" .
                    ", memory: " . memory_get_usage(true));

                if ($redirect_link != "") {
                    $this->getResponse()->setRedirect($redirect_link);
                }
                if($bxHelperData->getAdapter()->areThereSubPhrases() && sizeof($queries = $bxHelperData->getAdapter()->getSubPhrasesQueries()) == 1) {
                    $this->getResponse()->setRedirect(Mage::getUrl('*/*', array('_query' => ['q' => $queries[0], 'bx_initial_q' => Mage::app()->getRequest()->getParam('q')])));
                }
            }
        } catch(\Exception $e) {
            Mage::logException($e);
            $bxHelperData->setFallback(true);
        }
        parent::indexAction();

    }
}
