<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';

class MassOperationServiceTest extends TestCase
{
    use InvokesPrivateMethods;

    protected function setUp(): void
    {
        waRequest::reset();
        waContact::$names = array();
        shopProduct::reset();
        shopProductTagsModel::reset();
        shopHelper::reset();
        $GLOBALS['fake_wa_system'] = new FakeWaSystem();
    }

    public function testApplyRejectsEmptySelection(): void
    {
        $service = new shopMasseditorPluginMassOperationService(
            new FakeSelectionService(),
            new FakeLogService(),
            new waModel(),
            100
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Выберите хотя бы один товар.');

        $service->apply(array(
            'product_ids' => array(),
            'operation' => 'price',
            'numeric_value' => '100',
            'confirm_apply' => 1,
        ));
    }

    public function testApplyRejectsTooManyProducts(): void
    {
        $service = new shopMasseditorPluginMassOperationService(
            new FakeSelectionService(),
            new FakeLogService(),
            new waModel(),
            2
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('не более 2 товаров');

        $service->apply(array(
            'product_ids' => array(1, 2, 3),
            'operation' => 'price',
            'numeric_value' => '100',
            'confirm_apply' => 1,
        ));
    }

    public function testApplyRequiresConfirmation(): void
    {
        $service = new shopMasseditorPluginMassOperationService(
            new FakeSelectionService(),
            new FakeLogService(),
            new waModel(),
            100
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Подтвердите применение изменений.');

        $service->apply(array(
            'product_ids' => array(1),
            'operation' => 'visibility',
            'visibility_status' => 1,
        ));
    }

    public function testApplyRejectsWhenUserHasNoAdminRights(): void
    {
        $GLOBALS['fake_wa_system']->user = new FakeUser(7, false);
        $service = new shopMasseditorPluginMassOperationService(
            new FakeSelectionService(),
            new FakeLogService(),
            new waModel(),
            100
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Недостаточно прав');

        $service->apply(array(
            'product_ids' => array(1),
            'operation' => 'visibility',
            'visibility_status' => 1,
            'confirm_apply' => 1,
        ));
    }

    public function testApplyCommitsVisibilityOperationAndWritesLog(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            11 => array('id' => 11, 'name' => 'Product 11'),
            12 => array('id' => 12, 'name' => 'Product 12'),
        );
        $log = new FakeLogService();
        $model = new waModel();

        $service = new shopMasseditorPluginMassOperationService($selection, $log, $model, 100);
        $result = $service->apply(array(
            'product_ids' => array(11, 12),
            'operation' => 'visibility',
            'visibility_status' => 0,
            'confirm_apply' => 1,
        ));

        $this->assertSame('Операция успешно применена.', $result['message']);
        $this->assertSame('Изменить видимость · 2 товаров', $result['summary']);
        $this->assertCount(4, $model->execs);
        $this->assertStringContainsString('START TRANSACTION', $model->execs[0]['sql']);
        $this->assertStringContainsString('COMMIT', $model->execs[3]['sql']);
        $this->assertCount(1, $log->logged);
        $this->assertSame('visibility', $log->logged[0]['action_type']);
    }

    public function testApplyRollsBackOnProductMismatch(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            11 => array('id' => 11, 'name' => 'Product 11'),
        );
        $model = new waModel();

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);

        try {
            $service->apply(array(
                'product_ids' => array(11, 12),
                'operation' => 'visibility',
                'visibility_status' => 1,
                'confirm_apply' => 1,
            ));
            $this->fail('Exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('не найдена', $e->getMessage());
        }

        $this->assertCount(0, $model->execs);
    }

    public function testApplyPriceOperationUpdatesSkusAndComparePrice(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            11 => array('id' => 11, 'name' => 'Test Product'),
        );
        $log = new FakeLogService();
        $model = new waModel();
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'price' => 100, 'compare_price' => 150, 'available' => 1),
            array('id' => 102, 'product_id' => 11, 'price' => 50, 'compare_price' => 75, 'available' => 1),
        )));
        shopProduct::seed(11, array('name' => 'Test Product'));

        $service = new shopMasseditorPluginMassOperationService($selection, $log, $model, 100);
        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'price',
            'mode' => 'percent',
            'numeric_value' => '10',
            'round_step' => '10',
            'round_direction' => 'up',
            'compare_price_mode' => 'coefficient',
            'compare_price_value' => '2',
            'confirm_apply' => 1,
        ));

        $saved = shopProduct::$saved[11];
        $this->assertSame(110.0, $saved['skus'][101]['price']);
        $this->assertSame(220.0, $saved['skus'][101]['compare_price']);
        $this->assertSame(60.0, $saved['skus'][102]['price']);
        $this->assertSame(120.0, $saved['skus'][102]['compare_price']);
    }

    public function testApplyDescriptionTagsAndUrlBranches(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            15 => array('id' => 15, 'name' => 'Fancy Product', 'url' => 'old-url'),
        );
        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), new waModel(), 100);

        shopProduct::seed(15, array('description' => 'World', 'url' => 'old-url'));
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'description',
            'description_mode' => 'prepend',
            'text_value' => 'Hello ',
            'confirm_apply' => 1,
        ));

        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'tags',
            'tags_mode' => 'add',
            'tags_value' => 'red, blue',
            'confirm_apply' => 1,
        ));
        $this->assertSame('addTags', shopProductTagsModel::$calls[0]['method']);
        $this->assertSame(array('red', 'blue'), shopProductTagsModel::$calls[0]['tags']);

        shopHelper::$used_urls = array('fancy-product');
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'url',
            'url_mode' => 'regenerate',
            'confirm_apply' => 1,
        ));
        $this->assertSame('fancy-product_1', shopProduct::$saved[15]['url']);
    }

    public function testNormalizeHelpersAndDescriptions(): void
    {
        $service = new shopMasseditorPluginMassOperationService(
            new FakeSelectionService(),
            new FakeLogService(),
            new waModel(),
            100
        );

        $this->assertSame(55.0, $this->invokePrivate($service, 'calculateNumericValue', array(50, 'percent', 10)));
        $this->assertSame(60.0, $this->invokePrivate($service, 'applyRounding', array(55, '10', 'up')));
        $this->assertSame(array('red', 'blue'), $this->invokePrivate($service, 'normalizeTagList', array("red,\nblue, red")));
        $this->assertSame('100', $this->invokePrivate($service, 'normalizeRoundStep', array('100')));
        $this->assertSame('nearest', $this->invokePrivate($service, 'normalizeRoundDirection', array('weird')));
        $this->assertSame('keep', $this->invokePrivate($service, 'normalizeComparePriceMode', array('oops')));
        $this->assertSame('replace', $this->invokePrivate($service, 'normalizeDescriptionMode', array('oops')));
        $this->assertSame('add', $this->invokePrivate($service, 'normalizeTagsMode', array('oops')));
        $this->assertSame('regenerate', $this->invokePrivate($service, 'normalizeUrlMode', array('oops')));
        $this->assertSame('2.5', $this->invokePrivate($service, 'formatNumber', array(2.5000)));
        $this->assertSame('Видимость: Скрыт для 2 товаров', $this->invokePrivate($service, 'buildDescription', array(
            array('operation' => 'visibility', 'visibility_status' => 0),
            2,
        )));
    }

    public function testSuggestUniqueProductUrlThrowsAfterRetries(): void
    {
        $service = new shopMasseditorPluginMassOperationService(
            new FakeSelectionService(),
            new FakeLogService(),
            new waModel(),
            100
        );

        shopHelper::$used_urls = array();
        for ($i = 0; $i <= 20; $i++) {
            shopHelper::$used_urls[] = 'name' . ($i > 0 ? '_' . $i : '');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Не удалось подобрать уникальный URL');

        $this->invokePrivate($service, 'suggestUniqueProductUrl', array('name', 99));
    }
}
