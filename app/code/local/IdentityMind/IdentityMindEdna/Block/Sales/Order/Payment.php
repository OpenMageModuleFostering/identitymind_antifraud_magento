<?php

/**
 * This is an overwrite of core Magento block class: Mage_Adminhtml_Block_Sales_Order_Payment
 * it adds a http link right after the code of Payment Information section
 * 
 * @category   IdentityMind
 * @package    IdentityMind_IdentityMindEdna
 */
class IdentityMind_IdentityMindEdna_Block_Sales_Order_Payment extends Mage_Adminhtml_Block_Sales_Order_Payment {
    
    /**
     * Transforms returned HTML code of Payment Information
     * add a http link to IdentityMind EDNA panel
     * 
     * @param string $html
     * @return string 
     */
    protected function _afterToHtml($html) {
        $payment = $this->getParentBlock()->getOrder()->getPayment();
        /* @var $payment Mage_Sales_Model_Order_Payment */
        
        if ($payment->getAdditionalInformation(IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_TRANSACTION_ID_KEY)) {
            $html = $html . '<a href="https://edna.identitymind.com" target="_blank">IdentityMind eDNA Login</a>';
        }
        
        return parent::_afterToHtml(    $html);
    }
    
}