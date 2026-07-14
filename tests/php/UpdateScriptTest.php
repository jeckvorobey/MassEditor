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

        $this->assertCount(1, $update_files);
        $this->assertMatchesRegularExpression('/\/\d+\.php$/', $update_files[0]);

        waModel::$global_execs = array();
        include $update_files[0];
        include $update_files[0];

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
}
