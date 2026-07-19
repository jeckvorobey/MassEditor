<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';

$rollback_model_path = __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/models/shopMasseditorPluginRollback.model.php';
$rollback_service_path = __DIR__ . '/../../wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginRollbackService.class.php';
if (is_file($rollback_model_path)) {
    require_once $rollback_model_path;
}
if (is_file($rollback_service_path)) {
    require_once $rollback_service_path;
}

if (class_exists('shopMasseditorPluginRollbackModel')) {
    class FakeRollbackModel extends shopMasseditorPluginRollbackModel
    {
        public $headers = array();
        public $itemBatches = array();
        public $eligible = null;
        public $latestEligible = null;
        public $snapshotItems = array();
        public $marked = array();
        public $resetMarks = array();
        public $deletedSnapshots = array();
        public $lockAcquired = 0;
        public $lockReleased = 0;
        public $lockAvailable = true;
        public $markResult = 1;

        public function createSnapshot(array $data)
        {
            $this->headers[] = $data;
            return 9;
        }

        public function insertSnapshotItems($rollback_id, array $rows, $batch_size = 20)
        {
            $this->itemBatches[] = array(
                'rollback_id' => $rollback_id,
                'rows' => $rows,
                'batch_size' => $batch_size,
            );
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

        public function findEligibleSnapshot($log_id, $user_id, $now)
        {
            return $this->eligible;
        }

        public function findLatestEligibleSnapshot($user_id, $now)
        {
            return $this->latestEligible;
        }

        public function getSnapshotItems($rollback_id, $batch_size = 100)
        {
            return $this->snapshotItems;
        }

        public function getSnapshotStats($rollback_id)
        {
            $total_bytes = 0;
            foreach ($this->snapshotItems as $row) {
                $total_bytes += strlen((string) ifset($row['before_state'], ''));
                $total_bytes += strlen((string) ifset($row['after_state'], ''));
            }

            return array('item_count' => count($this->snapshotItems), 'total_bytes' => $total_bytes);
        }

        public function markRolledBack($rollback_id, $user_id, $rolled_back_at)
        {
            $this->marked[] = compact('rollback_id', 'user_id', 'rolled_back_at');
            return $this->markResult;
        }

        public function resetRolledBack($rollback_id)
        {
            $this->resetMarks[] = (int) $rollback_id;
            return 1;
        }

        public function deleteSnapshot($rollback_id)
        {
            $this->deletedSnapshots[] = (int) $rollback_id;
        }
    }

    class FailingRollbackAuditLogService extends FakeLogService
    {
        public function log($action_type, $entity_count, $description = null, $user_id = null)
        {
            throw new RuntimeException('Rollback audit failed');
        }
    }
}

class RollbackServiceTest extends TestCase
{
    protected function setUp(): void
    {
        shopProduct::reset();
        shopProductModel::reset();
        $GLOBALS['fake_wa_system'] = new FakeWaSystem();
    }

    public function testRollbackClassesExistInProjectLayers(): void
    {
        $this->assertTrue(class_exists('shopMasseditorPluginRollbackModel'));
        $this->assertTrue(class_exists('shopMasseditorPluginRollbackService'));
    }

    /**
     * Given a price operation for two products with multiple SKUs.
     * When the rollback service captures state.
     * Then price and compare_price are loaded in one batch query and grouped by product.
     */
    public function testCapturePriceStateUsesOneBatchQueryAndOnlyAffectedSkuFields(): void
    {
        if (!class_exists('shopMasseditorPluginRollbackService')) {
            $this->fail('Rollback service is not implemented.');
        }

        $model = new waModel();
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11, 'price' => '10.0000', 'compare_price' => '12.0000'),
            array('id' => 201, 'product_id' => 12, 'price' => '20.0000', 'compare_price' => '25.0000'),
        )));
        $model->queueResponse('SELECT id, edit_datetime', new FakeQueryResult(array(
            array('id' => 11, 'edit_datetime' => '2026-07-19 09:00:00'),
            array('id' => 12, 'edit_datetime' => '2026-07-19 09:00:00'),
        )));
        $service = new shopMasseditorPluginRollbackService(null, $model, null, 100);

        $state = $service->captureState(array('operation' => 'price'), array(11, 12));

        $this->assertCount(2, $model->queries);
        $this->assertStringContainsString('price, compare_price', $model->queries[0]['sql']);
        $this->assertSame(array(11, 12), $model->queries[0]['params']);
        $this->assertSame(
            array('price' => '10.0000', 'compare_price' => '12.0000'),
            $state[11]['data']['skus'][101]
        );
        $this->assertSame(1, $state[11]['version']);
        $this->assertSame('price', $state[11]['operation']);
        $this->assertSame(11, $state[11]['product_id']);
        $this->assertSame('2026-07-19 09:00:00', $state[11]['data']['product']['edit_datetime']);
    }

    /**
     * Given product-field operations.
     * When state is captured.
     * Then only the whitelisted field and edit_datetime are selected in one query.
     */
    public function testCaptureProductFieldOperationsUsesWhitelistedColumns(): void
    {
        if (!class_exists('shopMasseditorPluginRollbackService')) {
            $this->fail('Rollback service is not implemented.');
        }

        $cases = array(
            'visibility' => array('field' => 'status', 'value' => '1'),
            'description' => array('field' => 'description', 'value' => '<p>Old</p>'),
            'url' => array('field' => 'url', 'value' => 'old-url'),
            'video' => array('field' => 'video_url', 'value' => 'http://youtu.be/old'),
        );

        foreach ($cases as $operation => $case) {
            $model = new waModel();
            $model->queueResponse('FROM shop_product', new FakeQueryResult(array(array(
                'id' => 11,
                $case['field'] => $case['value'],
                'edit_datetime' => '2026-07-19 12:00:00',
            ))));
            $service = new shopMasseditorPluginRollbackService(null, $model, null, 100);

            $state = $service->captureState(array('operation' => $operation), array(11));

            $this->assertCount(1, $model->queries, $operation);
            $this->assertStringContainsString($case['field'], $model->queries[0]['sql'], $operation);
            $this->assertSame($case['value'], $state[11]['data']['product'][$case['field']], $operation);
            $this->assertSame('2026-07-19 12:00:00', $state[11]['data']['product']['edit_datetime'], $operation);
        }
    }

    /**
     * Given tag, category and feature operations.
     * When state is captured.
     * Then relations are batch loaded and feature capture excludes SKU-level values.
     */
    public function testCaptureRelationOperationsUsesScopedBatchQueries(): void
    {
        if (!class_exists('shopMasseditorPluginRollbackService')) {
            $this->fail('Rollback service is not implemented.');
        }

        $tag_model = new waModel();
        $tag_model->queueResponse('FROM shop_product_tags', new FakeQueryResult(array(
            array('product_id' => 11, 'name' => 'sale'),
            array('product_id' => 11, 'name' => 'summer'),
        )));
        $tag_model->queueResponse('SELECT id, edit_datetime', new FakeQueryResult(array(
            array('id' => 11, 'edit_datetime' => '2026-07-19 09:00:00'),
        )));
        $tags = (new shopMasseditorPluginRollbackService(null, $tag_model, null, 100))
            ->captureState(array('operation' => 'tags'), array(11));
        $this->assertSame(array('sale', 'summer'), $tags[11]['data']['tags']);
        $this->assertCount(2, $tag_model->queries);
        $this->assertSame('2026-07-19 09:00:00', $tags[11]['data']['product']['edit_datetime']);

        $category_model = new waModel();
        $category_model->queueResponse('FROM shop_product WHERE', new FakeQueryResult(array(array(
            'id' => 11,
            'category_id' => 3,
            'edit_datetime' => '2026-07-19 12:00:00',
        ))));
        $category_model->queueResponse('FROM shop_category_products', new FakeQueryResult(array(
            array('product_id' => 11, 'category_id' => 3, 'sort' => 0),
            array('product_id' => 11, 'category_id' => 7, 'sort' => 2),
        )));
        $categories = (new shopMasseditorPluginRollbackService(null, $category_model, null, 100))
            ->captureState(array('operation' => 'categories'), array(11));
        $this->assertCount(2, $category_model->queries);
        $this->assertSame(3, $categories[11]['data']['product']['category_id']);
        $this->assertSame(array(3 => 0, 7 => 2), $categories[11]['data']['categories']);

        $feature_model = new waModel();
        $feature_model->queueResponse('FROM shop_product_features', new FakeQueryResult(array(
            array('product_id' => 11, 'feature_value_id' => 71),
            array('product_id' => 11, 'feature_value_id' => 72),
        )));
        $feature_model->queueResponse('SELECT id, edit_datetime', new FakeQueryResult(array(
            array('id' => 11, 'edit_datetime' => '2026-07-19 09:00:00'),
        )));
        $features = (new shopMasseditorPluginRollbackService(null, $feature_model, null, 100))
            ->captureState(array('operation' => 'features', 'feature_id' => 7), array(11));
        $this->assertCount(2, $feature_model->queries);
        $this->assertStringContainsString('sku_id IS NULL', $feature_model->queries[0]['sql']);
        $this->assertSame(array(71, 72), $features[11]['data']['feature_value_ids']);
        $this->assertSame(7, $features[11]['data']['feature_id']);
    }

    /**
     * Given warehouse stock where one row exists and another is absent.
     * When state is captured.
     * Then the snapshot preserves both the value and row-existence contract.
     */
    public function testCaptureWarehouseStockPreservesMissingRows(): void
    {
        if (!class_exists('shopMasseditorPluginRollbackService')) {
            $this->fail('Rollback service is not implemented.');
        }

        $model = new waModel();
        $model->queueResponse('FROM shop_product_skus', new FakeQueryResult(array(
            array('id' => 101, 'product_id' => 11),
            array('id' => 102, 'product_id' => 11),
        )));
        $model->queueResponse('FROM shop_product_stocks', new FakeQueryResult(array(
            array('sku_id' => 101, 'stock_id' => 3, 'product_id' => 11, 'count' => '5.000'),
        )));
        $model->queueResponse('SELECT id, edit_datetime', new FakeQueryResult(array(
            array('id' => 11, 'edit_datetime' => '2026-07-19 09:00:00'),
        )));
        $service = new shopMasseditorPluginRollbackService(null, $model, null, 100);

        $state = $service->captureState(array(
            'operation' => 'stock',
            'stock_id' => 3,
            'stock_ids' => array(3),
        ), array(11));

        $this->assertCount(3, $model->queries);
        $this->assertSame(array('exists' => true, 'count' => '5.000'), $state[11]['data']['stocks'][101][3]);
        $this->assertSame(array('exists' => false, 'count' => null), $state[11]['data']['stocks'][102][3]);
        $this->assertSame('2026-07-19 09:00:00', $state[11]['data']['product']['edit_datetime']);
    }

    public function testCaptureRejectsUnknownOperationAndTooManyProductsBeforeSql(): void
    {
        if (!class_exists('shopMasseditorPluginRollbackService')) {
            $this->fail('Rollback service is not implemented.');
        }

        $model = new waModel();
        $service = new shopMasseditorPluginRollbackService(null, $model, null, 2);

        try {
            $service->captureState(array('operation' => "price' OR '1'='1"), array(1));
            $this->fail('Unknown operation was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Неизвестная массовая операция.', $e->getMessage());
        }

        try {
            $service->captureState(array('operation' => 'visibility'), array(1, 2, 3));
            $this->fail('Oversized snapshot was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('не более 2 товаров', $e->getMessage());
        }

        $this->assertCount(0, $model->queries);
    }

    /**
     * Given matching before and after items for a successful operation.
     * When the snapshot is stored at a known time.
     * Then a private header expires in exactly three hours and JSON items are delegated in a bounded batch.
     */
    public function testStoreSnapshotWritesThreeHourHeaderAndPrivateItems(): void
    {
        if (!class_exists('shopMasseditorPluginRollbackService') || !class_exists('FakeRollbackModel')) {
            $this->fail('Rollback storage is not implemented.');
        }

        $rollback_model = new FakeRollbackModel();
        $service = new shopMasseditorPluginRollbackService($rollback_model, new waModel(), null, 100);
        $before = array(11 => array(
            'version' => 1,
            'operation' => 'visibility',
            'product_id' => 11,
            'data' => array('product' => array('status' => 1, 'edit_datetime' => '2026-07-19 09:00:00')),
        ));
        $after = $before;
        $after[11]['data']['product']['status'] = 0;

        $rollback_id = $service->storeSnapshot(
            55,
            7,
            'visibility',
            $before,
            $after,
            '2026-07-19 10:00:00'
        );

        $this->assertSame(9, $rollback_id);
        $this->assertSame(array(
            'log_id' => 55,
            'user_id' => 7,
            'action_type' => 'visibility',
            'snapshot_version' => 1,
            'expires_at' => '2026-07-19 13:00:00',
            'rolled_back_at' => null,
            'rolled_back_by' => null,
            'created_at' => '2026-07-19 10:00:00',
        ), $rollback_model->headers[0]);
        $this->assertSame(9, $rollback_model->itemBatches[0]['rollback_id']);
        $this->assertSame(20, $rollback_model->itemBatches[0]['batch_size']);
        $this->assertSame(11, $rollback_model->itemBatches[0]['rows'][0]['product_id']);
        $this->assertSame($before[11], json_decode($rollback_model->itemBatches[0]['rows'][0]['before_state'], true));
        $this->assertSame($after[11], json_decode($rollback_model->itemBatches[0]['rows'][0]['after_state'], true));
    }

    public function testStoreSnapshotRejectsMismatchedOrMalformedItemsBeforeInsert(): void
    {
        if (!class_exists('shopMasseditorPluginRollbackService') || !class_exists('FakeRollbackModel')) {
            $this->fail('Rollback storage is not implemented.');
        }

        $rollback_model = new FakeRollbackModel();
        $service = new shopMasseditorPluginRollbackService($rollback_model, new waModel(), null, 2);
        $valid = array(
            'version' => 1,
            'operation' => 'visibility',
            'product_id' => 11,
            'data' => array('product' => array('status' => 1, 'edit_datetime' => '2026-07-19 10:00:00')),
        );

        $invalid_cases = array(
            'missing_after' => array(array(11 => $valid), array()),
            'wrong_product' => array(array(11 => $valid), array(11 => array_merge($valid, array('product_id' => 12)))),
            'wrong_operation' => array(array(11 => $valid), array(11 => array_merge($valid, array('operation' => 'tags')))),
            'too_many' => array(
                array(11 => $valid, 12 => array_merge($valid, array('product_id' => 12)), 13 => array_merge($valid, array('product_id' => 13))),
                array(11 => $valid, 12 => array_merge($valid, array('product_id' => 12)), 13 => array_merge($valid, array('product_id' => 13))),
            ),
        );

        foreach ($invalid_cases as $label => $states) {
            try {
                $service->storeSnapshot(55, 7, 'visibility', $states[0], $states[1], '2026-07-19 10:00:00');
                $this->fail('Invalid snapshot was accepted: ' . $label);
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage(), $label);
            }
        }

        $this->assertCount(0, $rollback_model->headers);
        $this->assertCount(0, $rollback_model->itemBatches);
    }

    /**
     * Given more items than one write batch.
     * When the rollback model persists items.
     * Then it emits bounded multi-row INSERT statements instead of one query per product.
     */
    public function testRollbackModelInsertsSnapshotItemsInBoundedBatches(): void
    {
        if (!class_exists('shopMasseditorPluginRollbackModel')) {
            $this->fail('Rollback model is not implemented.');
        }

        $model = new shopMasseditorPluginRollbackModel();
        $rows = array();
        for ($product_id = 1; $product_id <= 21; $product_id++) {
            $rows[] = array(
                'product_id' => $product_id,
                'before_state' => '{"before":true}',
                'after_state' => '{"after":true}',
            );
        }

        $model->insertSnapshotItems(9, $rows, 20);

        $this->assertCount(2, $model->execs);
        $this->assertStringContainsString('INSERT INTO shop_masseditor_rollback_item', $model->execs[0]['sql']);
        $this->assertStringContainsString('s:before_state_0', $model->execs[0]['sql']);
        $this->assertSame(1, $model->execs[0]['params']['product_id_0']);
        $this->assertSame(20, $model->execs[0]['params']['product_id_19']);
        $this->assertSame(21, $model->execs[1]['params']['product_id_0']);
    }

    public function testRollbackModelReadsSnapshotItemsInBoundedKeysetBatches(): void
    {
        $model = new shopMasseditorPluginRollbackModel();
        $first_batch = array();
        for ($product_id = 1; $product_id <= 100; $product_id++) {
            $first_batch[] = array(
                'product_id' => $product_id,
                'before_state' => '{}',
                'after_state' => '{}',
            );
        }
        $model->queueResponse('FROM shop_masseditor_rollback_item', new FakeQueryResult($first_batch));
        $model->queueResponse('FROM shop_masseditor_rollback_item', new FakeQueryResult(array(array(
            'product_id' => 101,
            'before_state' => '{}',
            'after_state' => '{}',
        ))));

        $items = $model->getSnapshotItems(9, 100);

        $this->assertCount(101, $items);
        $this->assertCount(2, $model->queries);
        $this->assertStringContainsString('product_id > i:last_product_id', $model->queries[0]['sql']);
        $this->assertStringContainsString('LIMIT 100', $model->queries[0]['sql']);
        $this->assertSame(0, $model->queries[0]['params']['last_product_id']);
        $this->assertSame(100, $model->queries[1]['params']['last_product_id']);
    }

    public function testRollbackModelCalculatesPrivateSnapshotSizeBeforeLoadingJson(): void
    {
        $model = new shopMasseditorPluginRollbackModel();
        $expected = array('item_count' => '2', 'total_bytes' => '512');
        $model->queueResponse('COUNT(*) AS item_count', new FakeQueryResult(array($expected)));

        $this->assertSame($expected, $model->getSnapshotStats(9));
        $this->assertStringContainsString('SUM(OCTET_LENGTH(before_state)', $model->queries[0]['sql']);
        $this->assertSame(9, $model->queries[0]['params']['rollback_id']);
    }

    /**
     * Given two mutating MassEditor requests.
     * When they use the rollback model lock boundary.
     * Then a named database lock with a bounded timeout serializes eligibility and writes.
     */
    public function testRollbackModelUsesBoundedNamedMutationLock(): void
    {
        if (!class_exists('shopMasseditorPluginRollbackModel')) {
            $this->fail('Rollback model is not implemented.');
        }

        $model = new shopMasseditorPluginRollbackModel();
        $model->queueResponse('GET_LOCK', new FakeQueryResult(array(), 1));
        $model->queueResponse('RELEASE_LOCK', new FakeQueryResult(array(), 1));

        $this->assertTrue($model->acquireMutationLock(5));
        $this->assertTrue($model->releaseMutationLock());
        $this->assertCount(2, $model->queries);
        $this->assertStringContainsString('GET_LOCK(s:lock_name, i:timeout)', $model->queries[0]['sql']);
        $this->assertSame('shop_masseditor_mutation', $model->queries[0]['params']['lock_name']);
        $this->assertSame(5, $model->queries[0]['params']['timeout']);
        $this->assertStringContainsString('RELEASE_LOCK(s:lock_name)', $model->queries[1]['sql']);

        $busy_model = new shopMasseditorPluginRollbackModel();
        $busy_model->queueResponse('GET_LOCK', new FakeQueryResult(array(), 0));
        $this->assertFalse($busy_model->acquireMutationLock(999));
        $this->assertSame(10, $busy_model->queries[0]['params']['timeout']);
    }

    public function testRollbackModelPurgesExpiredPrivateItemsBeforeHeaders(): void
    {
        $model = new shopMasseditorPluginRollbackModel();

        $model->purgeExpired('2026-07-19 13:00:00');

        $this->assertCount(2, $model->execs);
        $this->assertStringContainsString('DELETE i FROM shop_masseditor_rollback_item i', $model->execs[0]['sql']);
        $this->assertStringContainsString('r.expires_at < s:now', $model->execs[0]['sql']);
        $this->assertSame('2026-07-19 13:00:00', $model->execs[0]['params']['now']);
        $this->assertStringContainsString('DELETE FROM shop_masseditor_rollback', $model->execs[1]['sql']);
    }

    public function testRollbackModelEligibilityQueryChecksLatestUserExpiryAndAction(): void
    {
        $model = new shopMasseditorPluginRollbackModel();
        $expected = array(
            'rollback_id' => 9,
            'log_id' => 55,
            'user_id' => 7,
            'action_type' => 'visibility',
            'entity_count' => 1,
        );
        $model->queueResponse('FROM shop_masseditor_rollback r', new FakeQueryResult(array($expected)));

        $actual = $model->findEligibleSnapshot(55, 7, '2026-07-19 12:00:00');

        $this->assertSame($expected, $actual);
        $this->assertCount(1, $model->queries);
        $query = $model->queries[0];
        $this->assertStringContainsString('r.rolled_back_at IS NULL', $query['sql']);
        $this->assertStringContainsString('r.expires_at >= s:now', $query['sql']);
        $this->assertStringContainsString('l.id = (SELECT MAX(id) FROM shop_masseditor_log)', $query['sql']);
        $this->assertStringContainsString('l.action_type = r.action_type', $query['sql']);
        $this->assertSame(55, $query['params']['log_id']);
        $this->assertSame(7, $query['params']['user_id']);
    }

    public function testLatestEligibleLogIdIsScopedToCurrentUser(): void
    {
        $rollback_model = new FakeRollbackModel();
        $rollback_model->latestEligible = array('log_id' => 55);
        $service = new shopMasseditorPluginRollbackService($rollback_model, new waModel(), new FakeLogService(), 100);

        $this->assertSame(55, $service->getAvailableLogId(7, '2026-07-19 12:00:00'));
        $rollback_model->latestEligible = null;
        $this->assertNull($service->getAvailableLogId(8, '2026-07-19 12:00:00'));
    }

    /**
     * Given the current user's latest unexpired visibility snapshot and unchanged after-state.
     * When rollback is explicitly confirmed.
     * Then old product values are restored, the snapshot is marked and one rollback audit is written.
     */
    public function testRollbackRestoresEligibleSnapshotAndWritesAudit(): void
    {
        $GLOBALS['fake_wa_system']->user = new FakeUser(7, true);
        $rollback_model = new FakeRollbackModel();
        $rollback_model->eligible = array(
            'rollback_id' => 9,
            'log_id' => 55,
            'user_id' => 7,
            'action_type' => 'visibility',
            'entity_count' => 1,
            'snapshot_version' => 1,
        );
        $before = array(
            'version' => 1,
            'operation' => 'visibility',
            'product_id' => 11,
            'data' => array('product' => array('status' => 1, 'edit_datetime' => '2026-07-19 10:00:00')),
        );
        $after = $before;
        $after['data']['product'] = array('status' => 0, 'edit_datetime' => '2026-07-19 11:00:00');
        $rollback_model->snapshotItems = array(array(
            'product_id' => 11,
            'before_state' => json_encode($before),
            'after_state' => json_encode($after),
        ));
        $model = new waModel();
        $model->queueResponse('FROM shop_product WHERE', new FakeQueryResult(array(array(
            'id' => 11,
            'status' => 0,
            'edit_datetime' => '2026-07-19 11:00:00',
        ))));
        shopProduct::seed(11, array('status' => 0, 'edit_datetime' => '2026-07-19 11:00:00'));
        $log = new FakeLogService();
        $service = new shopMasseditorPluginRollbackService($rollback_model, $model, $log, 100);

        $result = $service->rollback(55, true, '2026-07-19 12:00:00');

        $this->assertSame(1, $result['entity_count']);
        $this->assertSame(55, $result['log_id']);
        $this->assertSame(1, shopProduct::$saved[11]['status']);
        $this->assertSame('2026-07-19 10:00:00', shopProduct::$saved[11]['edit_datetime']);
        $this->assertCount(1, $rollback_model->marked);
        $this->assertSame(9, $rollback_model->marked[0]['rollback_id']);
        $this->assertSame(7, $rollback_model->marked[0]['user_id']);
        $this->assertCount(1, $log->logged);
        $this->assertSame('rollback', $log->logged[0]['action_type']);
        $this->assertStringContainsString('#55', $log->logged[0]['description']);
        $this->assertSame(1, $rollback_model->lockAcquired);
        $this->assertSame(1, $rollback_model->lockReleased);
    }

    public function testRollbackRejectsIneligibleOrUnconfirmedRequestBeforeWrites(): void
    {
        $GLOBALS['fake_wa_system']->user = new FakeUser(7, true);
        $rollback_model = new FakeRollbackModel();
        $log = new FakeLogService();
        $service = new shopMasseditorPluginRollbackService($rollback_model, new waModel(), $log, 100);

        foreach (array(0, -1, 1.5, "' OR '1'='1", '999999999999999999999') as $invalid_id) {
            try {
                $service->rollback($invalid_id, true, '2026-07-19 12:00:00');
                $this->fail('Invalid log ID was accepted.');
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }

        try {
            $service->rollback(55, false, '2026-07-19 12:00:00');
            $this->fail('Missing confirmation was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertNotSame('', $e->getMessage());
        }

        try {
            $service->rollback(55, true, '2026-07-19 12:00:00');
            $this->fail('Expired, foreign, non-latest or rolled-back snapshot was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('недоступна', $e->getMessage());
        }

        $this->assertCount(0, shopProduct::$saved);
        $this->assertCount(0, $rollback_model->marked);
        $this->assertCount(0, $log->logged);
    }

    public function testRollbackRejectsAfterStateConflictBeforeRestore(): void
    {
        $GLOBALS['fake_wa_system']->user = new FakeUser(7, true);
        $rollback_model = new FakeRollbackModel();
        $rollback_model->eligible = array(
            'rollback_id' => 9,
            'log_id' => 55,
            'user_id' => 7,
            'action_type' => 'visibility',
            'entity_count' => 1,
            'snapshot_version' => 1,
        );
        $before = array('version' => 1, 'operation' => 'visibility', 'product_id' => 11, 'data' => array(
            'product' => array('status' => 1, 'edit_datetime' => '2026-07-19 10:00:00'),
        ));
        $after = array('version' => 1, 'operation' => 'visibility', 'product_id' => 11, 'data' => array(
            'product' => array('status' => 0, 'edit_datetime' => '2026-07-19 11:00:00'),
        ));
        $rollback_model->snapshotItems = array(array(
            'product_id' => 11,
            'before_state' => json_encode($before),
            'after_state' => json_encode($after),
        ));
        $model = new waModel();
        $model->queueResponse('FROM shop_product WHERE', new FakeQueryResult(array(array(
            'id' => 11,
            'status' => -1,
            'edit_datetime' => '2026-07-19 11:30:00',
        ))));
        shopProduct::seed(11, array('status' => -1, 'edit_datetime' => '2026-07-19 11:30:00'));
        $service = new shopMasseditorPluginRollbackService($rollback_model, $model, new FakeLogService(), 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('изменены после массовой операции');
        try {
            $service->rollback(55, true, '2026-07-19 12:00:00');
        } finally {
            $this->assertCount(0, shopProduct::$saved);
            $this->assertCount(0, $rollback_model->marked);
        }
    }

    public function testRollbackChecksAdminAndEveryProductRightBeforeRestore(): void
    {
        $rollback_model = new FakeRollbackModel();
        $service = new shopMasseditorPluginRollbackService($rollback_model, new waModel(), new FakeLogService(), 100);
        $GLOBALS['fake_wa_system']->user = new FakeUser(7, false);

        try {
            $service->rollback(55, true, '2026-07-19 12:00:00');
            $this->fail('Non-admin rollback was accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Недостаточно прав', $e->getMessage());
        }

        $GLOBALS['fake_wa_system']->user = new FakeUser(7, true);
        $rollback_model->eligible = array(
            'rollback_id' => 9, 'log_id' => 55, 'user_id' => 7, 'action_type' => 'visibility',
            'entity_count' => 1, 'snapshot_version' => 1,
        );
        $item = array('version' => 1, 'operation' => 'visibility', 'product_id' => 11, 'data' => array(
            'product' => array('status' => 0, 'edit_datetime' => '2026-07-19 11:00:00'),
        ));
        $rollback_model->snapshotItems = array(array(
            'product_id' => 11, 'before_state' => json_encode($item), 'after_state' => json_encode($item),
        ));
        shopProduct::denyRightsFor(11);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Недостаточно прав для изменения выбранного товара');
        $service->rollback(55, true, '2026-07-19 12:00:00');
    }

    public function testRestoreStateSupportsSkuTagsCategoriesFeaturesAndWarehouseStocks(): void
    {
        $rollback_model = new FakeRollbackModel();
        $model = new waModel();
        $service = new shopMasseditorPluginRollbackService($rollback_model, $model, new FakeLogService(), 100);

        shopProduct::seed(11, array(), array(
            101 => array('id' => 101, 'price' => 99, 'compare_price' => 120, 'available' => 1, 'count' => 5),
        ));
        $service->restoreState(array(11 => array(
            'version' => 1,
            'operation' => 'price',
            'product_id' => 11,
            'data' => array(
                'product' => array('edit_datetime' => '2026-07-19 10:00:00'),
                'skus' => array(101 => array('price' => '10.0000', 'compare_price' => '12.0000')),
            ),
        )), array('operation' => 'price'));
        $this->assertSame('10.0000', shopProduct::$saved[11]['skus'][101]['price']);
        $this->assertSame('12.0000', shopProduct::$saved[11]['skus'][101]['compare_price']);

        shopProduct::reset();
        shopProduct::seed(11);
        $model->execs = array();
        $service->restoreState(array(11 => array(
            'version' => 1, 'operation' => 'tags', 'product_id' => 11,
            'data' => array(
                'product' => array('edit_datetime' => '2026-07-19 10:00:00'),
                'tags' => array('sale', 'summer'),
            ),
        )), array('operation' => 'tags'));
        $this->assertSame(array('sale', 'summer'), shopProduct::$saved[11]['tags']);

        shopProduct::reset();
        shopProduct::seed(11);
        $model->execs = array();
        $service->restoreState(array(11 => array(
            'version' => 1, 'operation' => 'categories', 'product_id' => 11,
            'data' => array(
                'product' => array('category_id' => 3, 'edit_datetime' => '2026-07-19 10:00:00'),
                'categories' => array(3 => 0, 7 => 2),
            ),
        )), array('operation' => 'categories'));
        $this->assertSame(3, shopProduct::$saved[11]['category_id']);
        $this->assertStringContainsString('DELETE FROM shop_category_products', $model->execs[0]['sql']);
        $this->assertStringContainsString('(category_id, product_id, sort)', $model->execs[1]['sql']);
        $this->assertSame(2, $model->execs[1]['params']['sort_1']);

        $model->execs = array();
        $service->restoreState(array(11 => array(
            'version' => 1, 'operation' => 'features', 'product_id' => 11,
            'data' => array(
                'product' => array('edit_datetime' => '2026-07-19 10:00:00'),
                'feature_id' => 7,
                'feature_value_ids' => array(71, 72),
            ),
        )), array('operation' => 'features'));
        $this->assertCount(3, $model->execs);
        $this->assertStringContainsString('sku_id IS NULL', $model->execs[0]['sql']);
        $this->assertStringContainsString('INSERT INTO shop_product_features', $model->execs[1]['sql']);

        $model->execs = array();
        shopProductModel::reset();
        $service->restoreState(array(11 => array(
            'version' => 1, 'operation' => 'stock', 'product_id' => 11,
            'data' => array(
                'product' => array('edit_datetime' => '2026-07-19 10:00:00'),
                'stocks' => array(
                    101 => array(
                        3 => array('exists' => true, 'count' => '5.000'),
                        4 => array('exists' => false, 'count' => null),
                    ),
                ),
            ),
        )), array('operation' => 'stock', 'stock_id' => 3, 'stock_ids' => array(3, 4)));
        $this->assertCount(3, $model->execs);
        $this->assertStringContainsString('DELETE FROM shop_product_stocks', $model->execs[0]['sql']);
        $this->assertStringContainsString('REPLACE INTO shop_product_stocks', $model->execs[1]['sql']);
        $this->assertSame(array(11), shopProductModel::$corrected);
    }

    public function testRollbackCompensatesToAfterStateWhenAuditFails(): void
    {
        $GLOBALS['fake_wa_system']->user = new FakeUser(7, true);
        $rollback_model = new FakeRollbackModel();
        $rollback_model->eligible = array(
            'rollback_id' => 9, 'log_id' => 55, 'user_id' => 7, 'action_type' => 'visibility',
            'entity_count' => 1, 'snapshot_version' => 1,
        );
        $before = array('version' => 1, 'operation' => 'visibility', 'product_id' => 11, 'data' => array(
            'product' => array('status' => 1, 'edit_datetime' => '2026-07-19 10:00:00'),
        ));
        $after = array('version' => 1, 'operation' => 'visibility', 'product_id' => 11, 'data' => array(
            'product' => array('status' => 0, 'edit_datetime' => '2026-07-19 11:00:00'),
        ));
        $rollback_model->snapshotItems = array(array(
            'product_id' => 11, 'before_state' => json_encode($before), 'after_state' => json_encode($after),
        ));
        $model = new waModel();
        $model->queueResponse('FROM shop_product WHERE', new FakeQueryResult(array(array(
            'id' => 11, 'status' => 0, 'edit_datetime' => '2026-07-19 11:00:00',
        ))));
        shopProduct::seed(11, array('status' => 0, 'edit_datetime' => '2026-07-19 11:00:00'));
        $service = new shopMasseditorPluginRollbackService(
            $rollback_model,
            $model,
            new FailingRollbackAuditLogService(),
            100
        );

        try {
            $service->rollback(55, true, '2026-07-19 12:00:00');
            $this->fail('Audit failure was not propagated.');
        } catch (RuntimeException $e) {
            $this->assertSame('Rollback audit failed', $e->getMessage());
        }

        $this->assertSame(0, shopProduct::$saved[11]['status']);
        $this->assertSame('2026-07-19 11:00:00', shopProduct::$saved[11]['edit_datetime']);
        $this->assertSame(array(9), $rollback_model->resetMarks);
        $this->assertSame(1, $rollback_model->lockReleased);
    }

    public function testRestoreRejectsUnexpectedSnapshotFieldsBeforeSave(): void
    {
        $service = new shopMasseditorPluginRollbackService(new FakeRollbackModel(), new waModel(), new FakeLogService(), 100);
        shopProduct::seed(11, array('status' => 0));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Снимок для отката повреждён');
        try {
            $service->restoreState(array(11 => array(
                'version' => 1, 'operation' => 'visibility', 'product_id' => 11,
                'data' => array('product' => array('status' => 1, 'password' => 'overwrite')),
            )), array('operation' => 'visibility'));
        } finally {
            $this->assertCount(0, shopProduct::$saved);
        }
    }

    public function testRestoreRejectsLooseEnvelopeAndFieldsFromAnotherOperation(): void
    {
        $invalid_items = array(
            array(
                'version' => '1evil', 'operation' => 'visibility', 'product_id' => 11,
                'data' => array('product' => array('status' => 1, 'edit_datetime' => '2026-07-19 10:00:00')),
            ),
            array(
                'version' => 1, 'operation' => 'visibility', 'product_id' => '11evil',
                'data' => array('product' => array('status' => 1, 'edit_datetime' => '2026-07-19 10:00:00')),
            ),
            array(
                'version' => 1, 'operation' => 'visibility', 'product_id' => 11,
                'data' => array('product' => array(
                    'status' => 1,
                    'description' => 'must not be restored',
                    'edit_datetime' => '2026-07-19 10:00:00',
                )),
            ),
        );

        foreach ($invalid_items as $item) {
            shopProduct::reset();
            shopProduct::seed(11, array('status' => 0));
            $service = new shopMasseditorPluginRollbackService(
                new FakeRollbackModel(),
                new waModel(),
                new FakeLogService(),
                100
            );
            try {
                $service->restoreState(array(11 => $item), array('operation' => 'visibility'));
                $this->fail('Loosely validated snapshot was restored.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Снимок для отката повреждён', $e->getMessage());
            }
            $this->assertCount(0, shopProduct::$saved);
        }
    }

    public function testRestoreRejectsNonBooleanWarehouseRowExistenceBeforeSql(): void
    {
        $model = new waModel();
        $service = new shopMasseditorPluginRollbackService(
            new FakeRollbackModel(),
            $model,
            new FakeLogService(),
            100
        );
        shopProduct::seed(11);
        $item = array(
            'version' => 1,
            'operation' => 'stock',
            'product_id' => 11,
            'data' => array(
                'product' => array('edit_datetime' => '2026-07-19 10:00:00'),
                'stocks' => array(101 => array(3 => array('exists' => '0', 'count' => null))),
            ),
        );

        try {
            $service->restoreState(array(11 => $item), array('operation' => 'stock', 'stock_id' => 3));
            $this->fail('String warehouse existence marker was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Снимок для отката повреждён', $e->getMessage());
        }
        $this->assertCount(0, $model->execs);
    }
}
