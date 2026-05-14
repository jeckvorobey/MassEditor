<?php

use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    public function testBackendMenuReturnsLocalizedMarkup(): void
    {
        $GLOBALS['fake_wa_system'] = new FakeWaSystem();
        $GLOBALS['fake_wa_system']->app_url = '/backend/shop/';
        $plugin = new shopMasseditorPlugin(array());

        $GLOBALS['fake_wa_system']->locale = 'ru_RU';
        $menu = $plugin->backendMenu();

        $this->assertArrayHasKey('core_li', $menu);
        $this->assertStringContainsString('/backend/shop/?plugin=masseditor', $menu['core_li']);
        $this->assertStringContainsString('Массовый редактор', $menu['core_li']);

        $GLOBALS['fake_wa_system']->locale = 'en_US';
        $menu = $plugin->backendMenu();
        $this->assertStringContainsString('Mass Editor', $menu['core_li']);
    }

    public function testPluginNameAndDescriptionFollowResolvedLanguage(): void
    {
        $GLOBALS['fake_wa_system'] = new FakeWaSystem();
        $plugin = new shopMasseditorPlugin(array());

        $GLOBALS['fake_wa_system']->locale = 'ru_RU';
        $this->assertSame('Массовый редактор', $plugin->getName());
        $this->assertSame('Backend-плагин для безопасного массового редактирования товаров.', $plugin->getDescription());
        $this->assertStringNotContainsString('русским и английским интерфейсом', $plugin->getDescription());

        $GLOBALS['fake_wa_system']->locale = 'en_US';
        $this->assertSame('Mass Editor', $plugin->getName());
        $this->assertSame('Backend-only plugin for safe bulk product editing.', $plugin->getDescription());
        $this->assertStringNotContainsString('Russian and English interface', $plugin->getDescription());
    }

    public function testPluginSettingsDefaultHideSoonOperations(): void
    {
        $settings_source = file_get_contents(__DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/config/settings.php');

        $this->assertStringContainsString("'show_soon_operations' => array(", $settings_source);
        $this->assertStringContainsString("'value' => '0'", $settings_source);
        $this->assertStringContainsString("'interface_language' => array(", $settings_source);
        $this->assertStringContainsString("'value' => ''", $settings_source);
        $this->assertDoesNotMatchRegularExpression("/'interface_language' => array\\([\\s\\S]*array\\('value' => 'auto'/", $settings_source);
    }

    public function testLocaleCatalogsExistForMetadata(): void
    {
        $this->assertFileExists(__DIR__ . '/../../wa-apps/shop/plugins/masseditor/locale/ru_RU/LC_MESSAGES/shop_masseditor.po');
        $this->assertFileExists(__DIR__ . '/../../wa-apps/shop/plugins/masseditor/locale/en_US/LC_MESSAGES/shop_masseditor.po');
    }
}
