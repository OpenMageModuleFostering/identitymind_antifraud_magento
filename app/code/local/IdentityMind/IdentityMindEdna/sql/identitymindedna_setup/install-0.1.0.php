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
    $connection->query(
        $installer->getTable('sales/order_status'), array(
        'status'    => 'manual_review',
        'label'     => 'Manual Review',
    ));
    $connection->insert($installer->getTable('sales/order_status_state'), array(
        'status'        => 'manual_review',
        'state'         => 'holded',
        'is_default'    => '0',
    ));
    $connection->commit();
} catch(Exception $e) {
    Mage::logException($e);
    $connection->rollback();
}

$installer->endSetup();