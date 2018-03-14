<?php

/**
 * Class Boxalino_Intelligence_Block_Notification
 */
class Boxalino_Intelligence_Block_Notification extends Mage_Core_Block_Template {

    public function displayNotification() {
        $bxDataHelper = Mage::helper('boxalino_intelligence');
        if($bxDataHelper->isPluginEnabled()) {
            $bxDataHelper->getAdapter()->finalNotificationCheck();
        }
    }

}
