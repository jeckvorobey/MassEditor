<?php

use PHPUnit\Framework\TestCase;

if (!class_exists('waHtmlControl', false)) {
    class waHtmlControl
    {
        const INPUT = 'input';
        const SELECT = 'select';
        const CHECKBOX = 'checkbox';
    }
}

class PluginTest extends TestCase
{
    public function testShopSupportJsonDeclaresLocalizedPremiumDescriptions(): void
    {
        $path = __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/config/shop_support.json';
        $support = json_decode(file_get_contents($path), true);

        $this->assertIsArray($support);
        $this->assertSame('yes', $support['support_premium']);
        $this->assertSame(
            'The plugin works in the Shop-Script backend, supports Russian and English interfaces, and does not change the storefront design, cart, units of measurement, or order logic.',
            $support['support_premium_description']
        );
        $this->assertSame(
            'Плагин работает в бекенде Shop-Script, поддерживает русский и английский интерфейс и не изменяет оформление витрины, корзину, единицы измерения и логику заказов.',
            $support['support_premium_description_ru_RU']
        );
        $this->assertSame(
            'The plugin works in the Shop-Script backend, supports Russian and English interfaces, and does not change the storefront design, cart, units of measurement, or order logic.',
            $support['support_premium_description_en_US']
        );
    }

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

    public function testPluginSettingsTitlesAndDescriptionsAreLocalized(): void
    {
        $GLOBALS['fake_wa_system'] = new FakeWaSystem();
        $settings_path = __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/config/settings.php';

        $GLOBALS['fake_wa_system']->locale = 'ru_RU';
        $settings = require $settings_path;
        $this->assertSame('Размер страницы', $settings['page_size']['title']);
        $this->assertSame('Сколько товаров отображать в таблице.', $settings['page_size']['description']);
        $this->assertSame('Режим темы', $settings['theme_mode']['title']);
        $this->assertSame('Авто', $settings['theme_mode']['options'][0]['title']);
        $this->assertSame('Русский', $settings['interface_language']['options'][0]['title']);
        $this->assertSame('Английский', $settings['interface_language']['options'][1]['title']);

        $GLOBALS['fake_wa_system']->locale = 'en_US';
        $settings = require $settings_path;
        $this->assertSame('Page size', $settings['page_size']['title']);
        $this->assertSame('How many products to show in the table.', $settings['page_size']['description']);
        $this->assertSame('Theme mode', $settings['theme_mode']['title']);
        $this->assertSame('Auto', $settings['theme_mode']['options'][0]['title']);
        $this->assertSame('Russian', $settings['interface_language']['options'][0]['title']);
        $this->assertSame('English', $settings['interface_language']['options'][1]['title']);
    }

    public function testLocaleCatalogsExistForMetadata(): void
    {
        $this->assertFileExists(__DIR__ . '/../../wa-apps/shop/plugins/masseditor/locale/ru_RU/LC_MESSAGES/shop_masseditor.po');
        $this->assertFileExists(__DIR__ . '/../../wa-apps/shop/plugins/masseditor/locale/en_US/LC_MESSAGES/shop_masseditor.po');
    }
}
