<?php

$installer = $this;

$installer->startSetup();
$installer->run("UPDATE {$this->getTable('sales/order_status')} SET label = 'Accept' WHERE status = 'processing';");
$installer->run("UPDATE {$this->getTable('sales/order_status')} SET label = 'Payment Review' WHERE status = 'manual_review';");
$installer->run("UPDATE {$this->getTable('sales/order_status')} SET label = 'Cancelled' WHERE status = 'denied';");

$installer->endSetup();
