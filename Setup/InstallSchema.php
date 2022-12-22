<?php

namespace Openpay\Cards\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class InstallSchema implements InstallSchemaInterface {
    
    protected $logger;

    public function __construct(\Openpay\Cards\Logger\Logger $logger_interface) {
        $this->logger = $logger_interface;
    }

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context) {
        $this->logger->debug('#InstallSchema', array('version' => $context->getVersion()));          
        
        $installer = $setup;

        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.5.0', '<')) {
            if (!$installer->tableExists('openpay_customers')) {
                $table = $installer->getConnection()->newTable(
                    $installer->getTable('openpay_customers')
                )
                    ->addColumn(
                        'openpay_customer_id', \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER, null, [
                            'identity' => true,
                            'nullable' => false,
                            'primary' => true,
                            'unsigned' => true,
                        ], 'Openpay Customer ID'
                    )
                    ->addColumn(
                            'customer_id', 
                            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                            11, 
                            ['unsigned' => true, 'nullable' => false], 
                            'Customer ID'
                    )
                    ->addColumn(
                            'openpay_id', 
                            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 
                            50, 
                            ['nullable' => false], 
                            'Openpay ID'
                    )                    
                    ->addColumn(
                            'cards', 
                            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 
                            '64k', 
                            [], 
                            'Card tokens'
                    )                    
                    ->addColumn(
                            'created_at', 
                            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP, 
                            null, 
                            ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT], 
                            'Created At'
                    )->addColumn(
                            'updated_at', 
                            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP, 
                            null, 
                            ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
                            'Updated At'
                    )->setComment('Openpay Customers Table');
                
                $installer->getConnection()->createTable($table);
                
                $installer->getConnection()->addIndex(
                    $installer->getTable('openpay_customers'),    
                    $installer->getIdxName(
                        $installer->getTable('openpay_customers'),
                        ['customer_id']
                    ),
                    ['customer_id']
                );
                                
                $installer->getConnection()->addForeignKey(
                    $installer->getFkName('openpay_customers', 'customer_id', 'customer_entity', 'entity_id'),
                    $installer->getTable('openpay_customers'),
                    'customer_id',
                    $installer->getTable('customer_entity'),
                    'entity_id',
                    \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
                );                
            }
        }

        $installer->endSetup();
    }

}
