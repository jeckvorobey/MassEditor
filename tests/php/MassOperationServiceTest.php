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
            'name' => 'Material',
            'type' => 'varchar',
            'selectable' => 1,
            'multiple' => 0,
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

        $this->assertStringContainsString('DELETE FROM shop_product_features', $model->execs[1]['sql']);
        $this->assertStringContainsString('INSERT INTO shop_product_features', $model->execs[2]['sql']);
        $this->assertSame(77, $model->execs[2]['params']['feature_value_id']);

        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 8,
            'name' => 'Color',
            'type' => 'color',
            'selectable' => 1,
            'multiple' => 0,
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

        $this->assertStringContainsString('DELETE FROM shop_product_features', $model->execs[1]['sql']);
        $this->assertStringContainsString('INSERT INTO shop_product_features', $model->execs[2]['sql']);
        $this->assertSame(78, $model->execs[2]['params']['feature_value_id']);
    }

    public function testApplyFeatureOperationCreatesMissingBasicValueBeforeAssigning(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Feature Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 7,
            'name' => 'Material',
            'type' => 'varchar',
            'selectable' => 0,
            'multiple' => 0,
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

        $this->assertStringContainsString('DELETE FROM shop_product_features', $model->execs[1]['sql']);
        $this->assertStringContainsString('INSERT INTO shop_feature_values_varchar', $model->execs[2]['sql']);
        $this->assertSame(7, $model->execs[2]['params']['feature_id']);
        $this->assertSame('linen', $model->execs[2]['params']['value']);
        $this->assertStringContainsString('INSERT INTO shop_product_features', $model->execs[3]['sql']);
        $this->assertSame(88, $model->execs[3]['params']['feature_value_id']);
    }

    public function testApplyFeatureOperationValidatesNumericBasicValue(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Feature Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 9,
            'name' => 'Weight',
            'type' => 'double',
            'selectable' => 0,
            'multiple' => 0,
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
            'name' => 'WiFi',
            'type' => 'boolean',
            'selectable' => 1,
            'multiple' => 0,
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

        $this->assertStringContainsString('DELETE FROM shop_product_features', $model->execs[1]['sql']);
        $this->assertStringContainsString('INSERT INTO shop_product_features', $model->execs[2]['sql']);
        $this->assertSame(101, $model->execs[2]['params']['feature_value_id']);
    }

    public function testApplyFeatureOperationSupportsDimensionType(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Dim Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 21,
            'name' => 'Height',
            'type' => 'dimension.length',
            'selectable' => 0,
            'multiple' => 0,
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

        $this->assertStringContainsString('DELETE FROM shop_product_features', $model->execs[1]['sql']);
        $this->assertStringContainsString('INSERT INTO shop_product_features', $model->execs[2]['sql']);
        $this->assertSame(102, $model->execs[2]['params']['feature_value_id']);
    }

    public function testApplyFeatureOperationSupportsColorType(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Color Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 22,
            'name' => 'Color',
            'type' => 'color',
            'selectable' => 1,
            'multiple' => 0,
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

        $this->assertStringContainsString('DELETE FROM shop_product_features', $model->execs[1]['sql']);
        $this->assertStringContainsString('INSERT INTO shop_product_features', $model->execs[2]['sql']);
        $this->assertSame(103, $model->execs[2]['params']['feature_value_id']);
    }

    public function testApplyFeatureOperationSupportsSelectType(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Select Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 23,
            'name' => 'Size',
            'type' => 'select',
            'selectable' => 1,
            'multiple' => 0,
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

        $this->assertStringContainsString('DELETE FROM shop_product_features', $model->execs[1]['sql']);
        $this->assertStringContainsString('INSERT INTO shop_product_features', $model->execs[2]['sql']);
        $this->assertSame(104, $model->execs[2]['params']['feature_value_id']);
    }

    public function testApplyFeatureOperationSupportsRadioType(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Radio Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 24,
            'name' => 'Material',
            'type' => 'radio',
            'selectable' => 1,
            'multiple' => 0,
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

        $this->assertStringContainsString('DELETE FROM shop_product_features', $model->execs[1]['sql']);
        $this->assertStringContainsString('INSERT INTO shop_product_features', $model->execs[2]['sql']);
        $this->assertSame(105, $model->execs[2]['params']['feature_value_id']);
    }

    public function testApplyFeatureOperationSupportsRangeType(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Range Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 25,
            'name' => 'Power',
            'type' => 'range',
            'selectable' => 0,
            'multiple' => 0,
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

        $this->assertStringContainsString('DELETE FROM shop_product_features', $model->execs[1]['sql']);
        $this->assertStringContainsString('INSERT INTO shop_product_features', $model->execs[2]['sql']);
        $this->assertSame(106, $model->execs[2]['params']['feature_value_id']);
    }

    public function testApplyFeatureOperationClearWorksForAllTypes(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Clear Product'));

        foreach (array('boolean', 'dimension.length', 'color', 'select', 'radio', 'range') as $type) {
            $model = new waModel();
            $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
                'id' => 30,
                'name' => 'Test Feature',
                'type' => $type,
                'selectable' => 0,
                'multiple' => 0,
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

            $this->assertStringContainsString('DELETE FROM shop_product_features', $model->execs[1]['sql']);
            $this->assertCount(3, $model->execs, "Clear for type {$type} should have START TRANSACTION, DELETE, COMMIT");
            $this->assertStringContainsString('START TRANSACTION', $model->execs[0]['sql']);
            $this->assertStringContainsString('COMMIT', $model->execs[2]['sql']);
        }
    }

    public function testApplyFeatureOperationRejectsUnknownTypeWithFallback(): void
    {
        $selection = new FakeSelectionService();
        $selection->products = array(15 => array('id' => 15, 'name' => 'Unknown Product'));
        $model = new waModel();
        $model->queueResponse('FROM shop_feature WHERE', new FakeQueryResult(array(array(
            'id' => 26,
            'name' => 'Custom',
            'type' => 'custom_future_type',
            'selectable' => 0,
            'multiple' => 0,
        ))));
        $model->queueResponse('FROM shop_feature_values_varchar', new FakeQueryResult(array(array('id' => 107))));

        $service = new shopMasseditorPluginMassOperationService($selection, new FakeLogService(), $model, 100);
        $service->apply(array(
            'product_ids' => array(15),
            'operation' => 'features',
            'feature_id' => 26,
            'feature_mode' => 'set',
            'feature_value' => 'test value',
            'confirm_apply' => 1,
        ));

        $this->assertStringContainsString('DELETE FROM shop_product_features', $model->execs[1]['sql']);
        $this->assertStringContainsString('INSERT INTO shop_product_features', $model->execs[2]['sql']);
        $this->assertSame(107, $model->execs[2]['params']['feature_value_id']);
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
}
