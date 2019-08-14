<?php

/**
 * This class is a 1-1 wrapper of provided IdentityMind JAVA class CreditCardUtils (package: com.identitymind.utils)
 * transfered from JAVA to PHP code
 * 
 * @category   IdentityMind
 * @package    IdentityMind_IdentityMindEdna
 */
class IdentityMind_IdentityMindEdna_Model_Identitymindapi_Utils_Creditcardutils {
    
    private $_salt = '54l73D47';
    
    public function addCreditCardData($creditCardNumber, $requestArray = array()) {
        $requestArray['pccn'] = $this->generateFullCardHash($creditCardNumber);
        $requestArray['pcct'] = $this->generateCardToken($creditCardNumber);
        $requestArray['pccp'] = $this->generatePartialCardHash($creditCardNumber);
        return $requestArray;
    }
    
    private function generateFullCardHash($creditCardNumber) {
        $creditCardNumber = $this->normalizeCreditCard($creditCardNumber);
        return sha1($this->_salt . $creditCardNumber);
    }
    
    private function generateCardToken($creditCardNumber) {
        if (empty($creditCardNumber)) {
            Mage::throwException(Mage::helper('identitymindedna')->__('card number must be provided'));
        }
        $creditCardNumber = $this->normalizeCreditCard($creditCardNumber);
        if (strlen($creditCardNumber) == 15 || strlen($creditCardNumber) == 16) {
            return substr($creditCardNumber, 0, 6) . 'XXXXXX' . substr($creditCardNumber, 12);
        } else {
            Mage::throwException(Mage::helper('identitymindedna')->__('card number did not normalize to a 15 or 16 digit number'));
        }
    }
    
    private function generatePartialCardHash($creditCardNumber) {
        $creditCardNumber = $this->normalizeCreditCard($creditCardNumber);
        return $this->generateFullCardHash(substr($creditCardNumber, 0, 11));
    }
    
    private function normalizeCreditCard($creditCardNumber) {
        return preg_replace('/[\s\W]+/', "", $creditCardNumber);
    }
}