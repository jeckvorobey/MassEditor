<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';

class BackendActionTest extends TestCase
{
    use InvokesPrivateMethods;

    protected function setUp(): void
    {
        waRequest::reset();
        waLog::reset();
        waContact::$names = array(5 => 'Alice Admin');
        $plugin = new shopMasseditorPlugin(array(
            'date_format' => 'd.m.Y H:i',
            'page_size' => 50,
            'operation_limit' => 100,
            'log_retention_days' => 90,
            'theme_mode' => 'auto',
            'show_soon_operations' => 0,
        ));
        $GLOBALS['fake_wa_system'] = new FakeWaSystem();
        $GLOBALS['fake_wa_system']->plugins['masseditor'] = $plugin;
    }

    public function testExecuteRejectsNonAdminBeforeReadingBackendData(): void
    {
        $GLOBALS['fake_wa_system']->user = new FakeUser(9, false);
        $action = new shopMasseditorPluginBackendAction();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Недостаточно прав');

        $action->execute();
    }

    public function testSearchSuggestionsControllerRejectsNonAdmin(): void
    {
        $GLOBALS['fake_wa_system']->user = new FakeUser(9, false);
        $controller = new shopMasseditorPluginBackendSearchSuggestionsController();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Недостаточно прав');

        $controller->execute();
    }

    public function testSearchSuggestionsControllerNormalizesInputAndReturnsSuggestions(): void
    {
        waRequest::$get = array(
            'query' => '  sku  ',
            'status' => 'published',
            'availability' => 'available',
            'category_id' => 4,
        );
        $selection_service = new ControllerFakeProductSelectionService();
        $selection_service->suggestions = array('SKU-1', 'Summer dress');
        $controller = new shopMasseditorPluginBackendSearchSuggestionsController($selection_service);

        $controller->execute();

        $this->assertSame(array('suggestions' => array('SKU-1', 'Summer dress')), $controller->response);
        $this->assertSame('sku', $selection_service->lastQuery);
        $this->assertSame(array(
            'status' => 'published',
            'availability' => 'available',
            'category_id' => 4,
        ), $selection_service->lastFilters);
        $this->assertSame(10, $selection_service->lastLimit);
    }

    public function testSearchSuggestionsControllerSkipsShortInput(): void
    {
        waRequest::$get = array('query' => 's');
        $selection_service = new ControllerFakeProductSelectionService();
        $controller = new shopMasseditorPluginBackendSearchSuggestionsController($selection_service);

        $controller->execute();

        $this->assertSame(array('suggestions' => array()), $controller->response);
        $this->assertNull($selection_service->lastQuery);
    }

    public function testUnexpectedPostExceptionIsLoggedAndHiddenFromUi(): void
    {
        $GLOBALS['fake_wa_system']->plugins['masseditor'] = new ThrowingSettingsShopMasseditorPlugin(array(
            'date_format' => 'd.m.Y H:i',
            'page_size' => 50,
            'operation_limit' => 100,
            'log_retention_days' => 90,
            'theme_mode' => 'auto',
            'show_soon_operations' => 0,
        ));
        waRequest::$method = 'post';
        waRequest::$post = array(
            'save_settings' => 1,
            'page_size' => 50,
            'operation_limit' => 100,
            'log_retention_days' => 90,
            'date_format' => 'd.m.Y H:i',
            'theme_mode' => 'auto',
        );

        $action = new shopMasseditorPluginBackendAction();
        $action->execute();

        $this->assertSame(array('Операцию не удалось выполнить. Повторите действие или проверьте журнал ошибок.'), $action->view->assigned['errors']);
        $this->assertStringNotContainsString('SQLSTATE', $action->view->assigned['errors'][0]);
        $this->assertSame('settings', $action->view->assigned['active_tab']);
        $this->assertCount(1, waLog::$logs);
        $this->assertStringContainsString('SQLSTATE', waLog::$logs[0]['message']);
        $this->assertSame('shop/plugins/masseditor.log', waLog::$logs[0]['file']);
    }

    public function testBuildPaginationUiCreatesEllipsisAndBounds(): void
    {
        $action = new shopMasseditorPluginBackendAction();
        $ui = $this->invokePrivate($action, 'buildPaginationUi', array(array(
            'page' => 5,
            'pages' => 10,
            'page_size' => 50,
            'total' => 475,
        )));

        $this->assertSame(201, $ui['from']);
        $this->assertSame(250, $ui['to']);
        $this->assertTrue($ui['has_prev']);
        $this->assertTrue($ui['has_next']);
        $this->assertContains(array('type' => 'ellipsis'), $ui['items']);
    }

    public function testFormattingHelpersNormalizeValues(): void
    {
        $action = new shopMasseditorPluginBackendAction();

        $this->assertSame('10.5', $this->invokePrivate($action, 'formatDecimalForView', array('10.5000')));
        $this->assertSame('10.50', $this->invokePrivate($action, 'formatDecimalForView', array('10.5', '2')));
        $this->assertSame('-0', $this->invokePrivate($action, 'formatDecimalForView', array('-0')));
        $this->assertSame('26.04.2026 14:30', $this->invokePrivate($action, 'formatDateForView', array('2026-04-26 14:30:00', 'd.m.Y H:i')));
    }

    public function testDecorateProductsFormatsStockForTableView(): void
    {
        $action = new shopMasseditorPluginBackendAction();

        $products = $this->invokePrivate($action, 'decorateProducts', array(array(
            array(
                'count' => '12.000',
                'price' => '10.000',
                'compare_price' => '',
                'description' => '',
                'edit_datetime' => '2026-04-26 14:30:00',
                'stock_details' => array(
                    array('stock_id' => 3, 'stock_name' => 'Main', 'count' => '5.000'),
                    array('stock_id' => 4, 'stock_name' => 'Reserve', 'count' => null),
                ),
            ),
            array(
                'count' => '1.250',
                'price' => '10.000',
                'compare_price' => '',
                'description' => '',
                'edit_datetime' => '2026-04-26 14:30:00',
            ),
        )));

        $this->assertSame('12', $products[0]['count_view']);
        $this->assertSame('1.25', $products[1]['count_view']);
        $this->assertSame('5', $products[0]['stock_details'][0]['count_view']);
        $this->assertSame('∞', $products[0]['stock_details'][1]['count_view']);
    }

    public function testSelectedIdsAndOperationFormMerge(): void
    {
        $action = new shopMasseditorPluginBackendAction();

        $this->assertSame(array(2, 3), $this->invokePrivate($action, 'normalizeSelectedProductIds', array(array('2', '3', '0', '2'))));
        $this->assertSame(array('operation' => 'tags', 'mode' => 'set'), $this->invokePrivate($action, 'mergeOperationForm', array(
            array('operation' => 'price', 'mode' => 'set'),
            array('operation' => 'tags', 'other' => 'ignored'),
        )));
        $this->assertSame('Остатки · 2 товара · Операция выполнена.', $this->invokePrivate($action, 'formatResultMessage', array(array(
            'summary' => 'Остатки · 2 товара',
            'message' => 'Операция выполнена.',
        ))));
    }

    public function testDecorateLogsSettingsAndOperationsLibrary(): void
    {
        $action = new shopMasseditorPluginBackendAction();
        $logs = $this->invokePrivate($action, 'decorateLogs', array(array(
            array('action_type' => 'price', 'user_id' => 5, 'created_at' => '2026-04-26 14:30:00'),
            array('action_type' => 'unknown', 'user_id' => 99, 'created_at' => 'broken'),
        )));

        $this->assertSame('Цена', $logs[0]['action_label']);
        $this->assertSame('Alice Admin', $logs[0]['user_name']);
        $this->assertSame('26.04.2026 14:30', $logs[0]['created_at_view']);
        $this->assertNull($logs[1]['user_name']);
        $this->assertSame('broken', $logs[1]['created_at_view']);

        $options = $this->invokePrivate($action, 'getDateFormatOptions');
        $this->assertArrayHasKey('Y-m-d H:i', $options);

        $library = $this->invokePrivate($action, 'getOperationsLibrary', array(0));
        $flat_ids = array();
        foreach ($library as $group) {
            foreach ($group['items'] as $item) {
                $flat_ids[] = $item['id'];
            }
        }
        $this->assertContains('price', $flat_ids);
        $this->assertNotContains('sku_generator', $flat_ids);
    }

    public function testExecuteAssignsLocalizedPluginSurfacesInRussian(): void
    {
        $GLOBALS['fake_wa_system']->locale = 'ru_RU';

        $action = new shopMasseditorPluginBackendAction();
        $action->execute();

        $this->assertSame('Массовый редактор', $action->view->assigned['page_title']);
        $this->assertSame('Массовый редактор', $action->view->assigned['plugin_name']);
        $this->assertSame('Разделы массового редактора', $action->view->assigned['texts']['tabs_aria_label']);
        $this->assertSame('Цена сравнения', $action->view->assigned['texts']['compare_price']);
    }

    public function testManualEnglishLanguageControlsHeaderWhenWebasystLocaleIsRussian(): void
    {
        $GLOBALS['fake_wa_system']->locale = 'ru_RU';
        $GLOBALS['fake_wa_system']->plugins['masseditor']->saveSettings(array(
            'interface_language' => 'en_US',
        ));

        $action = new shopMasseditorPluginBackendAction();
        $action->execute();

        $this->assertSame('Массовый редактор', $action->view->assigned['page_title']);
        $this->assertSame('Mass Editor', $action->view->assigned['plugin_name']);
        $this->assertSame('Bulk product editing.', $action->view->assigned['texts']['subtitle']);
    }

    public function testPluginSettingsNormalizationAndThemeMode(): void
    {
        $action = new shopMasseditorPluginBackendAction();
        $plugin = $GLOBALS['fake_wa_system']->plugins['masseditor'];
        $settings = $this->invokePrivate($action, 'getPluginSettings', array($plugin));

        $this->assertSame(50, $settings['page_size']);
        $this->assertSame('auto', $settings['theme_mode']);
        $this->assertSame(0, $settings['show_soon_operations']);
        $this->assertSame('ru_RU', $settings['interface_language']);
        $this->assertSame('', $settings['interface_language_setting']);
        $this->assertSame('auto', $this->invokePrivate($action, 'normalizeThemeMode', array('broken')));
        $this->assertSame('d.m.Y H:i', $this->invokePrivate($action, 'normalizeDateFormat', array('wrong')));
        $this->assertSame(200, $this->invokePrivate($action, 'normalizeIntSetting', array(999, 50, 10, 200)));
    }

    public function testWebasystLocaleControlsEnglishTexts(): void
    {
        $action = new shopMasseditorPluginBackendAction();
        $plugin = $GLOBALS['fake_wa_system']->plugins['masseditor'];

        $GLOBALS['fake_wa_system']->locale = 'en_US';
        $settings = $this->invokePrivate($action, 'getPluginSettings', array($plugin));
        $this->assertSame('auto', $settings['theme_mode']);
        $this->assertSame('Products found', shopMasseditorPluginI18nService::t('stats_found'));

        waRequest::$post = array(
            'page_size' => 50,
            'operation_limit' => 100,
            'log_retention_days' => 90,
            'date_format' => 'd.m.Y H:i',
            'theme_mode' => 'auto',
        );

        $settings = $this->invokePrivate($action, 'savePluginSettings', array($plugin, $settings));
        $this->assertSame(50, $settings['page_size']);

        $library = $this->invokePrivate($action, 'getOperationsLibrary', array(0));
        $this->assertSame('Prices and SKU', $library[0]['title']);
        $this->assertSame('Change price', $library[0]['items'][0]['label']);
$soon_library = $this->invokePrivate($action, 'getOperationsLibrary', array(1));
        $this->assertSame('Stock', $soon_library[0]['items'][2]['label']);
        $this->assertSame('SKU generator', $soon_library[0]['items'][3]['label']);
    }

    public function testManualLanguageSettingOverridesWebasystLocaleWithoutAutoOption(): void
    {
        $action = new shopMasseditorPluginBackendAction();
        $plugin = $GLOBALS['fake_wa_system']->plugins['masseditor'];
        $GLOBALS['fake_wa_system']->locale = 'ru_RU';

        waRequest::$post = array(
            'page_size' => 50,
            'operation_limit' => 100,
            'log_retention_days' => 90,
            'date_format' => 'd.m.Y H:i',
            'theme_mode' => 'auto',
            'interface_language' => 'en_US',
        );

        $settings = $this->invokePrivate($action, 'getPluginSettings', array($plugin));
        $settings = $this->invokePrivate($action, 'savePluginSettings', array($plugin, $settings));

        $this->assertSame('en_US', $settings['interface_language']);
        $this->assertSame('en_US', $settings['interface_language_setting']);
        $this->assertSame('Products found', shopMasseditorPluginI18nService::t('stats_found', $settings['interface_language']));
        $this->assertSame(array('ru_RU' => 'Russian', 'en_US' => 'English'), shopMasseditorPluginI18nService::getLanguageOptions($settings['interface_language']));

        $library = $this->invokePrivate($action, 'getOperationsLibrary', array(0, $settings['interface_language']));
        $this->assertSame('Prices and SKU', $library[0]['title']);

        $GLOBALS['fake_wa_system']->locale = 'en_US';
        $plugin = new shopMasseditorPlugin(array('interface_language' => 'auto'));
        $settings = $this->invokePrivate($action, 'getPluginSettings', array($plugin));
        $this->assertSame('en_US', $settings['interface_language']);
        $this->assertSame('', $settings['interface_language_setting']);
    }

    public function testTemplateUsesLocalizedCompareAndPrimaryFilterButton(): void
    {
        $action = new shopMasseditorPluginBackendAction();
        $action->execute();
        $template = file_get_contents(__DIR__ . '/../../wa-apps/shop/plugins/masseditor/templates/actions/backend/Backend.html');

        $this->assertStringContainsString('<h1>{$plugin_name|escape}</h1>', $template);
        $this->assertStringContainsString('aria-label="{$texts.tabs_aria_label|escape}"', $template);
        $this->assertStringContainsString('<th>{$texts.compare_price|escape}</th>', $template);
        $this->assertStringContainsString('class="button masseditor-button masseditor-button_primary" type="submit" form="masseditor-filter-form"', $template);
        $this->assertStringContainsString('name="interface_language"', $template);
        $this->assertStringContainsString('{if $product.count === null}∞{else}{$product.count_view|escape}{/if}', $template);
        $this->assertStringNotContainsString('value="auto"{if $settings.interface_language', $template);
        $this->assertSame('Установить значение', $action->view->assigned['texts']['set_value']);
        $this->assertSame('Добавить значение', $action->view->assigned['texts']['add_value']);
        $this->assertSame('Отнять значение', $action->view->assigned['texts']['subtract_value']);
        $this->assertSame('Увеличить на процент', $action->view->assigned['texts']['increase_percent']);
        $this->assertSame('Убавить на процент', $action->view->assigned['texts']['decrease_percent']);
        $this->assertStringContainsString('<option value="set"{if $operation_form.mode === \'set\'} selected{/if}>{$texts.set_value|escape}</option>', $template);
        $this->assertStringContainsString('<option value="add"{if $operation_form.mode === \'add\'} selected{/if}>{$texts.add_value|escape}</option>', $template);
        $this->assertStringContainsString('<option value="subtract"{if $operation_form.mode === \'subtract\'} selected{/if}>{$texts.subtract_value|escape}</option>', $template);
        $this->assertStringContainsString('<option value="increase_percent"{if $operation_form.mode === \'increase_percent\'} selected{/if}>{$texts.increase_percent|escape}</option>', $template);
        $this->assertStringContainsString('<option value="decrease_percent"{if $operation_form.mode === \'decrease_percent\'} selected{/if}>{$texts.decrease_percent|escape}</option>', $template);
    }
}

class ControllerFakeProductSelectionService extends shopMasseditorPluginProductSelectionService
{
    public $suggestions = array();
    public $lastFilters = null;
    public $lastQuery = null;
    public $lastLimit = null;

    public function __construct()
    {
    }

    public function getSearchSuggestions(array $raw_filters, $query, $limit = 10)
    {
        $this->lastFilters = $raw_filters;
        $this->lastQuery = $query;
        $this->lastLimit = $limit;

        return $this->suggestions;
    }
}

class ThrowingSettingsShopMasseditorPlugin extends shopMasseditorPlugin
{
    public function saveSettings(array $settings)
    {
        throw new RuntimeException('SQLSTATE[42000]: leaked internal SQL error');
    }
}
