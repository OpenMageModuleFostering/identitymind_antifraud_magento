<?php
/**
 * PHP API communication with IdentityMind EDNA API
 *
 * @category   IdentityMind
 * @package    IdentityMind_IdentityMindEdna
 */
class IdentityMind_IdentityMindEdna_Model_Identitymindapi_Ednarequest {

    const BASE_URL_REQUEST = 'https://204.147.180.38/im/transaction'; // staging.identitymind.com (?)
//    const BASE_URL_REQUEST = 'https://edna.identitymind.com/im/transaction';

    const FEEDBACK_TYPE_ACCEPTED = 'accepted';
    const FEEDBACK_TYPE_REJECTED = 'rejected';

    /**
     * Sends a request to provided url and with provided request params
     * Returns decoded json object (a PHP stdClass), or null on error
     *
     * @param string $url
     * @param array $ednaRequestArray
     * @return stdClass
     */
    private function getResponse($url, $ednaRequestArray = array()) {


        $username = Mage::getStoreConfig('identitymind_edna/api_credentials/user', Mage::app()->getStore());
        $password = Mage::getStoreConfig('identitymind_edna/api_credentials/key', Mage::app()->getStore());

        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
        );


        Mage::log(print_r(array($url, $ednaRequestArray), true), null, 'feedback.log', true);

        $process = curl_init();
        curl_setopt($process, CURLOPT_URL, $url);
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($process, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($process, CURLOPT_HEADER, false);
        curl_setopt($process, CURLOPT_USERPWD, $username . ":" . $password);
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_POST, 1);
        curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        if (!empty($ednaRequestArray)) {
            curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($ednaRequestArray));
        }
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);


        $response = curl_exec($process);

        Mage::log("getResponse: ".print_r($response, true), null, 'feedback.log', true);
        $responseError = curl_error($process);

        if ($responseError) {
            Mage::log('eDNA Response Error:' . $responseError, null, 'feedback.log', true);
            return null;
        } else {
            Mage::log('no eDNA Response Error:' . $responseError, null, 'feedback.log', true);
            return json_decode($response);
        }
    }

    /**
     *
     * @param array $ednaRequestArray
     * @param string $creditCardNumber
     * @return stdClass
     */
    public function getTransactionResponse($ednaRequestArray = array(), $creditCardNumber = '') {
        $ednaRequestArray = Mage::getModel('identitymindedna/identitymindapi_utils_creditcardutils')
            ->addCreditCardData($creditCardNumber, $ednaRequestArray);

        return $this->getResponse(
            Mage::getStoreConfig('identitymind_edna/api_credentials/endpoint', Mage::app()->getStore()),
            $ednaRequestArray
        );
    }

    /**
     *
     * @param type $transactionId
     * @param type $feedbackType
     * @param type $ednaRequestArray
     * @return type
     */
    public function getFeedbackResponse($transactionId, $feedbackType, $ednaRequestArray = array()) {
        $feedbackUrl = Mage::getStoreConfig('identitymind_edna/api_credentials/endpoint', Mage::app()->getStore()) . '/'
            . $transactionId . '/' . $feedbackType;
        return $this->getResponse($feedbackUrl, $ednaRequestArray);
    }


    public function getPaypalTransactionResponse($ednaRequestArray = array()) {
        return $this->getResponse(
            Mage::getStoreConfig('identitymind_edna/api_credentials/endpoint', Mage::app()->getStore()),
            $ednaRequestArray
        );
    }
}