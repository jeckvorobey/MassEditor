<?php

use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    public function testBackendMenuReturnsExpectedMarkup(): void
    {
        $GLOBALS['fake_wa_system'] = new FakeWaSystem();
        $GLOBALS['fake_wa_system']->app_url = '/backend/shop/';
        $plugin = new shopMasseditorproductPlugin();

        $menu = $plugin->backendMenu();

        $this->assertArrayHasKey('core_li', $menu);
        $this->assertStringContainsString('/backend/shop/?plugin=masseditorproduct', $menu['core_li']);
        $this->assertStringContainsString('Mass Editor Product', $menu['core_li']);
    }
}
