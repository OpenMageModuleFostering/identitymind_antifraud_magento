<?php

class IdentityMind_IdentityMindEdna_Block_System_Config_Form_Fieldset
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        return Mage::helper('identitymindedna')->__(Mage::getStoreConfig('identitymind/access/text'));
    }
}
