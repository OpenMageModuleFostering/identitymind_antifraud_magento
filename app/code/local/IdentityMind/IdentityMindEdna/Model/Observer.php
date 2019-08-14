<?php

/**
 * The main Observer class, which catches following events:
 * sales_quote_payment_import_data_before
 * sales_order_payment_place_end
 * sales_order_payment_capture
 * sales_order_payment_void
 * sales_order_payment_cancel
 *
 *
 * @category   IdentityMind
 * @package    IdentityMind_IdentityMindEdna
 */
class IdentityMind_IdentityMindEdna_Model_Observer {

    private $_ednaResponse = null;
    private $_afterValidationAction = null;

    /**
     * Set payment data into registry for further operations (if needed)
     * @param Varien_Event_Observer $observer
     */
    public function verifyCreditCardBefore(Varien_Event_Observer $observer)
    {
        Mage::log("verifyCreditCardBefore", null, 'feedback.log', true);
        $paymentObject = new Varien_Object();

        $input = $observer->getEvent()->getInput();

        if (!isset($input['cc_number'])) {
            return $this;
        }
        $paymentObject->setPayment($observer->getEvent()->getPayment());
        $paymentObject->setInput($input);

        if (!Mage::registry('payment_object')) {
            Mage::register('payment_object', $paymentObject);
        }
    }

    /**
     * A method to be added for 'sales_quote_payment_import_data_before' event
     * This is a verification of CC data entered by the front-end user (both: admin and regular customer)
     * It prepares and sends a request data to the IdentityMind API server with use of Identitymindapi_Ednarequest model
     *
     * @param Varien_Object $obj
     * @throws Mage_Core_Exception
     * @return IdentityMind_IdentityMindEdna_Model_Observer
     */
    public function verifyCreditCard(Varien_Object $obj)
    {
        Mage::log("verifyCreditCard", null, 'feedback.log', true);

        if (!(bool)Mage::getStoreConfig('identitymind_edna/general/enabled', Mage::app()->getStore())) {
            return $this;
        }

        $input = $obj->getInput();

        $payment = $obj->getPayment();
        /* @var $payment Mage_Sales_Model_Quote_Payment */
        $order = $payment->getOrder();

        $quote = $payment->getQuote();
        /* @var $quote Mage_Sales_Model_Quote */
        $billingAddress = $quote->getBillingAddress();
        /* @var $billingAddress Mage_Sales_Model_Quote_Address */

        $shippingAddress = $quote->getShippingAddress();
        /* @var $shippingAddress Mage_Sales_Model_Quote_Address */

        $ednaRequestArray = array(
            'bfn' => $billingAddress->getFirstname(),
            'bln' => $billingAddress->getLastname(),
            'bsn' => $billingAddress->getStreetFull(),
            'bc'  => $billingAddress->getCity(),
            'bs'  => $billingAddress->getRegionCode(),
            'bz'  => $billingAddress->getPostcode(),
            'bco' => $billingAddress->getCountry(),
            'sfn' => $shippingAddress->getFirstname(),
            'sln' => $shippingAddress->getLastname(),
            'ssn' => $shippingAddress->getStreetFull(),
            'sc'  => $shippingAddress->getCity(),
            'ss'  => $shippingAddress->getRegionCode(),
            'sz'  => $shippingAddress->getPostcode(),
            'sco' => $shippingAddress->getCountry(),
            'ip'  => $quote->getRemoteIp(),
            'blg' => $_SERVER['HTTP_ACCEPT_LANGUAGE'],
            'tea' => $quote->getCustomerEmail(),
            'ph'  => $billingAddress->getTelephone(),
            'amt' => $quote->getGrandTotal(),
            'ccy' => $quote->getQuoteCurrencyCode(),
        );

        if (!$quote->getCustomerIsGuest()) {
            $customer = $quote->getCustomer();
            /* @var $customer Mage_Customer_Model_Customer */

            $registeredUserDetailsArray = array(
                'acd' => $customer->getCreatedAtTimestamp(),
                'man' => $customer->getLastname().', '.$customer->getFirstname(),
                'mem' => $customer->getEmail(),
            );
            $ednaRequestArray = array_merge($ednaRequestArray, $registeredUserDetailsArray);
        }

        $this->_ednaResponse = Mage::getModel('identitymindedna/identitymindapi_ednarequest')
            ->getTransactionResponse($ednaRequestArray, $input['cc_number']);

        if ( !($this->_ednaResponse instanceof stdClass)) {
            Mage::log('IdentityMind API Response was incorrect (reserved-order-ID: ' . $quote->getReservedOrderId() . ')');
        }

        if (isset($this->_ednaResponse->transaction_status) && $this->_ednaResponse->transaction_status == 'error') {
            $action = strtoupper(Mage::getStoreConfig('identitymind_edna/configuration/action_on_error', Mage::app()->getStore()));
            $this->_afterValidationAction = str_replace('-', '_', $action);
        } else {
            $this->_afterValidationAction = $this->_ednaResponse->res;
        }

        if (isset($this->_ednaResponse->tid)) {
            Mage::helper('identitymindedna')->setOrderPaymentEdnaTransactionId($payment,$this->_ednaResponse->tid);
            Mage::register(IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_TRANSACTION_ID_KEY, $this->_ednaResponse->tid);
            if (!Mage::registry(IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_FEEDBACK_SENT_FLAG)){
                if(!Mage::registry(IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_OBJECT_KEY)) {
                    Mage::register(IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_OBJECT_KEY, $payment);
                }
                $this->sendFeedback();
            }
        }


        switch ($this->_afterValidationAction) {
            case 'DENY':
//                $exceptionMessage = isset($this->_ednaResponse->res)?$this->_ednaResponse->res . ': ' . $this->_ednaResponse->frn:'Your Credit Card details were rejected';
//                Mage::throwException($exceptionMessage);

//                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, IdentityMind_IdentityMindEdna_Helper_Data::ORDER_STATUS_MANUAL_REVIEW, $exceptionMessage, false);
            case 'MANUAL_REVIEW':
            case 'ACCEPT':
            default:
                return $this;
        }

    }

    /**
     * Method added to the 'sales_order_payment_place_end' event
     * event after successful payment place and just before order save
     * it uses a response object returned by IdentityMind EDNA API on CC details validation
     * (returned on the previous step)
     *
     * It is used only for adding the response message to the order comments, and
     * if the response was MANUAL_REVIEW - it puts the order to "OnHold" state with "Manual Review" status
     *
     * @param Varien_Event_Observer $observer
     * @return \IdentityMind_IdentityMindEdna_Model_Observer
     */
    public function applyActionToOrder(Varien_Event_Observer $observer)
    {
        Mage::log("applyActionToOrder", null, 'feedback.log', true);
        if ($paymentObject = Mage::registry('payment_object')) {
            $this->verifyCreditCard($paymentObject);
        }

        if (!(bool)Mage::getStoreConfig('identitymind_edna/general/enabled', Mage::app()->getStore())) {
            return $this;
        }

        if ( empty($this->_ednaResponse) || !($this->_ednaResponse instanceof stdClass)) {
            return $this;
        }

        /* @var $payment Mage_Sales_Model_Order_Payment */
        $payment = $observer->getEvent()->getPayment();

        /*
         * process further only if the payment uses "Authorize Only" (without capture on order placement)
         * if the payment transaction is captured - it's already paid and there's nothing we can do
         */
        /*if ($payment->getMethodInstance()->getConfigPaymentAction() != Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE) {
            return $this;
        }*/

        $order = $payment->getOrder();

        /* @var $order Mage_Sales_Model_Order */
        switch($this->_afterValidationAction) {
            case 'MANUAL_REVIEW':
                $message = '';
                if (isset($this->_ednaResponse->res)) {
                    $message = '[IdentityMind eDNA] (transactionID:' . $this->_ednaResponse->tid . ') "'
                        . $this->_ednaResponse->res . '" reason: ' . $this->_ednaResponse->frn;

                    if (isset($this->_ednaResponse->ram)) {
                        $message .= '; "ram": ' . $this->_ednaResponse->ram;
                    }

                    if (isset($this->_ednaResponse->frd)) {
                        $message .= '; "frd": ' . $this->_ednaResponse->frd;
                    }
                } elseif(isset($this->_ednaResponse->error_message)) {
                    $message = '[IdentityMind eDNA] error on request to eDNA: ' . $this->_ednaResponse->error_message;
                }
                $order->setHoldBeforeState($order->getState());
                $order->setHoldBeforeStatus($order->getStatus());
                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, IdentityMind_IdentityMindEdna_Helper_Data::ORDER_STATUS_MANUAL_REVIEW, $message, false);

                break;
            case 'ACCEPT':
                $message = '[IdentityMind eDNA] (transactionID:' . $this->_ednaResponse->tid . ') "'
                    . $this->_ednaResponse->res . '": ' . $this->_ednaResponse->frn;
                if (isset($this->_ednaResponse->frd)) {
                    $message .= '; "frd": ' . $this->_ednaResponse->frd;
                }
                $order->addStatusHistoryComment($message);
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, Mage_Sales_Model_Order::STATE_PROCESSING, $message, false);
                break;
            case 'DENY':
                $message = '[IdentityMind eDNA] (transactionID:' . $this->_ednaResponse->tid . ') "'
                    . $this->_ednaResponse->res . '": ' . $this->_ednaResponse->frn;
                if (isset($this->_ednaResponse->frd)) {
                    $message .= '; "frd": ' . $this->_ednaResponse->frd;
                }
                $order->addStatusHistoryComment($message);
                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, IdentityMind_IdentityMindEdna_Helper_Data::ORDER_STATUS_DENIED, $message, false);
                break;
            case 'REJECT':
                $message = '[IdentityMind eDNA] (transactionID:' . $this->_ednaResponse->tid . ') "'
                    . $this->_ednaResponse->res . '": ' . $this->_ednaResponse->frn;
                if (isset($this->_ednaResponse->frd)) {
                    $message .= '; "frd": ' . $this->_ednaResponse->frd;
                }
                $order->addStatusHistoryComment($message);
                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, IdentityMind_IdentityMindEdna_Helper_Data::ORDER_STATUS_REJECTED, $message, false);
                break;
            default:
                return $this;
        }
        return $this;
    }

    /**
     * Sends a feedback after the payment was: captured, voided or canceled
     * it uses methods from Identitymindapi_Ednarequest model
     *
     * @param Varien_Event_Observer $observer
     * @return \IdentityMind_IdentityMindEdna_Model_Observer
     */
    public function sendFeedback($observer = null)
    {
        Mage::log("sendFeedback", null, 'feedback.log', true);

        if (!(bool)Mage::getStoreConfig('identitymind_edna/general/enabled')) {
            return $this;
        }

        $ednaTransactionId = Mage::registry(IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_TRANSACTION_ID_KEY);
        $feedbackEventName = Mage::registry(IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_FEEDBACK_EVENT_NAME);
        $payment  = Mage::registry(IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_OBJECT_KEY);

        if (!$feedbackEventName && $observer) {
            Mage::register(IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_FEEDBACK_EVENT_NAME, $observer->getEvent()->getName());
        }

        if (!$payment && $observer) {
            $payment = $observer->getEvent()->getPayment();
            Mage::register(IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_OBJECT_KEY, $payment);
        }

        if (!$ednaTransactionId ) {
            return $this;
        }



        $feedbackType = null;

        switch($feedbackEventName) {
            case 'sales_order_payment_capture':
                $feedbackType = IdentityMind_IdentityMindEdna_Model_Identitymindapi_Ednarequest::FEEDBACK_TYPE_ACCEPTED;
                break;
            case 'sales_order_payment_void':
            case 'sales_order_payment_cancel':
                $feedbackType = IdentityMind_IdentityMindEdna_Model_Identitymindapi_Ednarequest::FEEDBACK_TYPE_REJECTED;
                break;
            default:
                return $this;
        }

        $ednaRequestArray = array(
            'tid' => $ednaTransactionId
        );

        $this->_ednaResponse = Mage::getModel('identitymindedna/identitymindapi_ednarequest')
            ->getFeedbackResponse($ednaTransactionId, $feedbackType, $ednaRequestArray);

        $order = $payment->getOrder();

        if (!$order) {
            return;
        }

        /* @var $order Mage_Sales_Model_Order */

        if ( !($this->_ednaResponse instanceof stdClass)) {
            Mage::log(
                'IdentityMind API Response was incorrect for Order ID: ' . $order->getId() . ', Order increment-ID: '
                    . $order->getIncrementId()
            );
        }

        if (isset($this->_ednaResponse->transaction_status) && $this->_ednaResponse->transaction_status == 'error') {
            $order->addStatusHistoryComment(
                'There was an error during IdentityMind eDNA feedback request: '
                    . $this->_ednaResponse->error_message
            );
            $this->_ednaResponse = null;
            return $this;
        } elseif(isset($this->_ednaResponse->message)) {

            Mage::log('[IdentityMind eDNA] Feedback response: ' . $this->_ednaResponse->message, null, 'feedback.log', true);
            $order->addStatusHistoryComment('[IdentityMind eDNA] Feedback response: ' . $this->_ednaResponse->message);
        }
        Mage::log("The End", null, 'feedback.log', true);
        Mage::register(IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_FEEDBACK_SENT_FLAG, true);
        return $this;
    }

    public  function orderPaymentSaveAfter(Varien_Event_Observer $observer)
    {
        Mage::log("orderPaymentSaveAfter", null, 'feedback.log', true);
        $payment = $observer->getEvent()->getPayment();

        if (Mage::registry('paypal_payment_'.$payment->getId())) {
            return;
        }

        $info = $payment->getAdditionalInformation();

        if (!isset($info['paypal_payer_id']) || !isset($info['paypal_payer_email'])) {
            return;
        }

        $quote = Mage::getSingleton('checkout/session')->getQuote();

        if (!$quote || !($quote->getId()>0)) {
            return;
        }

        $billingAddress = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();

        if (!$billingAddress || !$shippingAddress || !$quote->getGrandTotal() || !$quote->getCustomerEmail()) {
            return;
        }

        $ednaRequestArray = array(
            'bfn' => $billingAddress->getFirstname(),
            'bln' => $billingAddress->getLastname(),
            'bsn' => $billingAddress->getStreetFull(),
            'bc'  => $billingAddress->getCity(),
            'bs'  => $billingAddress->getRegionCode(),
            'bz'  => $billingAddress->getPostcode(),
            'bco' => $billingAddress->getCountry(),
            'sfn' => $shippingAddress->getFirstname(),
            'sln' => $shippingAddress->getLastname(),
            'ssn' => $shippingAddress->getStreetFull(),
            'sc'  => $shippingAddress->getCity(),
            'ss'  => $shippingAddress->getRegionCode(),
            'sz'  => $shippingAddress->getPostcode(),
            'sco' => $shippingAddress->getCountry(),
            'ip'  => $quote->getRemoteIp(),
            'blg' => $_SERVER['HTTP_ACCEPT_LANGUAGE'],
            'tea' => $quote->getCustomerEmail(),
            'ph'  => $billingAddress->getTelephone(),
            'amt' => $quote->getGrandTotal(),
            'ccy' => $quote->getQuoteCurrencyCode(),
            'pppi'=> $info['paypal_payer_id'],
            'pppe'=> $info['paypal_payer_email']
        );

        $ednaResponse = Mage::getModel('identitymindedna/identitymindapi_ednarequest')
            ->getPaypalTransactionResponse($ednaRequestArray);

        Mage::helper('identitymindedna')->setOrderPaymentEdnaTransactionId($payment,$ednaResponse->tid);

        Mage::register('paypal_payment_'.$payment->getId(), true);

        $order = $payment->getOrder();


        $order->addStatusHistoryComment(
            "[IdentityMind eDNA] PayPal Transaction: <br />" .
                "Transaction status:".$ednaResponse->res."<br />".
                "Result:".$ednaResponse->transaction_status."<br />"
        );

        $msg = isset($ednaResponse->res) ? $ednaResponse->res  . ': ' . $ednaResponse->frn : 'Your Paypal Account details were rejected';
        echo get_class($order->getPayment());
        switch ($ednaResponse->res) {
            case 'DENY':
                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, IdentityMind_IdentityMindEdna_Helper_Data::ORDER_STATUS_DENIED, $msg, false);
               // $order->getPayment()->deny();
                break;
            case 'MANUAL_REVIEW':

                $order->setHoldBeforeState($order->getState());
                $order->setHoldBeforeStatus($order->getStatus());
                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, IdentityMind_IdentityMindEdna_Helper_Data::ORDER_STATUS_MANUAL_REVIEW, $msg, false);
                break;
            case 'ACCEPT':
                $msg = '[IdentityMind eDNA] (transactionID:' . $ednaResponse->tid . ') "'
                    . $ednaResponse->res . '": ' . $ednaResponse->frn;
                if (isset($ednaResponse->frd)) {
                    $msg .= '; "frd": ' . $ednaResponse->frd;
                }
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, Mage_Sales_Model_Order::STATE_PROCESSING, $msg, false);
               // $order->getPayment()->accept();
                break;
            case 'REJECTED':
                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, IdentityMind_IdentityMindEdna_Helper_Data::ORDER_STATUS_REJECTED, $msg, false);
                break;
            default:
                return $this;
        }
//        $order->save();

        return;
    }

    public function paymentReviewCompletedAfter(Varien_Event_Observer $observer)
    {
        $payment = $observer->getEvent()->getPayment();
        $action = $observer->getEvent()->getAction();

        $event = ($action == 'accept') ? 'sales_order_payment_capture' : 'sales_order_payment_cancel';

        Mage::log("paymentReviewCompletedAfter; action: ".$action, null, 'feedback.log', true);

        Mage::register(
            IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_TRANSACTION_ID_KEY,
            $payment->getAdditionalInformation('edna_transaction_id')
        );

        Mage::register(
            IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_FEEDBACK_EVENT_NAME,
            $event
        );

        Mage::register(
            IdentityMind_IdentityMindEdna_Helper_Data::PAYMENT_OBJECT_KEY,
            $payment
        );

        return $this->sendFeedback();
    }

};