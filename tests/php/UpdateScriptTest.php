<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';

class UpdateScriptTest extends TestCase
{
    public function testVersion120MetaUpdateIsTimestampedAndIdempotent(): void
    {
        $cancelled_plugin_id = 'masseditor' . 'product';
        $update_files = glob(
            __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/updates/1.2.0/*.php'
        );

        $this->assertCount(2, $update_files);
        $update_file = $this->findUpdateContaining($update_files, 'shop_masseditor_log');
        $this->assertMatchesRegularExpression('/\/\d+\.php$/', $update_file);

        waModel::$global_execs = array();
        include $update_file;
        include $update_file;

        $this->assertCount(2, waModel::$global_execs);
        foreach (waModel::$global_execs as $exec) {
            $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS shop_masseditor_log', $exec['sql']);
            $this->assertStringNotContainsString('DROP ', strtoupper($exec['sql']));
            $this->assertStringNotContainsString('masseditor_migrations', $exec['sql']);
            $this->assertStringNotContainsString($cancelled_plugin_id, $exec['sql']);
        }
    }

    public function testVersionAndCancelledPluginIdContract(): void
    {
        $cancelled_plugin_id = 'masseditor' . 'product';
        $plugin_config = require __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/config/plugin.php';
        $entrypoint = file_get_contents(__DIR__ . '/../../docker/php/entrypoint.sh');

        $this->assertSame('1.2.0', $plugin_config['version']);
        $this->assertStringNotContainsString($cancelled_plugin_id, $entrypoint);
    }

    /**
     * Given an existing 1.1.0 installation without rollback snapshot tables.
     * When the 1.2.0 rollback meta-update is executed more than once.
     * Then both private rollback tables are created idempotently without destructive SQL.
     */
    public function testVersion120MetaUpdateCreatesRollbackTablesIdempotently(): void
    {
        $update_files = glob(
            __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/updates/1.2.0/*.php'
        );

        $this->assertCount(2, $update_files);
        $update_file = $this->findUpdateContaining($update_files, 'shop_masseditor_rollback');
        $this->assertMatchesRegularExpression('/\/\d+\.php$/', $update_file);

        waModel::$global_execs = array();
        include $update_file;
        include $update_file;

        $this->assertCount(4, waModel::$global_execs);
        $sql = implode("\n", array_column(waModel::$global_execs, 'sql'));
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS shop_masseditor_rollback ', $sql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS shop_masseditor_rollback_item ', $sql);
        $this->assertStringContainsString('UNIQUE KEY log_id (log_id)', $sql);
        $this->assertStringContainsString('PRIMARY KEY (rollback_id, product_id)', $sql);
        $this->assertStringNotContainsString('DROP ', strtoupper($sql));
    }

    /**
     * Given a clean plugin installation.
     * When Webasyst reads db.php.
     * Then the installation schema contains the same private rollback tables and indexes.
     */
    public function testInstallSchemaContainsPrivateRollbackTables(): void
    {
        $schema = require __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/config/db.php';

        $this->assertArrayHasKey('shop_masseditor_rollback', $schema);
        $this->assertArrayHasKey('shop_masseditor_rollback_item', $schema);
        $this->assertSame('log_id', $schema['shop_masseditor_rollback'][':keys']['log_id']['fields']);
        $this->assertTrue($schema['shop_masseditor_rollback'][':keys']['log_id']['unique']);
        $this->assertSame(
            array('rollback_id', 'product_id'),
            $schema['shop_masseditor_rollback_item'][':keys']['PRIMARY']
        );
        $this->assertSame('mediumtext', $schema['shop_masseditor_rollback_item']['before_state'][0]);
        $this->assertSame('mediumtext', $schema['shop_masseditor_rollback_item']['after_state'][0]);
    }

    /**
     * @param string[] $update_files
     */
    private function findUpdateContaining(array $update_files, string $needle): string
    {
        foreach ($update_files as $update_file) {
            if (strpos((string) file_get_contents($update_file), $needle) !== false) {
                return $update_file;
            }
        }

        $this->fail(sprintf('No meta-update contains %s.', $needle));
    }
}
