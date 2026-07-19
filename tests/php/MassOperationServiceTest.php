<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';

class RecordingRollbackService extends shopMasseditorPluginRollbackService
{
    public $lockAvailable = true;
    public $lockAcquired = 0;
    public $lockReleased = 0;
    public $states = array();
    public $captured = array();
    public $stored = array();
    public $restored = array();
    public $throwOnStore = false;
    public $throwOnRestore = false;

    public function __construct()
    {
    }

    public function acquireMutationLock($timeout = 5)
    {
        $this->lockAcquired++;
        return $this->lockAvailable;
    }

    public function releaseMutationLock()
    {
        $this->lockReleased++;
        return true;
    }

    public function captureState(array $request, array $product_ids)
    {
        $this->captured[] = array('request' => $request, 'product_ids' => $product_ids);
        return array_shift($this->states);
    }

    public function storeSnapshot($log_id, $user_id, $operation, array $before, array $after, $created_at = null)
    {
        if ($this->throwOnStore) {
            throw new RuntimeException('Snapshot store failed');
        }
        $this->stored[] = compact('log_id', 'user_id', 'operation', 'before', 'after', 'created_at');
        return 9;
    }

    public function restoreState(array $state, array $request)
    {
        if ($this->throwOnRestore) {
            throw new RuntimeException('Compensation failed with private state');
        }
        $this->restored[] = array('state' => $state, 'request' => $request);
    }
}

class MassOperationServiceTest extends TestCase
{
    use InvokesPrivateMethods;

    protected function setUp(): void
    {
        waRequest::reset();
        waLog::reset();
        waContact::$names = array();
        shopProduct::reset();
        shopProductModel::reset();
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
        $this->assertSame('Изменить видимость · 2 товара', $result['summary']);
        $this->assertCount(4, $model->execs);
        $this->assertStringContainsString('START TRANSACTION', $model->execs[0]['sql']);
        $this->assertStringContainsString('COMMIT', $model->execs[3]['sql']);
        $this->assertCount(1, $log->logged);
        $this->assertSame('visibility', $log->logged[0]['action_type']);
    }

    public function testApplyCapturesBeforeAndAfterAndStoresSnapshotUnderMutationLock(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'Product 11'));
        $log = new FakeLogService();
        $model = new waModel();
        $rollback = new RecordingRollbackService();
        $before = array(11 => array('version' => 1, 'operation' => 'visibility', 'product_id' => 11, 'data' => array('status' => 1)));
        $after = array(11 => array('version' => 1, 'operation' => 'visibility', 'product_id' => 11, 'data' => array('status' => 0)));
        $rollback->states = array($before, $after);

        $service = new shopMasseditorPluginMassOperationService($selection, $log, $model, 100, null, $rollback);
        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'visibility',
            'visibility_status' => 0,
            'confirm_apply' => 1,
        ));

        $this->assertSame(1, $rollback->lockAcquired);
        $this->assertSame(1, $rollback->lockReleased);
        $this->assertCount(2, $rollback->captured);
        $this->assertSame(array(11), $rollback->captured[0]['product_ids']);
        $this->assertCount(1, $rollback->stored);
        $this->assertSame(1, $rollback->stored[0]['log_id']);
        $this->assertSame(1, $rollback->stored[0]['user_id']);
        $this->assertSame('visibility', $rollback->stored[0]['operation']);
        $this->assertSame($before, $rollback->stored[0]['before']);
        $this->assertSame($after, $rollback->stored[0]['after']);
        $this->assertCount(0, $rollback->restored);
    }

    public function testApplyCompensatesAndDiscardsLogWhenSnapshotStoreFails(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'Product 11'));
        $log = new FakeLogService();
        $model = new waModel();
        $rollback = new RecordingRollbackService();
        $before = array(11 => array('version' => 1, 'operation' => 'visibility', 'product_id' => 11, 'data' => array('status' => 1)));
        $after = array(11 => array('version' => 1, 'operation' => 'visibility', 'product_id' => 11, 'data' => array('status' => 0)));
        $rollback->states = array($before, $after);
        $rollback->throwOnStore = true;

        $service = new shopMasseditorPluginMassOperationService($selection, $log, $model, 100, null, $rollback);

        try {
            $service->apply(array(
                'product_ids' => array(11),
                'operation' => 'visibility',
                'visibility_status' => 0,
                'confirm_apply' => 1,
            ));
            $this->fail('Snapshot failure was not propagated.');
        } catch (RuntimeException $e) {
            $this->assertSame('Snapshot store failed', $e->getMessage());
        }

        $this->assertSame(array(1), $log->discarded);
        $this->assertCount(1, $rollback->restored);
        $this->assertSame($before, $rollback->restored[0]['state']);
        $this->assertSame('ROLLBACK', end($model->execs)['sql']);
        $this->assertSame(1, $rollback->lockReleased);
    }

    public function testApplyRejectsBusyMutationLockBeforeTransaction(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'Product 11'));
        $model = new waModel();
        $rollback = new RecordingRollbackService();
        $rollback->lockAvailable = false;
        $service = new shopMasseditorPluginMassOperationService(
            $selection,
            new FakeLogService(),
            $model,
            100,
            null,
            $rollback
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Операция уже выполняется');

        try {
            $service->apply(array(
                'product_ids' => array(11),
                'operation' => 'visibility',
                'visibility_status' => 0,
                'confirm_apply' => 1,
            ));
        } finally {
            $this->assertCount(0, $model->execs);
            $this->assertSame(0, $rollback->lockReleased);
        }
    }

    public function testApplyLogsCompensationFailureWithoutExposingPrivateState(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'Product 11'));
        $model = new waModel();
        $rollback = new RecordingRollbackService();
        $rollback->states = array(
            array(11 => array('before' => 'private')),
            array(11 => array('after' => 'private')),
        );
        $rollback->throwOnStore = true;
        $rollback->throwOnRestore = true;
        $service = new shopMasseditorPluginMassOperationService(
            $selection,
            new FakeLogService(),
            $model,
            100,
            'ru_RU',
            $rollback
        );

        try {
            $service->apply(array(
                'product_ids' => array(11),
                'operation' => 'visibility',
                'visibility_status' => 0,
                'confirm_apply' => 1,
            ));
            $this->fail('Compensation failure was not propagated.');
        } catch (RuntimeException $e) {
            $this->assertSame('Не удалось восстановить старые значения.', $e->getMessage());
            $this->assertStringNotContainsString('private', $e->getMessage());
        }

        $this->assertCount(1, waLog::$logs);
        $this->assertStringContainsString('Compensation failed with private state', waLog::$logs[0]['message']);
        $this->assertSame('shop/plugins/masseditor.log', waLog::$logs[0]['file']);
        $this->assertSame(1, $rollback->lockReleased);
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

    public function testApplyPriceOperationSupportsAllModesAndCoefficientUsesRecalculatedPrice(): void
    {
        $scenarios = array(
            'set' => array(
                'input' => '10',
                'expected' => array(
                    101 => array('price' => 10.0, 'compare_price' => 20.0),
                    102 => array('price' => 10.0, 'compare_price' => 20.0),
                ),
            ),
            'add' => array(
                'input' => '10',
                'expected' => array(
                    101 => array('price' => 110.0, 'compare_price' => 220.0),
                    102 => array('price' => 60.0, 'compare_price' => 120.0),
                ),
            ),
            'subtract' => array(
                'input' => '10',
                'expected' => array(
                    101 => array('price' => 90.0, 'compare_price' => 180.0),
                    102 => array('price' => 40.0, 'compare_price' => 80.0),
                ),
            ),
            'increase_percent' => array(
                'input' => '10',
                'expected' => array(
                    101 => array('price' => 110.0, 'compare_price' => 220.0),
                    102 => array('price' => 55.0, 'compare_price' => 110.0),
                ),
            ),
            'decrease_percent' => array(
                'input' => '10',
                'expected' => array(
                    101 => array('price' => 90.0, 'compare_price' => 180.0),
                    102 => array('price' => 45.0, 'compare_price' => 90.0),
                ),
            ),
        );

        foreach ($scenarios as $mode => $scenario) {
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
                'mode' => $mode,
                'numeric_value' => $scenario['input'],
                'round_step' => '',
                'round_direction' => 'nearest',
                'compare_price_mode' => 'coefficient',
                'compare_price_value' => '2',
                'confirm_apply' => 1,
            ));

            $saved = shopProduct::$saved[11];
            foreach ($scenario['expected'] as $sku_id => $expected) {
                $this->assertSame($expected['price'], $saved['skus'][$sku_id]['price'], 'Unexpected price for mode ' . $mode . ' and sku ' . $sku_id);
                $this->assertSame($expected['compare_price'], $saved['skus'][$sku_id]['compare_price'], 'Unexpected compare price for mode ' . $mode . ' and sku ' . $sku_id);
            }
        }
    }

    public function testApplyPriceOperationRoundsToDecimalPlaces(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            11 => array('id' => 11, 'name' => 'Test Product'),
        );
        $model = new waModel();
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'price' => 100, 'compare_price' => 150, 'available' => 1),
        )));
        shopProduct::seed(11, array('name' => 'Test Product'));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'price',
            'mode' => 'set',
            'numeric_value' => '10.005',
            'round_step' => '2',
            'round_direction' => 'up',
            'compare_price_mode' => 'keep',
            'confirm_apply' => 1,
        ));

        $saved = shopProduct::$saved[11];
        $this->assertSame(10.01, $saved['skus'][101]['price']);
    }

    public function testApplyComparePriceOperationRoundsToDecimalPlaces(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            11 => array('id' => 11, 'name' => 'Test Product'),
        );
        $model = new waModel();
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'price' => 100, 'compare_price' => 15.555, 'available' => 1),
        )));
        shopProduct::seed(11, array('name' => 'Test Product'));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'compare_price',
            'mode' => 'increase_percent',
            'numeric_value' => '10',
            'round_step' => '2',
            'round_direction' => 'nearest',
            'confirm_apply' => 1,
        ));

        $saved = shopProduct::$saved[11];
        $this->assertSame(17.11, $saved['skus'][101]['compare_price']);
    }

    public function testApplyComparePriceOperationSupportsAllModes(): void
    {
        $scenarios = array(
            'set' => array(101 => 10.0, 102 => 10.0),
            'add' => array(101 => 160.0, 102 => 85.0),
            'subtract' => array(101 => 140.0, 102 => 65.0),
            'increase_percent' => array(101 => 165.0, 102 => 82.5),
            'decrease_percent' => array(101 => 135.0, 102 => 67.5),
        );

        foreach ($scenarios as $mode => $expected_values) {
            $selection = new FakeSelectionService();
            $selection->products = array(
                11 => array('id' => 11, 'name' => 'Test Product'),
            );
            $model = new waModel();
            $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
                array('id' => 101, 'product_id' => 11, 'price' => 100, 'compare_price' => 150, 'available' => 1),
                array('id' => 102, 'product_id' => 11, 'price' => 50, 'compare_price' => 75, 'available' => 1),
            )));
            shopProduct::seed(11, array('name' => 'Test Product'));

            $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
            $service->apply(array(
                'product_ids' => array(11),
                'operation' => 'compare_price',
                'mode' => $mode,
                'numeric_value' => '10',
                'round_step' => '',
                'round_direction' => 'nearest',
                'confirm_apply' => 1,
            ));

            $saved = shopProduct::$saved[11];
            foreach ($expected_values as $sku_id => $expected_value) {
                $this->assertSame($expected_value, $saved['skus'][$sku_id]['compare_price'], 'Unexpected compare price for mode ' . $mode . ' and sku ' . $sku_id);
            }
        }
    }

    public function testApplyPriceOperationRejectsNegativeInputMagnitude(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            11 => array('id' => 11, 'name' => 'Test Product'),
        );

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), new waModel(), 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Укажите корректное числовое значение.');

        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'price',
            'mode' => 'add',
            'numeric_value' => '-10',
            'confirm_apply' => 1,
        ));
    }

    public function testApplyPriceOperationRejectsBelowZeroResult(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            11 => array('id' => 11, 'name' => 'Test Product'),
        );
        $model = new waModel();
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'price' => 100, 'compare_price' => 150, 'available' => 1),
        )));
        shopProduct::seed(11, array('name' => 'Test Product'));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Итоговое значение не может быть отрицательным.');

        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'price',
            'mode' => 'subtract',
            'numeric_value' => '150',
            'confirm_apply' => 1,
        ));
    }

    public function testApplyComparePriceOperationRejectsBelowZeroResult(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            11 => array('id' => 11, 'name' => 'Test Product'),
        );
        $model = new waModel();
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'price' => 100, 'compare_price' => 100, 'available' => 1),
        )));
        shopProduct::seed(11, array('name' => 'Test Product'));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Итоговое значение не может быть отрицательным.');

        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'compare_price',
            'mode' => 'decrease_percent',
            'numeric_value' => '150',
            'confirm_apply' => 1,
        ));
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

    public function testApplyFilterSelectionResolvesIdsOnServerAndRespectsLimit(): void
    {
        $selection = new FakeSelectionService();
        $selection->filterIds = array(11, 12);
        $selection->products = array(
            11 => array('id' => 11, 'name' => 'Product 11'),
            12 => array('id' => 12, 'name' => 'Product 12'),
        );
        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), new waModel(), 2);

        $result = $service->apply(array(
            'selection_mode' => 'filter',
            'filters' => array('query' => 'sku', 'status' => 'published'),
            'operation' => 'visibility',
            'visibility_status' => 1,
            'confirm_apply' => 1,
        ));

        $this->assertSame(array(11, 12), $result['request']['product_ids']);
        $this->assertSame('filter', $result['request']['selection_mode']);
        $this->assertSame(3, $selection->lastFilterRequest['limit']);
        $this->assertSame('sku', $selection->lastFilterRequest['filters']['query']);

        $selection->filterIds = array(11, 12, 13);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('не более 2 товаров');
        $service->apply(array(
            'selection_mode' => 'filter',
            'filters' => array('query' => 'sku'),
            'operation' => 'visibility',
            'visibility_status' => 1,
            'confirm_apply' => 1,
        ));
    }

    public function testApplyStockOperationUpdatesSkuStocksAndRejectsNegativeResult(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'Stock Product'));
        $log = new FakeLogService();
        $model = new waModel();
        $model->queueResponse('FROM shop_stock', new FakeQueryResult(array(
            array('id' => 3, 'name' => 'Main'),
            array('id' => 4, 'name' => 'Reserve'),
        )));
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'count' => 5, 'price' => 100, 'compare_price' => 0, 'available' => 1),
        )));
        $model->queueResponse('FROM shop_product_stocks', new FakeQueryResult(array(
            array('sku_id' => 101, 'stock_id' => 3, 'count' => 5),
        )));
        shopProduct::seed(11, array('name' => 'Stock Product'), array(
            101 => array('id' => 101, 'product_id' => 11, 'stock' => array(3 => 5)),
        ));

        $service = new shopMasseditorPluginMassOperationService($selection, $log, $model, 100);
        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'stock',
            'stock_id' => 3,
            'stock_mode' => 'decrease',
            'stock_value' => '2',
            'confirm_apply' => 1,
        ));

        $this->assertArrayNotHasKey(11, shopProduct::$saved);
        $this->assertStringContainsString('REPLACE INTO shop_product_stocks', $model->execs[1]['sql']);
        $this->assertSame(3.0, $model->execs[1]['params']['count_0']);
        $this->assertSame('stock', $log->logged[0]['action_type']);

        $model->queueResponse('FROM shop_stock', new FakeQueryResult(array(array('id' => 3, 'name' => 'Main'))));
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'count' => 5, 'price' => 100, 'compare_price' => 0, 'available' => 1),
        )));
        $model->queueResponse('FROM shop_product_stocks', new FakeQueryResult(array(
            array('sku_id' => 101, 'stock_id' => 3, 'count' => 3),
        )));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Остаток не может стать отрицательным');
        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'stock',
            'stock_id' => 3,
            'stock_mode' => 'decrease',
            'stock_value' => '20',
            'confirm_apply' => 1,
        ));
    }

    public function testApplyStockOperationLoadsCurrentWarehouseStocksBeforeCalculation(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'Stock Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_stock', new FakeQueryResult(array(
            array('id' => 3, 'name' => 'Main'),
            array('id' => 4, 'name' => 'Reserve'),
        )));
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'count' => 5, 'price' => 100, 'compare_price' => 0, 'available' => 1),
        )));
        $model->queueResponse('FROM shop_product_stocks', new FakeQueryResult(array(
            array('sku_id' => 101, 'stock_id' => 3, 'count' => 5),
            array('sku_id' => 101, 'stock_id' => 4, 'count' => 9),
        )));
        shopProduct::seed(11, array('name' => 'Stock Product'), array(
            101 => array('id' => 101, 'product_id' => 11, 'count' => 5),
        ));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'stock',
            'stock_id' => 3,
            'stock_mode' => 'decrease',
            'stock_value' => '2',
            'confirm_apply' => 1,
        ));

        $this->assertArrayNotHasKey(11, shopProduct::$saved);
        $this->assertStringContainsString('REPLACE INTO shop_product_stocks', $model->execs[1]['sql']);
        $this->assertSame(101, $model->execs[1]['params']['sku_id_0']);
        $this->assertSame(3, $model->execs[1]['params']['stock_id_0']);
        $this->assertSame(3.0, $model->execs[1]['params']['count_0']);
        $this->assertSame(101, $model->execs[1]['params']['sku_id_1']);
        $this->assertSame(4, $model->execs[1]['params']['stock_id_1']);
        $this->assertSame(9.0, $model->execs[1]['params']['count_1']);
        $this->assertSame(array(11), shopProductModel::$corrected);
    }

    public function testApplyStockOperationMaterializesOtherWarehouseStocksWithZero(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'Stock Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_stock', new FakeQueryResult(array(
            array('id' => 3, 'name' => 'Main'),
            array('id' => 4, 'name' => 'Reserve'),
        )));
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'count' => 7, 'price' => 100, 'compare_price' => 0, 'available' => 1),
        )));
        $model->queueResponse('FROM shop_product_stocks', new FakeQueryResult(array(
            array('sku_id' => 101, 'stock_id' => 4, 'count' => 2),
        )));
        shopProduct::seed(11, array('name' => 'Stock Product'), array(
            101 => array('id' => 101, 'product_id' => 11, 'count' => 7),
        ));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'stock',
            'stock_id' => 3,
            'stock_mode' => 'increase',
            'stock_value' => '5',
            'confirm_apply' => 1,
        ));

        $this->assertArrayNotHasKey(11, shopProduct::$saved);
        $this->assertStringContainsString('REPLACE INTO shop_product_stocks', $model->execs[1]['sql']);
        $this->assertSame(101, $model->execs[1]['params']['sku_id_0']);
        $this->assertSame(3, $model->execs[1]['params']['stock_id_0']);
        $this->assertSame(5.0, $model->execs[1]['params']['count_0']);
        $this->assertSame(101, $model->execs[1]['params']['sku_id_1']);
        $this->assertSame(4, $model->execs[1]['params']['stock_id_1']);
        $this->assertSame(2.0, $model->execs[1]['params']['count_1']);
        $this->assertSame(array(11), shopProductModel::$corrected);
    }

    public function testApplyStockOperationWithWarehouseSelectionRejectsDecreaseBelowZeroForProductsWithoutWarehouseAccounting(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'Stock Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_stock', new FakeQueryResult(array(
            array('id' => 3, 'name' => 'Main'),
            array('id' => 4, 'name' => 'Reserve'),
        )));
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'count' => 7, 'price' => 100, 'compare_price' => 0, 'available' => 1),
        )));
        $model->queueResponse('FROM shop_product_stocks', new FakeQueryResult(array()));
        shopProduct::seed(11, array('name' => 'Stock Product'), array(
            101 => array('id' => 101, 'product_id' => 11, 'count' => 7),
        ));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Остаток не может стать отрицательным');
        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'stock',
            'stock_id' => 3,
            'stock_mode' => 'decrease',
            'stock_value' => '1',
            'confirm_apply' => 1,
        ));
    }

    public function testApplyStockOperationWithoutWarehouseUpdatesSkuCount(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'Stock Product'));
        shopProduct::seed(11, array('name' => 'Stock Product'), array(
            101 => array('id' => 101, 'product_id' => 11, 'count' => 5),
        ));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), new waModel(), 100);
        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'stock',
            'stock_id' => 0,
            'stock_mode' => 'increase',
            'stock_value' => '2',
            'confirm_apply' => 1,
        ));

        $this->assertSame(7.0, shopProduct::$saved[11]['skus'][101]['count']);
        $this->assertArrayNotHasKey('stock', shopProduct::$saved[11]['skus'][101]);
    }

    public function testApplyStockOperationWithWarehouseSelectionWritesWarehouseStockForProductsWithoutWarehouseAccounting(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'Stock Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_stock', new FakeQueryResult(array(
            array('id' => 3, 'name' => 'Main'),
            array('id' => 4, 'name' => 'Reserve'),
        )));
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'count' => 5, 'price' => 100, 'compare_price' => 0, 'available' => 1),
        )));
        $model->queueResponse('FROM shop_product_stocks', new FakeQueryResult(array()));
        shopProduct::seed(11, array('name' => 'Stock Product'), array(
            101 => array('id' => 101, 'product_id' => 11, 'count' => 5),
        ));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'stock',
            'stock_id' => 3,
            'stock_mode' => 'set',
            'stock_value' => '333',
            'confirm_apply' => 1,
        ));

        $this->assertArrayNotHasKey(11, shopProduct::$saved);
        $this->assertStringContainsString('REPLACE INTO shop_product_stocks', $model->execs[1]['sql']);
        $this->assertSame(101, $model->execs[1]['params']['sku_id_0']);
        $this->assertSame(3, $model->execs[1]['params']['stock_id_0']);
        $this->assertSame(333.0, $model->execs[1]['params']['count_0']);
        $this->assertSame(101, $model->execs[1]['params']['sku_id_1']);
        $this->assertSame(4, $model->execs[1]['params']['stock_id_1']);
        $this->assertSame(0.0, $model->execs[1]['params']['count_1']);
        $this->assertSame(array(11), shopProductModel::$corrected);
    }

    public function testApplyStockOperationWritesProductIdForEverySkuOnSelectedWarehouse(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'Stock Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_stock', new FakeQueryResult(array(
            array('id' => 3, 'name' => 'Main'),
            array('id' => 4, 'name' => 'Reserve'),
        )));
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'count' => 5, 'price' => 100, 'compare_price' => 0, 'available' => 1),
            array('id' => 102, 'product_id' => 11, 'count' => 7, 'price' => 100, 'compare_price' => 0, 'available' => 1),
        )));
        $model->queueResponse('FROM shop_product_stocks', new FakeQueryResult(array(
            array('sku_id' => 101, 'stock_id' => 4, 'count' => 9),
        )));
        shopProduct::seed(11, array('name' => 'Stock Product'), array(
            101 => array('id' => 101, 'product_id' => 11, 'count' => 5),
            102 => array('id' => 102, 'product_id' => 11, 'count' => 7),
        ));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(11),
            'operation' => 'stock',
            'stock_id' => 3,
            'stock_mode' => 'set',
            'stock_value' => '123',
            'confirm_apply' => 1,
        ));

        $stock_write = $model->execs[1];
        $this->assertStringContainsString('(sku_id, stock_id, product_id, count)', $stock_write['sql']);
        $this->assertSame(101, $stock_write['params']['sku_id_0']);
        $this->assertSame(11, $stock_write['params']['product_id_0']);
        $this->assertSame(101, $stock_write['params']['sku_id_1']);
        $this->assertSame(11, $stock_write['params']['product_id_1']);
        $this->assertSame(3, $stock_write['params']['stock_id_0']);
        $this->assertSame(4, $stock_write['params']['stock_id_1']);
        $this->assertSame(102, $stock_write['params']['sku_id_2']);
        $this->assertSame(11, $stock_write['params']['product_id_2']);
        $this->assertSame(3, $stock_write['params']['stock_id_2']);
        $this->assertSame(102, $stock_write['params']['sku_id_3']);
        $this->assertSame(11, $stock_write['params']['product_id_3']);
        $this->assertSame(4, $stock_write['params']['stock_id_3']);
    }

    public function testApplyStockOperationWithWarehouseSelectionSupportsIncreaseDecreaseAndInfiniteModes(): void
    {
        $scenarios = array(
            'increase_from_existing' => array(
                'stock_rows' => array(array('sku_id' => 101, 'stock_id' => 3, 'count' => 4)),
                'mode' => 'increase',
                'value' => '2',
                'expected' => 6.0,
            ),
            'decrease_from_existing' => array(
                'stock_rows' => array(array('sku_id' => 101, 'stock_id' => 3, 'count' => 4)),
                'mode' => 'decrease',
                'value' => '1.5',
                'expected' => 2.5,
            ),
            'infinite' => array(
                'stock_rows' => array(array('sku_id' => 101, 'stock_id' => 3, 'count' => 4)),
                'mode' => 'infinite',
                'value' => '',
                'expected' => null,
            ),
            'increase_from_missing_stock' => array(
                'stock_rows' => array(),
                'mode' => 'increase',
                'value' => '8',
                'expected' => 8.0,
            ),
        );

        foreach ($scenarios as $label => $scenario) {
            shopProduct::reset();
            shopProductModel::reset();

            $selection = new FakeSelectionService();
            $selection->products = array(11 => array('id' => 11, 'name' => 'Stock Product'));
            $model = new waModel();
            $model->queueResponse('FROM shop_stock', new FakeQueryResult(array(array('id' => 3, 'name' => 'Main'))));
            $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
                array('id' => 101, 'product_id' => 11, 'count' => 5, 'price' => 100, 'compare_price' => 0, 'available' => 1),
            )));
            $model->queueResponse('FROM shop_product_stocks', new FakeQueryResult($scenario['stock_rows']));
            shopProduct::seed(11, array('name' => 'Stock Product'), array(
                101 => array('id' => 101, 'product_id' => 11, 'count' => 5),
            ));

            $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
            $service->apply(array(
                'product_ids' => array(11),
                'operation' => 'stock',
                'stock_id' => 3,
                'stock_mode' => $scenario['mode'],
                'stock_value' => $scenario['value'],
                'confirm_apply' => 1,
            ));

            $this->assertStringContainsString('REPLACE INTO shop_product_stocks', $model->execs[1]['sql'], $label);
            $this->assertSame($scenario['expected'], $model->execs[1]['params']['count_0'], $label);
            $this->assertSame(array(11), shopProductModel::$corrected, $label);
        }
    }

    public function testApplyStockOperationWithoutWarehouseRejectsProductsWithWarehouseStocks(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'Stock Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'count' => 7, 'price' => 100, 'compare_price' => 0, 'available' => 1),
        )));
        $model->queueResponse('FROM shop_product_stocks', new FakeQueryResult(array(
            array('sku_id' => 101, 'stock_id' => 3, 'count' => 5),
        )));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);

        try {
            $service->apply(array(
                'product_ids' => array(11),
                'operation' => 'stock',
                'stock_id' => 0,
                'stock_mode' => 'increase',
                'stock_value' => '2',
                'confirm_apply' => 1,
            ));
            $this->fail('Exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Для товаров со складским учетом выберите конкретный склад.', $e->getMessage());
        }

        $this->assertCount(0, $model->execs);
    }

    public function testApplyFeatureOperationAllowsOnlyBasicExistingValues(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Feature Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 7,
            'code' => 'material',
            'name' => 'Material',
            'type' => 'varchar',
            'selectable' => 1,
            'multiple' => 0,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(array('id' => 77))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 7,
            'feature_mode' => 'set',
            'feature_value' => 'cotton',
            'confirm_apply' => 1,
        ));

        $this->assertSame(array('material' => 'cotton'), shopProduct::$saved[15]['features']);
        $this->assertFalse($this->modelExecutedSqlContaining($model, 'shop_product_features'));

        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 8,
            'code' => 'color',
            'name' => 'Color',
            'type' => 'color',
            'selectable' => 1,
            'multiple' => 0,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(array('id' => 78))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 8,
            'feature_mode' => 'set',
            'feature_value' => 'red',
            'confirm_apply' => 1,
        ));

        $this->assertSame(array('color' => 'red'), shopProduct::$saved[15]['features']);
        $this->assertFalse($this->modelExecutedSqlContaining($model, 'shop_product_features'));
    }

    /**
     * Given a multiple product feature and duplicate selected value IDs.
     * When replace mode is applied.
     * Then only unique values of the selected feature are passed to shopProduct.
     */
    public function testApplyMultipleFeatureReplaceDeduplicatesSelectedValues(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Feature Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 7,
            'code' => 'material',
            'name' => 'Material',
            'type' => 'varchar',
            'selectable' => 1,
            'multiple' => 1,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(
            array('id' => 71, 'feature_id' => 7, 'value' => 'Cotton'),
            array('id' => 72, 'feature_id' => 7, 'value' => 'Linen'),
        )));
        $model->queueResponse('FROM shop_product_features', new FakeQueryResult(array(
            array('product_id' => 15, 'feature_value_id' => 70),
        )));

        shopProduct::seed(15, array('features' => array('other' => 'keep')));
        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 7,
            'feature_mode' => 'replace',
            'feature_value_ids' => array('71', '72', '71'),
            'confirm_apply' => 1,
        ));

        $this->assertSame(array('material' => array('Cotton', 'Linen')), shopProduct::$saved[15]['features']);
        $this->assertFalse($this->modelExecutedSqlContaining($model, 'DELETE FROM shop_product_features'));
    }

    /**
     * Given a value ID that does not belong to the selected feature.
     * When a multiple feature operation is requested.
     * Then the request is rejected before a transaction or product save.
     */
    public function testApplyMultipleFeatureRejectsForeignValueBeforeWrite(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Feature Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 7,
            'code' => 'material',
            'name' => 'Material',
            'type' => 'varchar',
            'selectable' => 1,
            'multiple' => 1,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(
            array('id' => 71, 'feature_id' => 7, 'value' => 'Cotton'),
        )));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);

        try {
            $service->apply(array(
                'product_ids' => array(15),
                'operation' => 'features',
                'feature_id' => 7,
                'feature_mode' => 'add',
                'feature_value_ids' => array(71, 999),
                'confirm_apply' => 1,
            ));
            $this->fail('Exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Выберите существующее значение характеристики.', $e->getMessage());
        }

        $this->assertCount(0, $model->execs);
        $this->assertArrayNotHasKey(15, shopProduct::$saved);
    }

    /**
     * Given existing product-level values and an SKU value of the same feature.
     * When add, remove and clear modes are applied.
     * Then only product-level IDs participate and each mode computes an exact set.
     */
    public function testApplyMultipleFeatureModesUseOnlyProductLevelValues(): void
    {
        $scenarios = array(
            'add' => array('selected' => array(72), 'expected' => array('Cotton', 'Linen')),
            'remove' => array('selected' => array(71), 'expected' => array()),
            'clear' => array('selected' => array(), 'expected' => array()),
        );

        foreach ($scenarios as $mode => $scenario) {
            shopProduct::reset();
            shopProduct::seed(15, array('features' => array('other' => 'keep')));
            $selection = new FakeSelectionService();
            $selection->products = array(15 => array('id' => 15, 'name' => 'Feature Product'));
            $model = new waModel();
            $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
                'id' => 7,
                'code' => 'material',
                'name' => 'Material',
                'type' => 'varchar',
                'selectable' => 1,
                'multiple' => 1,
                'parent_id' => 0,
            ))));
            if ($scenario['selected']) {
                $selected_rows = array();
                foreach ($scenario['selected'] as $value_id) {
                    $selected_rows[] = array(
                        'id' => $value_id,
                        'feature_id' => 7,
                        'value' => $value_id === 71 ? 'Cotton' : 'Linen',
                    );
                }
                $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult($selected_rows));
            }
            $model->queueResponse('FROM shop_product_features', new FakeQueryResult(array(
                array('product_id' => 15, 'feature_value_id' => 71),
            )));
            $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(
                array('id' => 71, 'feature_id' => 7, 'value' => 'Cotton'),
            )));

            $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
            $service->apply(array(
                'product_ids' => array(15),
                'operation' => 'features',
                'feature_id' => 7,
                'feature_mode' => $mode,
                'feature_value_ids' => $scenario['selected'],
                'confirm_apply' => 1,
            ));

            $this->assertSame(array('material' => $scenario['expected']), shopProduct::$saved[15]['features'], 'Unexpected mode ' . $mode);
            if (in_array($mode, array('add', 'remove'), true)) {
                $current_query = $this->modelQueryContaining($model, 'FROM shop_product_features');
                $this->assertStringContainsString('sku_id IS NULL', $current_query['sql']);
            }
        }
    }

    /**
     * Given two products and a failure while saving the second product.
     * When a multiple feature operation runs.
     * Then the service rolls the whole operation back and does not write a success log.
     */
    public function testApplyMultipleFeatureRollsBackOnProductSaveFailure(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            15 => array('id' => 15, 'name' => 'First Product'),
            16 => array('id' => 16, 'name' => 'Second Product'),
        );
        $log = new FakeLogService();
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 7,
            'code' => 'material',
            'name' => 'Material',
            'type' => 'varchar',
            'selectable' => 1,
            'multiple' => 1,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(
            array('id' => 71, 'feature_id' => 7, 'value' => 'Cotton'),
        )));
        $model->queueResponse('FROM shop_product_features', new FakeQueryResult(array()));
        shopProduct::seed(15);
        shopProduct::seed(16);
        shopProduct::failSaveFor(16, 'Simulated save failure');

        $service = new shopMasseditorPluginMassOperationService($selection, $log, $model, 100);

        try {
            $service->apply(array(
                'product_ids' => array(15, 16),
                'operation' => 'features',
                'feature_id' => 7,
                'feature_mode' => 'replace',
                'feature_value_ids' => array(71),
                'confirm_apply' => 1,
            ));
            $this->fail('Exception was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated save failure', $e->getMessage());
        }

        $this->assertSame('START TRANSACTION', $model->execs[0]['sql']);
        $this->assertSame('ROLLBACK', end($model->execs)['sql']);
        $this->assertCount(0, $log->logged);
    }

    /**
     * Given an oversized list of feature value IDs.
     * When a multiple feature operation is validated.
     * Then it is rejected before any database write.
     */
    public function testApplyMultipleFeatureRejectsOversizedValueListBeforeWrite(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Feature Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 7,
            'code' => 'material',
            'name' => 'Material',
            'type' => 'varchar',
            'selectable' => 1,
            'multiple' => 1,
            'parent_id' => 0,
        ))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Выбрано слишком много значений характеристики.');
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 7,
            'feature_mode' => 'replace',
            'feature_value_ids' => range(1, 1001),
            'confirm_apply' => 1,
        ));
    }

    private function modelExecutedSqlContaining(waModel $model, $needle)
    {
        foreach ($model->execs as $exec) {
            if (strpos($exec['sql'], $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function modelQueryContaining(waModel $model, $needle)
    {
        foreach ($model->queries as $query) {
            if (strpos($query['sql'], $needle) !== false) {
                return $query;
            }
        }

        $this->fail('Query was not executed: ' . $needle);
    }

    public function testApplyFeatureOperationCreatesMissingBasicValueBeforeAssigning(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Feature Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 7,
            'code' => 'material',
            'name' => 'Material',
            'type' => 'varchar',
            'selectable' => 0,
            'multiple' => 0,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array()));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(array('id' => 88))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 7,
            'feature_mode' => 'set',
            'feature_value' => 'linen',
            'confirm_apply' => 1,
        ));

        $this->assertSame(array('material' => 'linen'), shopProduct::$saved[15]['features']);
        $this->assertFalse($this->modelExecutedSqlContaining($model, 'shop_feature_values_'));
        $this->assertFalse($this->modelExecutedSqlContaining($model, 'shop_product_features'));
    }

    public function testApplyFeatureOperationValidatesNumericBasicValue(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Feature Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 9,
            'code' => 'weight',
            'name' => 'Weight',
            'type' => 'double',
            'selectable' => 0,
            'multiple' => 0,
            'parent_id' => 0,
        ))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Укажите корректное значение характеристики.');
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 9,
            'feature_mode' => 'set',
            'feature_value' => 'heavy',
            'confirm_apply' => 1,
        ));
    }

    public function testApplyFeatureOperationSupportsBooleanType(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Bool Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 20,
            'code' => 'wifi',
            'name' => 'WiFi',
            'type' => 'boolean',
            'selectable' => 1,
            'multiple' => 0,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(array('id' => 101))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 20,
            'feature_mode' => 'set',
            'feature_value' => '1',
            'confirm_apply' => 1,
        ));

        $this->assertSame(array('wifi' => '1'), shopProduct::$saved[15]['features']);
    }

    public function testApplyFeatureOperationSupportsDimensionType(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Dim Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 21,
            'code' => 'height',
            'name' => 'Height',
            'type' => 'dimension.length',
            'selectable' => 0,
            'multiple' => 0,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_double', new FakeQueryResult(array(array('id' => 102))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 21,
            'feature_mode' => 'set',
            'feature_value' => '12,5',
            'confirm_apply' => 1,
        ));

        $this->assertSame(array('height' => 12.5), shopProduct::$saved[15]['features']);
    }

    public function testApplyFeatureOperationSupportsColorType(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Color Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 22,
            'code' => 'color',
            'name' => 'Color',
            'type' => 'color',
            'selectable' => 1,
            'multiple' => 0,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(array('id' => 103))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 22,
            'feature_mode' => 'set',
            'feature_value' => '#ff0000',
            'confirm_apply' => 1,
        ));

        $this->assertSame(array('color' => '#ff0000'), shopProduct::$saved[15]['features']);
    }

    public function testApplyFeatureOperationSupportsSelectType(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Select Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 23,
            'code' => 'size',
            'name' => 'Size',
            'type' => 'select',
            'selectable' => 1,
            'multiple' => 0,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(array('id' => 104))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 23,
            'feature_mode' => 'set',
            'feature_value' => 'Large',
            'confirm_apply' => 1,
        ));

        $this->assertSame(array('size' => 'Large'), shopProduct::$saved[15]['features']);
    }

    public function testApplyFeatureOperationSupportsRadioType(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Radio Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 24,
            'code' => 'material',
            'name' => 'Material',
            'type' => 'radio',
            'selectable' => 1,
            'multiple' => 0,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(array('id' => 105))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 24,
            'feature_mode' => 'set',
            'feature_value' => 'Cotton',
            'confirm_apply' => 1,
        ));

        $this->assertSame(array('material' => 'Cotton'), shopProduct::$saved[15]['features']);
    }

    public function testApplyFeatureOperationSupportsRangeType(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Range Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 25,
            'code' => 'power',
            'name' => 'Power',
            'type' => 'range',
            'selectable' => 0,
            'multiple' => 0,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_double', new FakeQueryResult(array(array('id' => 106))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 25,
            'feature_mode' => 'set',
            'feature_value' => '42',
            'confirm_apply' => 1,
        ));

        $this->assertSame(array('power' => 42.0), shopProduct::$saved[15]['features']);
    }

    public function testApplyFeatureOperationClearWorksForAllTypes(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Clear Product'));

        foreach (array('boolean', 'dimension.length', 'color', 'select', 'radio', 'range') as $type) {
            $model = new waModel();
            $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
                'id' => 30,
                'code' => 'test_feature',
                'name' => 'Test Feature',
                'type' => $type,
                'selectable' => 0,
                'multiple' => 0,
                'parent_id' => 0,
            ))));

            $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
            $service->apply(array(
                'product_ids' => array(15),
                'operation' => 'features',
                'feature_id' => 30,
                'feature_mode' => 'clear',
                'feature_value' => '',
                'confirm_apply' => 1,
            ));

            $this->assertSame(array('test_feature' => array()), shopProduct::$saved[15]['features']);
            $this->assertCount(2, $model->execs, "Clear for type {$type} should have START TRANSACTION and COMMIT");
            $this->assertStringContainsString('START TRANSACTION', $model->execs[0]['sql']);
            $this->assertStringContainsString('COMMIT', $model->execs[1]['sql']);
        }
    }

    public function testApplyFeatureOperationRejectsUnknownTypeWithoutFallbackWrite(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Unknown Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 26,
            'code' => 'custom',
            'name' => 'Custom',
            'type' => 'custom_future_type',
            'selectable' => 0,
            'multiple' => 0,
            'parent_id' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(array('id' => 107))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        try {
            $service->apply(array(
                'product_ids' => array(15),
                'operation' => 'features',
                'feature_id' => 26,
                'feature_mode' => 'set',
                'feature_value' => 'test value',
                'confirm_apply' => 1,
            ));
            $this->fail('Exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Тип характеристики не поддерживается в этой версии.', $e->getMessage());
        }

        $this->assertCount(0, $model->execs);
    }

    public function testApplyCategoriesOperationAddsRemovesAndReplacesMainCategory(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(21 => array('id' => 21, 'name' => 'Category Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_category WHERE', new FakeQueryResult(array(array('id' => 5, 'name' => 'Sale'))));
        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);

        $service->apply(array(
            'product_ids' => array(21),
            'operation' => 'categories',
            'category_id' => 5,
            'categories_mode' => 'replace_main',
            'confirm_apply' => 1,
        ));

        $this->assertStringContainsString('UPDATE shop_product SET category_id', $model->execs[1]['sql']);
        $this->assertStringContainsString('INSERT IGNORE INTO shop_category_products', $model->execs[2]['sql']);

        $model = new waModel();
        $model->queueResponse('FROM shop_category WHERE', new FakeQueryResult(array(array('id' => 5, 'name' => 'Sale'))));
        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(21),
            'operation' => 'categories',
            'category_id' => 5,
            'categories_mode' => 'remove',
            'confirm_apply' => 1,
        ));
        $this->assertStringContainsString('DELETE FROM shop_category_products', $model->execs[1]['sql']);
    }

    public function testApplyVideoSetsAndClearsUrlThroughShopProduct(): void
    {
        foreach (array(
            'set' => array('input' => 'https://www.youtube.com/watch?v=abc', 'expected' => 'http://youtu.be/abc'),
            'clear' => array('input' => '', 'expected' => null),
        ) as $mode => $scenario) {
            shopProduct::reset();
            shopProduct::seed(31, array('video_url' => 'https://example.com/old'));
            $selection = new FakeSelectionService();
            $selection->products = array(31 => array('id' => 31, 'name' => 'Video Product'));
            $log = new FakeLogService();
            $model = new waModel();
            $service = new shopMasseditorPluginMassOperationService($selection, $log, $model, 100);

            $result = $service->apply(array(
                'product_ids' => array(31),
                'operation' => 'video',
                'video_mode' => $mode,
                'video_url' => $scenario['input'],
                'confirm_apply' => 1,
            ));

            $this->assertSame($scenario['expected'], shopProduct::$saved[31]['video_url']);
            $this->assertSame('video', $log->logged[0]['action_type']);
            $this->assertSame('COMMIT', end($model->execs)['sql']);
            $this->assertSame('Видео · 1 товар', $result['summary']);
        }
    }

    public function testApplyVideoRejectsUnsupportedProviderBeforeTransaction(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(31 => array('id' => 31, 'name' => 'Планшет'));
        $log = new FakeLogService();
        $model = new waModel();
        shopProduct::seed(31, array('video_url' => null));
        $service = new shopMasseditorPluginMassOperationService($selection, $log, $model, 100);

        try {
            $service->apply(array(
                'product_ids' => array(31),
                'operation' => 'video',
                'video_mode' => 'set',
                'video_url' => 'https://yandex.ru/video/preview/14675653702268624155',
                'confirm_apply' => 1,
            ));
            $this->fail('Unsupported video provider was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(
                'Скопируйте в это поле адрес видеоролика товара с сайта Rutube, VK, YouTube или Vimeo.',
                $e->getMessage()
            );
        }

        $this->assertCount(0, $model->execs);
        $this->assertCount(0, $log->logged);
        $this->assertArrayNotHasKey(31, shopProduct::$saved);
    }

    public function testApplyVideoRejectsInvalidUrlBeforeTransaction(): void
    {
        foreach (array(
            'javascript:alert(1)',
            'https://example.com/' . str_repeat('a', 240),
        ) as $invalid_url) {
            $selection = new FakeSelectionService();
            $selection->products = array(31 => array('id' => 31, 'name' => 'Video Product'));
            $model = new waModel();
            $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);

            try {
                $service->apply(array(
                    'product_ids' => array(31),
                    'operation' => 'video',
                    'video_mode' => 'set',
                    'video_url' => $invalid_url,
                    'confirm_apply' => 1,
                ));
                $this->fail('Invalid video URL was accepted.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('Укажите корректную HTTP(S)-ссылку на видео.', $e->getMessage());
            }

            $this->assertCount(0, $model->execs);
        }
    }

    public function testApplyVideoRollsBackWhenProductRightsAreDenied(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(31 => array('id' => 31, 'name' => 'Video Product'));
        $log = new FakeLogService();
        $model = new waModel();
        shopProduct::seed(31);
        shopProduct::denyRightsFor(31);
        $service = new shopMasseditorPluginMassOperationService($selection, $log, $model, 100);

        try {
            $service->apply(array(
                'product_ids' => array(31),
                'operation' => 'video',
                'video_mode' => 'clear',
                'confirm_apply' => 1,
            ));
            $this->fail('Exception was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('Недостаточно прав для изменения выбранного товара.', $e->getMessage());
        }

        $this->assertSame('ROLLBACK', end($model->execs)['sql']);
        $this->assertCount(0, $log->logged);
    }

    public function testNormalizeHelpersAndDescriptions(): void
    {
        $service = new shopMasseditorPluginMassOperationService(
            new FakeSelectionService(),
            new FakeLogService(),
            new waModel(),
            100
        );

        $this->assertSame(10.0, $this->invokePrivate($service, 'calculateNumericValue', array(50, 'set', 10)));
        $this->assertSame(60.0, $this->invokePrivate($service, 'calculateNumericValue', array(50, 'add', 10)));
        $this->assertSame(40.0, $this->invokePrivate($service, 'calculateNumericValue', array(50, 'subtract', 10)));
        $this->assertSame(55.0, $this->invokePrivate($service, 'calculateNumericValue', array(50, 'increase_percent', 10)));
        $this->assertSame(45.0, $this->invokePrivate($service, 'calculateNumericValue', array(50, 'decrease_percent', 10)));
        $this->assertSame(55.13, $this->invokePrivate($service, 'applyRounding', array(55.123, '2', 'up')));
        $this->assertSame(array('red', 'blue'), $this->invokePrivate($service, 'normalizeTagList', array("red,\nblue, red")));
        $this->assertSame('2', $this->invokePrivate($service, 'normalizeRoundStep', array('2')));
        $this->assertSame('', $this->invokePrivate($service, 'normalizeRoundStep', array('10')));
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
        $this->assertSame('Изменить цену: прибавить значение 10 для 2 товаров', $this->invokePrivate($service, 'buildDescription', array(
            array('operation' => 'price', 'mode' => 'add', 'numeric_value' => 10),
            2,
        )));
        $this->assertSame('Изменить цену сравнения: убавить на 10% для 2 товаров', $this->invokePrivate($service, 'buildDescription', array(
            array('operation' => 'compare_price', 'mode' => 'decrease_percent', 'numeric_value' => 10),
            2,
        )));
    }

    public function testEnglishLanguageChangesMessagesSummaryAndDescriptions(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            11 => array('id' => 11, 'name' => 'Product 11'),
            12 => array('id' => 12, 'name' => 'Product 12'),
        );
        $service = new shopMasseditorPluginMassOperationService(
            $selection,
            new FakeLogService(),
            new waModel(),
            100,
        );

        $GLOBALS['fake_wa_system']->locale = 'en_US';
        $result = $service->apply(array(
            'product_ids' => array(11, 12),
            'operation' => 'visibility',
            'visibility_status' => 0,
            'confirm_apply' => 1,
        ));

        $this->assertSame('Operation applied successfully.', $result['message']);
        $this->assertSame('Change visibility · 2 products', $result['summary']);
        $this->assertSame('Visibility: Hidden for 2 products', $this->invokePrivate($service, 'buildDescription', array(
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

    public function testGetFeatureValuesReturnsSelectableValues(): void
    {
        $model = new waModel();
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(
            array('id' => 1, 'value' => 'Cotton'),
            array('id' => 2, 'value' => 'Linen'),
            array('id' => 3, 'value' => 'Silk'),
        )));

        $service = new shopMasseditorPluginMassOperationService(
            new FakeSelectionService(),
            new FakeLogService(),
            $model,
            100
        );

        $values = $service->getFeatureValues(7, 'varchar');

        $this->assertCount(3, $values);
        $this->assertSame('Cotton', $values[0]['value']);
        $this->assertSame('Linen', $values[1]['value']);
        $this->assertSame('Silk', $values[2]['value']);
    }

    public function testGetFeatureValuesReturnsEmptyForInvalidSuffix(): void
    {
        $model = new waModel();
        $service = new shopMasseditorPluginMassOperationService(
            new FakeSelectionService(),
            new FakeLogService(),
            $model,
            100
        );

        $values = $service->getFeatureValues(7, 'invalid_table');

        $this->assertEmpty($values);
    }

    public function testGetFeatureValuesMapBatchesByTableAndRejectsForeignRows(): void
    {
        $model = new waModel();
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(
            array('id' => 1, 'feature_id' => 7, 'value' => 'Cotton'),
            array('id' => 2, 'feature_id' => 8, 'value' => 'Linen'),
            array('id' => 3, 'feature_id' => 99, 'value' => 'Foreign'),
        )));
        $service = new shopMasseditorPluginMassOperationService(
            new FakeSelectionService(),
            new FakeLogService(),
            $model,
            100
        );

        $map = $service->getFeatureValuesMap(array(
            array('id' => 7, 'type' => 'varchar', 'selectable' => 1, 'multiple' => 0),
            array('id' => 8, 'type' => 'varchar', 'selectable' => 1, 'multiple' => 1),
        ));

        $this->assertSame(array(7, 8), array_keys($map));
        $this->assertCount(1, $map[7]);
        $this->assertCount(1, $map[8]);
        $this->assertCount(1, $model->queries);
    }

    public function testApplyStockOperationWithStockTypeFilterWithoutWarehouseSkipsWarehouseProducts(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            11 => array('id' => 11, 'name' => 'No Warehouse Product'),
            12 => array('id' => 12, 'name' => 'Warehouse Product'),
        );
        $model = new waModel();
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'count' => 5, 'price' => 100, 'compare_price' => 0, 'available' => 1),
            array('id' => 102, 'product_id' => 12, 'count' => 3, 'price' => 200, 'compare_price' => 0, 'available' => 1),
        )));
        $model->queueResponse('FROM shop_product_stocks', new FakeQueryResult(array(
            array('sku_id' => 102, 'stock_id' => 1, 'count' => 10),
        )));
        shopProduct::seed(11, array('name' => 'No Warehouse Product'), array(
            101 => array('id' => 101, 'product_id' => 11, 'count' => 5),
        ));
        shopProduct::seed(12, array('name' => 'Warehouse Product'), array(
            102 => array('id' => 102, 'product_id' => 12, 'count' => 3),
        ));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $result = $service->apply(array(
            'product_ids' => array(11, 12), 'operation' => 'stock', 'stock_id' => 0,
            'stock_mode' => 'increase', 'stock_value' => '10',
            'stock_type_filter' => 'without_warehouse', 'confirm_apply' => 1,
        ));

        $this->assertSame(15.0, shopProduct::$saved[11]['skus'][101]['count']);
        $this->assertArrayNotHasKey(12, shopProduct::$saved);
        $this->assertSame(1, $result['skipped']);
    }

    public function testApplyStockOperationWithStockTypeFilterWithWarehouseSkipsNonWarehouseProducts(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(
            11 => array('id' => 11, 'name' => 'No Warehouse Product'),
            12 => array('id' => 12, 'name' => 'Warehouse Product'),
        );
        $model = new waModel();
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'count' => 5, 'price' => 100, 'compare_price' => 0, 'available' => 1),
            array('id' => 102, 'product_id' => 12, 'count' => 3, 'price' => 200, 'compare_price' => 0, 'available' => 1),
        )));
        $model->queueResponse('FROM shop_product_stocks', new FakeQueryResult(array(
            array('sku_id' => 102, 'stock_id' => 1, 'count' => 10),
        )));
        shopProduct::seed(11, array('name' => 'No Warehouse Product'), array(
            101 => array('id' => 101, 'product_id' => 11, 'count' => 5),
        ));
        shopProduct::seed(12, array('name' => 'Warehouse Product'), array(
            102 => array('id' => 102, 'product_id' => 12, 'count' => 3),
        ));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $result = $service->apply(array(
            'product_ids' => array(11, 12), 'operation' => 'stock', 'stock_id' => 0,
            'stock_mode' => 'increase', 'stock_value' => '10',
            'stock_type_filter' => 'with_warehouse', 'confirm_apply' => 1,
        ));

        $this->assertSame(13.0, shopProduct::$saved[12]['skus'][102]['count']);
        $this->assertArrayNotHasKey(11, shopProduct::$saved);
        $this->assertSame(1, $result['skipped']);
    }

    public function testApplyStockOperationWithStockTypeFilterAllProcessesAllProducts(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(11 => array('id' => 11, 'name' => 'No Warehouse Product'));
        shopProduct::seed(11, array('name' => 'No Warehouse Product'), array(
            101 => array('id' => 101, 'product_id' => 11, 'count' => 5),
        ));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), new waModel(), 100);
        $result = $service->apply(array(
            'product_ids' => array(11), 'operation' => 'stock', 'stock_id' => 0,
            'stock_mode' => 'increase', 'stock_value' => '2',
            'stock_type_filter' => 'all', 'confirm_apply' => 1,
        ));

        $this->assertSame(7.0, shopProduct::$saved[11]['skus'][101]['count']);
        $this->assertSame(0, $result['skipped']);
    }
}
