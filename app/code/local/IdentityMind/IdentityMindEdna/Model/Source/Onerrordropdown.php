<?php

/**
 * A definition of available options for a drop-down admin field
 *
 * 
 * @category   IdentityMind
 * @package    IdentityMind_IdentityMindEdna
 */
class IdentityMind_IdentityMindEdna_Model_Source_Onerrordropdown {

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        return array(
            array('value' => 'accept', 'label' => Mage::helper('identitymindedna')->__('Accept Order')),
            array('value' => 'deny', 'label' => Mage::helper('identitymindedna')->__('Deny Order')),
            array('value' => 'manual-review', 'label' => Mage::helper('identitymindedna')->__('Set Manual Review Status (On Hold state)')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'accept' => Mage::helper('identitymindedna')->__('Accept Order'),
            'deny' => Mage::helper('identitymindedna')->__('Deny Order'),
            'manual-review' => Mage::helper('identitymindedna')->__('Set Manual Review Status (On Hold state)'),
        );
    }
}