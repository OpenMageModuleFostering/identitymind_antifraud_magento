<?php

/**
 * A default module helper
 * 
 * @category   IdentityMind
 * @package    IdentityMind_IdentityMindEdna
 */
class IdentityMind_IdentityMindEdna_Helper_Data extends Mage_Core_Helper_Abstract {
    
    const PAYMENT_TRANSACTION_ID_KEY = 'edna_transaction_id';
    const PAYMENT_FEEDBACK_EVENT_NAME = 'feedback_event_type';
    const PAYMENT_OBJECT_KEY = 'payment_object';
    const PAYMENT_FEEDBACK_SENT_FLAG = 'feedback_sent';

    const ORDER_STATUS_MANUAL_REVIEW = 'manual_review';
    const ORDER_STATUS_DENIED = 'denied';
    const ORDER_STATUS_REJECTED = 'rejected';
    const ORDER_STATUS_PENDING = 'pending';
    const ORDER_STATUS_ACCEPTED = 'processing';

    /**
     * Checks if current payment belongs to an order, which was after the Manual Review status
     * 
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return boolean 
     */
    public function isOrderPaymentAfterManualReview(Mage_Sales_Model_Order_Payment $payment) {
        
        $order = $payment->getOrder();
        /* @var $order Mage_Sales_Model_Order */
        
        $statusHistoryCollection = $order->getStatusHistoryCollection();
        
        foreach ($statusHistoryCollection as $status) {
            if ($status->getStatus() == self::ORDER_STATUS_MANUAL_REVIEW) {
                return true;
            }
        }
        
        return false;
    }
    /**
     * Sets IdentityMind transactionID in the additional information array for Payment object
     * 
     * @param Mage_Sales_Model_Quote_Payment $payment
     * @param string $transactionId 
     */
    public function setOrderPaymentEdnaTransactionId($payment, $transactionId) {
        $payment->setAdditionalInformation(self::PAYMENT_TRANSACTION_ID_KEY, $transactionId);
    }

    /**
     * Gets IdentityMind transactionID from the current Payment object
     * (returns null if not set)
     * 
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return string (null if not set) 
     */
    public function getOrderPaymentEdnaTransactionId($payment) {
        return $payment->getAdditionalInformation(self::PAYMENT_TRANSACTION_ID_KEY);
    }
    
}