<?php
/**
 * Adding new "Manual Review" status to "Hold" state
 *
 * @category   IdentityMind
 * @package    IdentityMind_IdentityMindEdna
 */

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

$connection = $installer->getConnection();
/* @var $connection Varien_Db_Adapter_Interface */

try {
    $connection->beginTransaction();
    $connection->insert($installer->getTable('sales/order_status'), array(
        'status'    => 'denied',
        'label'     => 'Deny',
    ));
    $connection->insert($installer->getTable('sales/order_status_state'), array(
        'status'        => 'denied',
        'state'         => 'holded',
        'is_default'    => '0',
    ));

    $connection->insert($installer->getTable('sales/order_status'), array(
        'status'    => 'rejected',
        'label'     => 'Rejected',
    ));
    $connection->insert($installer->getTable('sales/order_status_state'), array(
        'status'        => 'rejected',
        'state'         => 'holded',
        'is_default'    => '0',
    ));
    $connection->commit();
} catch(Exception $e) {
    $connection->rollback();
    Mage::logException($e);
}

$installer->endSetup();