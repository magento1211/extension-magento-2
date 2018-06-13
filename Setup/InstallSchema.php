<?php

namespace Emartech\Emarsys\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class InstallSchema implements InstallSchemaInterface
{
  public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
  {
    $setup->startSetup();

    $this->createEmarsysSettingsTable($setup);
    $this->createEmarsysEventsTable($setup);

    $setup->endSetup();
  }

  /**
   * @param SchemaSetupInterface $setup
   * @throws \Zend_Db_Exception
   */
  private function createEmarsysEventsTable(SchemaSetupInterface $setup)
  {
    $tableName = $setup->getTable('emarsys_events');
    if ($setup->getConnection()->isTableExists($tableName) != true) {
      $table = $setup->getConnection()->newTable(
        $setup->getTable('emarsys_events'))
        ->addColumn(
          'event_id',
          \Magento\Framework\DB\Ddl\Table::TYPE_BIGINT,
          null,
          ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
          'Event Id'
        )
        ->addColumn(
          'event_type',
          \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
          255,
          ['default' => null, 'nullable' => false],
          'Event Type'
        )
        ->addColumn(
          'event_data',
          \Magento\Framework\DB\Ddl\Table::TYPE_BLOB,
          null,
          ['default' => null, 'nullable' => false],
          'Event Data'
        )
        ->addColumn(
          'created_at',
          \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
          null,
          ['default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT, 'nullable' => false],
          'Timestamp'
        )
        ->addIndex(
          $setup->getIdxName(
            $setup->getTable('emarsys_events'),
            ['event_type'],
            \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX
          ),
          ['event_type'],
          ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX]
        )
        ->addIndex(
          $setup->getIdxName(
            $setup->getTable('emarsys_events'),
            ['created_at'],
            \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX
          ),
          ['created_at'],
          ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX]
        );
      $setup->getConnection()->createTable($table);
    }
  }

  /**
   * @param SchemaSetupInterface $setup
   * @throws \Zend_Db_Exception
   */
  private function createEmarsysSettingsTable(SchemaSetupInterface $setup)
  {
    $tableName = $setup->getTable('emarsys_settings');
    if ($setup->getConnection()->isTableExists($tableName) != true) {
      $table = $setup->getConnection()->newTable(
        $setup->getTable('emarsys_settings'))
        ->addColumn(
          'setting_id',
          \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
          null,
          ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
          'Id'
        )
        ->addColumn(
          'setting',
          \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
          255,
          ['default' => null, 'nullable' => false],
          'Setting'
        )
        ->addColumn(
          'value',
          \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
          null,
          ['default' => null, 'nullable' => false],
          'Value'
        )
        ->addIndex(
          $setup->getIdxName(
            $setup->getTable('emarsys_settings'),
            ['setting'],
            \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
          ),
          ['setting'],
          ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
        );
      $setup->getConnection()->createTable($table);
    }
  }
}