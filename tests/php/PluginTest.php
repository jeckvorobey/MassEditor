<?php

use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    public function testBackendMenuReturnsExpectedMarkup(): void
    {
        $GLOBALS['fake_wa_system'] = new FakeWaSystem();
        $GLOBALS['fake_wa_system']->app_url = '/backend/shop/';
        $plugin = new shopMasseditorPlugin();

        $menu = $plugin->backendMenu();

        $this->assertArrayHasKey('core_li', $menu);
        $this->assertStringContainsString('/backend/shop/?plugin=masseditor', $menu['core_li']);
        $this->assertStringContainsString('Mass Editor', $menu['core_li']);
    }
}
