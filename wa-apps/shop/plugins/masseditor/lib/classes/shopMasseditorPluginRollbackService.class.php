<?php

class shopMasseditorPluginRollbackService
{
    const SNAPSHOT_VERSION = 1;
    const DEFAULT_OPERATION_LIMIT = 100;
    const MAX_OPERATION_LIMIT = 1000;
    const STORE_BATCH_SIZE = 20;
    const MAX_ITEM_BYTES = 16777215;
    const MAX_SNAPSHOT_BYTES = 67108864;

    private $rollback_model;
    private $model;
    private $log_service;
    private $operation_limit;
    private $language;

    public function __construct(
        shopMasseditorPluginRollbackModel $rollback_model = null,
        waModel $model = null,
        shopMasseditorPluginLogService $log_service = null,
        $operation_limit = null,
        $language = null
    ) {
        $this->rollback_model = $rollback_model ?: new shopMasseditorPluginRollbackModel();
        $this->model = $model ?: new waModel();
        $this->log_service = $log_service ?: new shopMasseditorPluginLogService();
        $this->operation_limit = $this->normalizeOperationLimit($operation_limit);
        $this->language = $language;
    }

    public function captureState(array $request, array $raw_product_ids)
    {
        $operation = isset($request['operation']) ? trim((string) $request['operation']) : '';
        if (!in_array($operation, shopMasseditorPluginMassOperationService::ALL_OPERATIONS, true)) {
            throw new InvalidArgumentException($this->t('unknown_operation'));
        }

        $product_ids = $this->normalizeProductIds($raw_product_ids);
        if (!$product_ids) {
            throw new InvalidArgumentException($this->t('validation_select_product'));
        }
        if (count($product_ids) > $this->operation_limit) {
            throw new InvalidArgumentException(
                $this->t('limit_error_prefix') . $this->operation_limit . $this->t('limit_error_suffix')
            );
        }

        $state = $this->initializeState($operation, $product_ids);
        if (in_array($operation, array('visibility', 'description', 'url', 'video'), true)) {
            $state = $this->captureProductFieldState($state, $operation, $product_ids);
        } elseif (in_array($operation, array('price', 'compare_price', 'availability'), true)) {
            $state = $this->captureSkuState($state, $operation, $product_ids);
        } elseif ($operation === 'stock') {
            $stock_id = isset($request['stock_id']) ? (int) $request['stock_id'] : 0;
            $state = $stock_id > 0
                ? $this->captureWarehouseStockState($state, $request, $product_ids)
                : $this->captureSkuState($state, $operation, $product_ids);
        } elseif ($operation === 'tags') {
            $state = $this->captureTagsState($state, $product_ids);
        } elseif ($operation === 'categories') {
            $state = $this->captureCategoriesState($state, $product_ids);
        } else {
            $state = $this->captureFeaturesState($state, $request, $product_ids);
        }

        return $this->captureEditDatetimeState($state, $product_ids);
    }

    public function acquireMutationLock($timeout = 5)
    {
        return $this->rollback_model->acquireMutationLock($timeout);
    }

    public function releaseMutationLock()
    {
        return $this->rollback_model->releaseMutationLock();
    }

    public function getAvailableLogId($user_id, $now = null)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return null;
        }
        $now = $this->normalizeDatetime($now);
        $row = $this->rollback_model->findLatestEligibleSnapshot($user_id, $now);

        return $row && !empty($row['log_id']) ? (int) $row['log_id'] : null;
    }

    public function purgeExpiredSnapshots($now = null)
    {
        return $this->rollback_model->purgeExpired($this->normalizeDatetime($now));
    }

    public function rollback($raw_log_id, $confirmed, $now = null)
    {
        $this->assertAdminRights();
        $log_id = $this->normalizeStrictPositiveInteger($raw_log_id, 'rollback_invalid_request');
        if (!($confirmed === true || $confirmed === 1 || $confirmed === '1')) {
            throw new InvalidArgumentException($this->t('rollback_confirm_required'));
        }

        $now = $this->normalizeDatetime($now);
        $user = wa()->getUser();
        $user_id = $user ? (int) $user->getId() : 0;
        $lock_acquired = false;
        $restore_started = false;
        $marked = false;
        $rollback_id = 0;
        $request = array();
        $after_state = array();

        try {
            if (!$this->acquireMutationLock(5)) {
                throw new RuntimeException($this->t('rollback_busy'));
            }
            $lock_acquired = true;

            $snapshot = $this->rollback_model->findEligibleSnapshot($log_id, $user_id, $now);
            if (!$snapshot) {
                throw new InvalidArgumentException($this->t('rollback_unavailable'));
            }
            $rollback_id = isset($snapshot['rollback_id']) ? (int) $snapshot['rollback_id'] : 0;
            $operation = isset($snapshot['action_type']) ? (string) $snapshot['action_type'] : '';
            if ($rollback_id <= 0
                || (int) ifset($snapshot['log_id'], 0) !== $log_id
                || (int) ifset($snapshot['user_id'], 0) !== $user_id
                || (int) ifset($snapshot['snapshot_version'], 0) !== self::SNAPSHOT_VERSION
                || !in_array($operation, shopMasseditorPluginMassOperationService::ALL_OPERATIONS, true)
            ) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }

            $states = $this->loadSnapshotStates($snapshot);
            $before_state = $states['before'];
            $after_state = $states['after'];
            $this->assertProductRights(array_keys($before_state));
            $request = $this->requestFromState($operation, $after_state);
            $current_state = $this->captureState($request, array_keys($after_state));
            if ($this->canonicalizeState($current_state) !== $this->canonicalizeState($after_state)) {
                throw new InvalidArgumentException($this->t('rollback_conflict'));
            }

            $restore_started = true;
            $this->restoreState($before_state, $request);
            if ((int) $this->rollback_model->markRolledBack($rollback_id, $user_id, $now) !== 1) {
                throw new InvalidArgumentException($this->t('rollback_unavailable'));
            }
            $marked = true;

            $entity_count = count($before_state);
            $this->log_service->log(
                'rollback',
                $entity_count,
                sprintf($this->t('rollback_log_description'), $log_id),
                $user_id
            );

            return array(
                'log_id' => $log_id,
                'entity_count' => $entity_count,
                'message' => $this->t('rollback_success'),
            );
        } catch (Exception $e) {
            if ($restore_started && $after_state) {
                try {
                    $this->restoreState($after_state, $request);
                    if ($marked && $rollback_id > 0) {
                        $this->rollback_model->resetRolledBack($rollback_id);
                    }
                } catch (Exception $compensation_error) {
                    waLog::log(
                        get_class($compensation_error) . ': ' . $compensation_error->getMessage()
                        . "\n" . $compensation_error->getTraceAsString(),
                        'shop/plugins/masseditor.log'
                    );
                }
            }
            throw $e;
        } finally {
            if ($lock_acquired) {
                $this->releaseMutationLock();
            }
        }
    }

    public function restoreState(array $state, array $request)
    {
        if (!$state || count($state) > $this->operation_limit) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
        $operation = isset($request['operation']) ? (string) $request['operation'] : '';
        if (!in_array($operation, shopMasseditorPluginMassOperationService::ALL_OPERATIONS, true)) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }

        $this->assertProductRights(array_keys($state));
        foreach (array_chunk($state, 20, true) as $chunk) {
            foreach ($chunk as $product_id => $item) {
                $product_id = (int) $product_id;
                $this->assertValidSnapshotItem($item, $operation, $product_id);
                $data = $item['data'];
                if (in_array($operation, array('visibility', 'description', 'url', 'video'), true)) {
                    $this->restoreProductFields($product_id, $data, $operation);
                } elseif (in_array($operation, array('price', 'compare_price', 'availability'), true)
                    || ($operation === 'stock' && isset($data['skus']))
                ) {
                    $this->restoreSkus($product_id, $data, $operation);
                } elseif ($operation === 'stock') {
                    $this->restoreWarehouseStocks($product_id, $data);
                } elseif ($operation === 'tags') {
                    $this->restoreTags($product_id, $data);
                } elseif ($operation === 'categories') {
                    $this->restoreCategories($product_id, $data);
                } else {
                    $this->restoreFeature($product_id, $data);
                }
            }
        }
    }

    public function storeSnapshot(
        $log_id,
        $user_id,
        $operation,
        array $before_state,
        array $after_state,
        $created_at = null
    ) {
        $log_id = (int) $log_id;
        $user_id = (int) $user_id;
        $operation = trim((string) $operation);
        if ($log_id <= 0 || $user_id <= 0 || !in_array($operation, shopMasseditorPluginMassOperationService::ALL_OPERATIONS, true)) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }

        $rows = $this->buildStorageRows($operation, $before_state, $after_state);
        $created_at = $created_at === null ? date('Y-m-d H:i:s') : trim((string) $created_at);
        $created_timestamp = strtotime($created_at);
        if ($created_timestamp === false || date('Y-m-d H:i:s', $created_timestamp) !== $created_at) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }

        $rollback_id = (int) $this->rollback_model->createSnapshot(array(
            'log_id' => $log_id,
            'user_id' => $user_id,
            'action_type' => $operation,
            'snapshot_version' => self::SNAPSHOT_VERSION,
            'expires_at' => date('Y-m-d H:i:s', $created_timestamp + 10800),
            'rolled_back_at' => null,
            'rolled_back_by' => null,
            'created_at' => $created_at,
        ));
        if ($rollback_id <= 0) {
            throw new RuntimeException($this->t('rollback_snapshot_save_failed'));
        }

        try {
            $this->rollback_model->insertSnapshotItems(
                $rollback_id,
                $rows,
                self::STORE_BATCH_SIZE
            );
        } catch (Exception $e) {
            $this->rollback_model->deleteSnapshot($rollback_id);
            throw $e;
        }

        return $rollback_id;
    }

    private function loadSnapshotStates(array $snapshot)
    {
        $rollback_id = (int) $snapshot['rollback_id'];
        $operation = (string) $snapshot['action_type'];
        $expected_count = isset($snapshot['entity_count']) ? (int) $snapshot['entity_count'] : 0;
        $stats = $this->rollback_model->getSnapshotStats($rollback_id);
        $item_count = isset($stats['item_count']) ? (int) $stats['item_count'] : 0;
        $total_bytes = isset($stats['total_bytes']) ? (int) $stats['total_bytes'] : 0;
        if ($item_count <= 0
            || $item_count > $this->operation_limit
            || $total_bytes <= 0
            || $total_bytes > self::MAX_SNAPSHOT_BYTES
            || ($expected_count > 0 && $item_count !== $expected_count)
        ) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
        $rows = $this->rollback_model->getSnapshotItems($rollback_id, 100);
        if (count($rows) !== $item_count) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }

        $before_state = array();
        $after_state = array();
        foreach ($rows as $row) {
            $product_id = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            $before = isset($row['before_state']) ? json_decode((string) $row['before_state'], true) : null;
            $after = isset($row['after_state']) ? json_decode((string) $row['after_state'], true) : null;
            if ($product_id <= 0 || isset($before_state[$product_id]) || !is_array($before) || !is_array($after)) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }
            $this->assertValidSnapshotItem($before, $operation, $product_id);
            $this->assertValidSnapshotItem($after, $operation, $product_id);
            $before_state[$product_id] = $before;
            $after_state[$product_id] = $after;
        }
        ksort($before_state, SORT_NUMERIC);
        ksort($after_state, SORT_NUMERIC);

        return array('before' => $before_state, 'after' => $after_state);
    }

    private function requestFromState($operation, array $state)
    {
        $request = array('operation' => $operation);
        $first = reset($state);
        $data = isset($first['data']) && is_array($first['data']) ? $first['data'] : array();
        if ($operation === 'features') {
            $request['feature_id'] = isset($data['feature_id']) ? (int) $data['feature_id'] : 0;
        } elseif ($operation === 'stock' && isset($data['stocks']) && is_array($data['stocks'])) {
            $stock_ids = array();
            foreach ($state as $item) {
                $stocks_by_sku = isset($item['data']['stocks']) && is_array($item['data']['stocks'])
                    ? $item['data']['stocks']
                    : array();
                foreach ($stocks_by_sku as $stocks) {
                    foreach (array_keys($stocks) as $stock_id) {
                        $stock_id = (int) $stock_id;
                        if ($stock_id > 0) {
                            $stock_ids[$stock_id] = $stock_id;
                        }
                    }
                }
            }
            sort($stock_ids, SORT_NUMERIC);
            $request['stock_ids'] = array_values($stock_ids);
            $request['stock_id'] = $stock_ids ? (int) reset($stock_ids) : 0;
        } elseif ($operation === 'stock') {
            $request['stock_id'] = 0;
            $request['stock_ids'] = array();
        }

        return $request;
    }

    private function canonicalizeState(array $state)
    {
        ksort($state, SORT_NUMERIC);
        foreach ($state as &$item) {
            $item = $this->sortRecursive($item);
        }
        unset($item);

        return $state;
    }

    private function sortRecursive($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as &$item) {
            $item = $this->sortRecursive($item);
        }
        unset($item);
        if ($value && array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value, SORT_NATURAL);
        }

        return $value;
    }

    private function assertProductRights(array $product_ids)
    {
        foreach ($product_ids as $product_id) {
            $product_id = (int) $product_id;
            if ($product_id <= 0) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }
            $product = new shopProduct($product_id);
            if (!$product->checkRights()) {
                throw new RuntimeException($this->t('product_edit_denied'));
            }
        }
    }

    private function restoreProductFields($product_id, array $data, $operation)
    {
        $field_map = array(
            'visibility' => 'status',
            'description' => 'description',
            'url' => 'url',
            'video' => 'video_url',
        );
        $allowed_fields = array($field_map[$operation], 'edit_datetime');
        $product = new shopProduct($product_id);
        foreach ($data['product'] as $field => $value) {
            if (!in_array($field, $allowed_fields, true)) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }
            $product[$field] = $value;
        }
        if (!$product->save()) {
            throw new RuntimeException($this->t('rollback_restore_failed'));
        }
        $this->restoreEditDatetime($product_id, $data);
    }

    private function restoreSkus($product_id, array $data, $operation)
    {
        if (!isset($data['skus']) || !is_array($data['skus'])) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
        $allowed_by_operation = array(
            'price' => array('price', 'compare_price'),
            'compare_price' => array('compare_price'),
            'availability' => array('available'),
            'stock' => array('count'),
        );
        $allowed_fields = $allowed_by_operation[$operation];
        $product = new shopProduct($product_id);
        $skus = $product->getSkus();
        foreach ($data['skus'] as $raw_sku_id => $sku_state) {
            $sku_id = (int) $raw_sku_id;
            if ($sku_id <= 0 || !isset($skus[$sku_id]) || !is_array($sku_state)) {
                throw new InvalidArgumentException($this->t('rollback_conflict'));
            }
            foreach ($sku_state as $field => $value) {
                if (!in_array($field, $allowed_fields, true)) {
                    throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
                }
                $skus[$sku_id][$field] = $value;
            }
        }
        $product['skus'] = $skus;
        if (!$product->save()) {
            throw new RuntimeException($this->t('rollback_restore_failed'));
        }
        $this->restoreEditDatetime($product_id, $data);
        if ($operation === 'stock') {
            (new shopProductModel())->correct($product_id);
        }
    }

    private function restoreWarehouseStocks($product_id, array $data)
    {
        if (!isset($data['stocks']) || !is_array($data['stocks'])) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
        $delete_parts = array();
        $delete_params = array();
        $replace_values = array();
        $replace_params = array();
        $delete_index = 0;
        $replace_index = 0;
        foreach ($data['stocks'] as $raw_sku_id => $stocks) {
            $sku_id = (int) $raw_sku_id;
            if ($sku_id <= 0 || !is_array($stocks)) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }
            foreach ($stocks as $raw_stock_id => $row) {
                $stock_id = (int) $raw_stock_id;
                if ($stock_id <= 0 || !is_array($row) || !array_key_exists('exists', $row)) {
                    throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
                }
                if (empty($row['exists'])) {
                    $delete_parts[] = '(sku_id = i:delete_sku_' . $delete_index
                        . ' AND stock_id = i:delete_stock_' . $delete_index . ')';
                    $delete_params['delete_sku_' . $delete_index] = $sku_id;
                    $delete_params['delete_stock_' . $delete_index] = $stock_id;
                    $delete_index++;
                    continue;
                }
                $replace_values[] = '(i:replace_sku_' . $replace_index . ', i:replace_stock_' . $replace_index
                    . ', i:replace_product_' . $replace_index . ', :replace_count_' . $replace_index . ')';
                $replace_params['replace_sku_' . $replace_index] = $sku_id;
                $replace_params['replace_stock_' . $replace_index] = $stock_id;
                $replace_params['replace_product_' . $replace_index] = $product_id;
                $replace_params['replace_count_' . $replace_index] = array_key_exists('count', $row) ? $row['count'] : null;
                $replace_index++;
            }
        }
        if ($delete_parts) {
            $this->model->exec(
                'DELETE FROM shop_product_stocks WHERE ' . implode(' OR ', $delete_parts),
                $delete_params
            );
        }
        if ($replace_values) {
            $this->model->exec(
                'REPLACE INTO shop_product_stocks (sku_id, stock_id, product_id, count) VALUES '
                . implode(', ', $replace_values),
                $replace_params
            );
        }
        $this->restoreEditDatetime($product_id, $data);
        (new shopProductModel())->correct($product_id);
    }

    private function restoreTags($product_id, array $data)
    {
        if (!isset($data['tags']) || !is_array($data['tags'])) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
        $product = new shopProduct($product_id);
        $product['tags'] = array_values(array_map('strval', $data['tags']));
        if (!$product->save()) {
            throw new RuntimeException($this->t('rollback_restore_failed'));
        }
        $this->restoreEditDatetime($product_id, $data);
    }

    private function restoreCategories($product_id, array $data)
    {
        if (!isset($data['product'], $data['categories'])
            || !is_array($data['product'])
            || !is_array($data['categories'])
        ) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
        $product = new shopProduct($product_id);
        $product['category_id'] = array_key_exists('category_id', $data['product'])
            ? $data['product']['category_id']
            : null;
        if (array_key_exists('edit_datetime', $data['product'])) {
            $product['edit_datetime'] = $data['product']['edit_datetime'];
        }
        if (!$product->save()) {
            throw new RuntimeException($this->t('rollback_restore_failed'));
        }

        $this->model->exec(
            'DELETE FROM shop_category_products WHERE product_id = i:product_id',
            array('product_id' => $product_id)
        );
        if ($data['categories']) {
            $values = array();
            $params = array();
            foreach ($data['categories'] as $raw_category_id => $raw_sort) {
                $category_id = (int) $raw_category_id;
                if ($category_id <= 0 || !is_int($raw_sort)) {
                    throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
                }
                $index = count($values);
                $values[] = '(i:category_id_' . $index . ', i:product_id_' . $index . ', i:sort_' . $index . ')';
                $params['category_id_' . $index] = $category_id;
                $params['product_id_' . $index] = $product_id;
                $params['sort_' . $index] = $raw_sort;
            }
            $this->model->exec(
                'INSERT INTO shop_category_products (category_id, product_id, sort) VALUES '
                . implode(', ', $values),
                $params
            );
        }
        $this->restoreEditDatetime($product_id, $data);
    }

    private function restoreFeature($product_id, array $data)
    {
        $feature_id = isset($data['feature_id']) ? (int) $data['feature_id'] : 0;
        $value_ids = isset($data['feature_value_ids']) && is_array($data['feature_value_ids'])
            ? $this->normalizePositiveIds($data['feature_value_ids'])
            : array();
        if ($feature_id <= 0) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
        $this->model->exec(
            'DELETE FROM shop_product_features
             WHERE product_id = i:product_id AND feature_id = i:feature_id AND sku_id IS NULL',
            array('product_id' => $product_id, 'feature_id' => $feature_id)
        );
        if ($value_ids) {
            $values = array();
            $params = array();
            foreach (array_values($value_ids) as $index => $value_id) {
                $values[] = '(i:product_id_' . $index . ', NULL, i:feature_id_' . $index
                    . ', i:feature_value_id_' . $index . ')';
                $params['product_id_' . $index] = $product_id;
                $params['feature_id_' . $index] = $feature_id;
                $params['feature_value_id_' . $index] = $value_id;
            }
            $this->model->exec(
                'INSERT INTO shop_product_features (product_id, sku_id, feature_id, feature_value_id) VALUES '
                . implode(', ', $values),
                $params
            );
        }
        $this->restoreEditDatetime($product_id, $data);
    }

    private function restoreEditDatetime($product_id, array $data)
    {
        if (!isset($data['product'])
            || !is_array($data['product'])
            || !array_key_exists('edit_datetime', $data['product'])
        ) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
        $this->model->exec(
            'UPDATE shop_product SET edit_datetime = :edit_datetime WHERE id = i:product_id',
            array(
                'edit_datetime' => $data['product']['edit_datetime'],
                'product_id' => $product_id,
            )
        );
    }

    private function normalizeStrictPositiveInteger($value, $message_key)
    {
        if (is_int($value)) {
            $normalized = $value;
        } elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value)) {
            $normalized = (int) $value;
        } else {
            throw new InvalidArgumentException($this->t($message_key));
        }
        if ($normalized <= 0) {
            throw new InvalidArgumentException($this->t($message_key));
        }
        if ($normalized > 2147483647) {
            throw new InvalidArgumentException($this->t($message_key));
        }

        return $normalized;
    }

    private function normalizeDatetime($value)
    {
        if ($value === null) {
            return date('Y-m-d H:i:s');
        }
        $value = trim((string) $value);
        $timestamp = strtotime($value);
        if ($timestamp === false || date('Y-m-d H:i:s', $timestamp) !== $value) {
            throw new InvalidArgumentException($this->t('rollback_invalid_request'));
        }

        return $value;
    }

    private function assertAdminRights()
    {
        $user = wa()->getUser();
        if (!$user || !$user->isAdmin('shop')) {
            throw new RuntimeException($this->t('admin_required'));
        }
    }

    private function buildStorageRows($operation, array $before_state, array $after_state)
    {
        if (!$before_state || count($before_state) !== count($after_state) || count($before_state) > $this->operation_limit) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }

        ksort($before_state, SORT_NUMERIC);
        ksort($after_state, SORT_NUMERIC);
        if (array_keys($before_state) !== array_keys($after_state)) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }

        $rows = array();
        $total_bytes = 0;
        foreach ($before_state as $product_id => $before_item) {
            $product_id = (int) $product_id;
            $after_item = $after_state[$product_id];
            $this->assertValidSnapshotItem($before_item, $operation, $product_id);
            $this->assertValidSnapshotItem($after_item, $operation, $product_id);

            $before_json = json_encode($before_item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $after_json = json_encode($after_item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($before_json === false || $after_json === false
                || strlen($before_json) > self::MAX_ITEM_BYTES
                || strlen($after_json) > self::MAX_ITEM_BYTES
            ) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }
            $total_bytes += strlen($before_json) + strlen($after_json);
            if ($total_bytes > self::MAX_SNAPSHOT_BYTES) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }

            $rows[] = array(
                'product_id' => $product_id,
                'before_state' => $before_json,
                'after_state' => $after_json,
            );
        }

        return $rows;
    }

    private function assertValidSnapshotItem($item, $operation, $product_id)
    {
        if (!is_array($item)
            || !isset($item['version'], $item['operation'], $item['product_id'], $item['data'])
            || !$this->hasExactKeys($item, array('version', 'operation', 'product_id', 'data'))
            || !is_int($item['version'])
            || $item['version'] !== self::SNAPSHOT_VERSION
            || !is_string($item['operation'])
            || $item['operation'] !== $operation
            || !is_int($item['product_id'])
            || $item['product_id'] !== $product_id
            || $product_id <= 0
            || !is_array($item['data'])
        ) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
        $this->assertValidSnapshotData($item['data'], $operation);
    }

    private function assertValidSnapshotData(array $data, $operation)
    {
        $product_field_map = array(
            'visibility' => 'status',
            'description' => 'description',
            'url' => 'url',
            'video' => 'video_url',
        );
        if (isset($product_field_map[$operation])) {
            $this->assertExactKeys($data, array('product'));
            $field = $product_field_map[$operation];
            $this->assertProductState($data['product'], array($field, 'edit_datetime'));
            $value = $data['product'][$field];
            if (($operation === 'visibility'
                    && !in_array($value, array(-1, 0, 1, '-1', '0', '1'), true))
                || ($operation !== 'visibility' && $value !== null && !is_string($value))
            ) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }
            return;
        }

        if (in_array($operation, array('price', 'compare_price', 'availability'), true)
            || ($operation === 'stock' && isset($data['skus']))
        ) {
            $fields = array(
                'price' => array('price', 'compare_price'),
                'compare_price' => array('compare_price'),
                'availability' => array('available'),
                'stock' => array('count'),
            );
            $this->assertExactKeys($data, array('product', 'skus'));
            $this->assertProductState($data['product'], array('edit_datetime'));
            if (!is_array($data['skus'])) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }
            foreach ($data['skus'] as $sku_id => $sku_state) {
                if (!is_int($sku_id) || $sku_id <= 0 || !is_array($sku_state)) {
                    throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
                }
                $this->assertExactKeys($sku_state, $fields[$operation]);
                foreach ($sku_state as $value) {
                    if ($value !== null && !is_numeric($value)) {
                        throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
                    }
                }
                if ($operation === 'availability'
                    && !in_array($sku_state['available'], array(0, 1, '0', '1'), true)
                ) {
                    throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
                }
            }
            return;
        }

        if ($operation === 'stock') {
            $this->assertExactKeys($data, array('product', 'stocks'));
            $this->assertProductState($data['product'], array('edit_datetime'));
            if (!is_array($data['stocks'])) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }
            foreach ($data['stocks'] as $sku_id => $stocks) {
                if (!is_int($sku_id) || $sku_id <= 0 || !is_array($stocks)) {
                    throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
                }
                foreach ($stocks as $stock_id => $row) {
                    if (!is_int($stock_id) || $stock_id <= 0 || !is_array($row)) {
                        throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
                    }
                    $this->assertExactKeys($row, array('exists', 'count'));
                    if (!is_bool($row['exists']) || ($row['count'] !== null && !is_numeric($row['count']))) {
                        throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
                    }
                }
            }
            return;
        }

        if ($operation === 'tags') {
            $this->assertExactKeys($data, array('product', 'tags'));
            $this->assertProductState($data['product'], array('edit_datetime'));
            if (!is_array($data['tags'])) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }
            foreach ($data['tags'] as $tag) {
                if (!is_string($tag)) {
                    throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
                }
            }
            return;
        }

        if ($operation === 'categories') {
            $this->assertExactKeys($data, array('product', 'categories'));
            $this->assertProductState($data['product'], array('category_id', 'edit_datetime'));
            if (($data['product']['category_id'] !== null && !is_int($data['product']['category_id']))
                || !is_array($data['categories'])
            ) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }
            foreach ($data['categories'] as $category_id => $sort) {
                if (!is_int($category_id) || $category_id <= 0 || !is_int($sort)) {
                    throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
                }
            }
            return;
        }

        $this->assertExactKeys($data, array('product', 'feature_id', 'feature_value_ids'));
        $this->assertProductState($data['product'], array('edit_datetime'));
        if (!is_int($data['feature_id']) || $data['feature_id'] <= 0 || !is_array($data['feature_value_ids'])) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
        $seen = array();
        foreach ($data['feature_value_ids'] as $value_id) {
            if (!is_int($value_id) || $value_id <= 0 || isset($seen[$value_id])) {
                throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
            }
            $seen[$value_id] = true;
        }
    }

    private function assertProductState($product, array $keys)
    {
        if (!is_array($product)) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
        $this->assertExactKeys($product, $keys);
        if (in_array('edit_datetime', $keys, true)
            && !$this->isValidSnapshotDatetime($product['edit_datetime'])
        ) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
    }

    private function isValidSnapshotDatetime($value)
    {
        if ($value === null) {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }
        $timestamp = strtotime($value);

        return $timestamp !== false && date('Y-m-d H:i:s', $timestamp) === $value;
    }

    private function assertExactKeys(array $value, array $expected_keys)
    {
        if (!$this->hasExactKeys($value, $expected_keys)) {
            throw new InvalidArgumentException($this->t('rollback_invalid_snapshot'));
        }
    }

    private function hasExactKeys(array $value, array $expected_keys)
    {
        $actual_keys = array_keys($value);
        sort($actual_keys, SORT_STRING);
        sort($expected_keys, SORT_STRING);

        return $actual_keys === $expected_keys;
    }

    private function captureProductFieldState(array $state, $operation, array $product_ids)
    {
        $field_map = array(
            'visibility' => 'status',
            'description' => 'description',
            'url' => 'url',
            'video' => 'video_url',
        );
        $field = $field_map[$operation];
        $rows = $this->model
            ->query(
                'SELECT id, ' . $field . ', edit_datetime
                 FROM shop_product WHERE id IN (' . $this->placeholders($product_ids) . ')',
                $product_ids
            )
            ->fetchAll();

        $loaded = array();
        foreach ($rows as $row) {
            $product_id = isset($row['id']) ? (int) $row['id'] : 0;
            if (!isset($state[$product_id])) {
                continue;
            }
            $state[$product_id]['data']['product'] = array(
                $field => array_key_exists($field, $row) ? $row[$field] : null,
                'edit_datetime' => array_key_exists('edit_datetime', $row) ? $row['edit_datetime'] : null,
            );
            $loaded[$product_id] = true;
        }
        $this->assertAllProductsLoaded($state, $loaded);

        return $state;
    }

    private function captureSkuState(array $state, $operation, array $product_ids)
    {
        $field_map = array(
            'price' => array('price', 'compare_price'),
            'compare_price' => array('compare_price'),
            'availability' => array('available'),
            'stock' => array('count'),
        );
        $fields = $field_map[$operation];
        $rows = $this->model
            ->query(
                'SELECT id, product_id, ' . implode(', ', $fields) . '
                 FROM shop_product_skus
                 WHERE product_id IN (' . $this->placeholders($product_ids) . ')
                 ORDER BY product_id, id',
                $product_ids
            )
            ->fetchAll();

        foreach ($state as &$item) {
            $item['data']['skus'] = array();
        }
        unset($item);

        foreach ($rows as $row) {
            $product_id = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            $sku_id = isset($row['id']) ? (int) $row['id'] : 0;
            if (!isset($state[$product_id]) || $sku_id <= 0) {
                continue;
            }
            $sku_state = array();
            foreach ($fields as $field) {
                $sku_state[$field] = array_key_exists($field, $row) ? $row[$field] : null;
            }
            $state[$product_id]['data']['skus'][$sku_id] = $sku_state;
        }

        return $state;
    }

    private function captureWarehouseStockState(array $state, array $request, array $product_ids)
    {
        $stock_ids = isset($request['stock_ids']) && is_array($request['stock_ids'])
            ? $this->normalizePositiveIds($request['stock_ids'])
            : array();
        $selected_stock_id = isset($request['stock_id']) ? (int) $request['stock_id'] : 0;
        if ($selected_stock_id > 0) {
            $stock_ids[$selected_stock_id] = $selected_stock_id;
        }
        $stock_ids = array_values($stock_ids);
        sort($stock_ids, SORT_NUMERIC);
        if (!$stock_ids) {
            throw new InvalidArgumentException($this->t('validation_stock'));
        }

        $sku_rows = $this->model
            ->query(
                'SELECT id, product_id
                 FROM shop_product_skus
                 WHERE product_id IN (' . $this->placeholders($product_ids) . ')
                 ORDER BY product_id, id',
                $product_ids
            )
            ->fetchAll();
        $sku_to_product = array();
        foreach ($state as &$item) {
            $item['data']['stocks'] = array();
        }
        unset($item);
        foreach ($sku_rows as $row) {
            $sku_id = isset($row['id']) ? (int) $row['id'] : 0;
            $product_id = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            if ($sku_id <= 0 || !isset($state[$product_id])) {
                continue;
            }
            $sku_to_product[$sku_id] = $product_id;
            foreach ($stock_ids as $stock_id) {
                $state[$product_id]['data']['stocks'][$sku_id][$stock_id] = array(
                    'exists' => false,
                    'count' => null,
                );
            }
        }

        if (!$sku_to_product) {
            return $state;
        }

        $sku_ids = array_keys($sku_to_product);
        $rows = $this->model
            ->query(
                'SELECT sku_id, stock_id, product_id, count
                 FROM shop_product_stocks
                 WHERE sku_id IN (' . $this->placeholders($sku_ids) . ')
                   AND stock_id IN (' . $this->placeholders($stock_ids) . ')
                 ORDER BY product_id, sku_id, stock_id',
                array_merge($sku_ids, $stock_ids)
            )
            ->fetchAll();
        foreach ($rows as $row) {
            $sku_id = isset($row['sku_id']) ? (int) $row['sku_id'] : 0;
            $stock_id = isset($row['stock_id']) ? (int) $row['stock_id'] : 0;
            if (!isset($sku_to_product[$sku_id])) {
                continue;
            }
            $product_id = $sku_to_product[$sku_id];
            if (!isset($state[$product_id]['data']['stocks'][$sku_id][$stock_id])) {
                continue;
            }
            $state[$product_id]['data']['stocks'][$sku_id][$stock_id] = array(
                'exists' => true,
                'count' => array_key_exists('count', $row) ? $row['count'] : null,
            );
        }

        return $state;
    }

    private function captureTagsState(array $state, array $product_ids)
    {
        foreach ($state as &$item) {
            $item['data']['tags'] = array();
        }
        unset($item);

        $rows = $this->model
            ->query(
                'SELECT pt.product_id, t.name
                 FROM shop_product_tags pt
                 JOIN shop_tag t ON t.id = pt.tag_id
                 WHERE pt.product_id IN (' . $this->placeholders($product_ids) . ')
                 ORDER BY pt.product_id, t.name',
                $product_ids
            )
            ->fetchAll();
        foreach ($rows as $row) {
            $product_id = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            if (isset($state[$product_id]) && isset($row['name'])) {
                $state[$product_id]['data']['tags'][] = (string) $row['name'];
            }
        }

        return $state;
    }

    private function captureCategoriesState(array $state, array $product_ids)
    {
        $rows = $this->model
            ->query(
                'SELECT id, category_id, edit_datetime
                 FROM shop_product WHERE id IN (' . $this->placeholders($product_ids) . ')',
                $product_ids
            )
            ->fetchAll();
        $loaded = array();
        foreach ($state as &$item) {
            $item['data']['categories'] = array();
        }
        unset($item);
        foreach ($rows as $row) {
            $product_id = isset($row['id']) ? (int) $row['id'] : 0;
            if (!isset($state[$product_id])) {
                continue;
            }
            $state[$product_id]['data']['product'] = array(
                'category_id' => array_key_exists('category_id', $row) && $row['category_id'] !== null
                    ? (int) $row['category_id']
                    : null,
                'edit_datetime' => array_key_exists('edit_datetime', $row) ? $row['edit_datetime'] : null,
            );
            $loaded[$product_id] = true;
        }
        $this->assertAllProductsLoaded($state, $loaded);

        $category_rows = $this->model
            ->query(
                'SELECT product_id, category_id, sort
                 FROM shop_category_products
                 WHERE product_id IN (' . $this->placeholders($product_ids) . ')
                 ORDER BY product_id, category_id',
                $product_ids
            )
            ->fetchAll();
        foreach ($category_rows as $row) {
            $product_id = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            $category_id = isset($row['category_id']) ? (int) $row['category_id'] : 0;
            if (isset($state[$product_id]) && $category_id > 0) {
                $state[$product_id]['data']['categories'][$category_id] = isset($row['sort']) ? (int) $row['sort'] : 0;
            }
        }

        return $state;
    }

    private function captureFeaturesState(array $state, array $request, array $product_ids)
    {
        $feature_id = isset($request['feature_id']) ? (int) $request['feature_id'] : 0;
        if ($feature_id <= 0) {
            throw new InvalidArgumentException($this->t('validation_feature'));
        }
        foreach ($state as &$item) {
            $item['data']['feature_id'] = $feature_id;
            $item['data']['feature_value_ids'] = array();
        }
        unset($item);

        $params = array_merge(array($feature_id), $product_ids);
        $rows = $this->model
            ->query(
                'SELECT product_id, feature_value_id
                 FROM shop_product_features
                 WHERE feature_id = ?
                   AND product_id IN (' . $this->placeholders($product_ids) . ')
                   AND sku_id IS NULL
                 ORDER BY product_id, feature_value_id',
                $params
            )
            ->fetchAll();
        foreach ($rows as $row) {
            $product_id = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            $value_id = isset($row['feature_value_id']) ? (int) $row['feature_value_id'] : 0;
            if (isset($state[$product_id]) && $value_id > 0) {
                $state[$product_id]['data']['feature_value_ids'][$value_id] = $value_id;
            }
        }
        foreach ($state as &$item) {
            $item['data']['feature_value_ids'] = array_values($item['data']['feature_value_ids']);
        }
        unset($item);

        return $state;
    }

    private function captureEditDatetimeState(array $state, array $product_ids)
    {
        $loaded = array();
        foreach ($state as $product_id => $item) {
            if (isset($item['data']['product'])
                && is_array($item['data']['product'])
                && array_key_exists('edit_datetime', $item['data']['product'])
            ) {
                $loaded[(int) $product_id] = true;
            }
        }
        if (count($loaded) === count($state)) {
            return $state;
        }

        $rows = $this->model
            ->query(
                'SELECT id, edit_datetime
                 FROM shop_product WHERE id IN (' . $this->placeholders($product_ids) . ')',
                $product_ids
            )
            ->fetchAll();
        foreach ($rows as $row) {
            $product_id = isset($row['id']) ? (int) $row['id'] : 0;
            if (!isset($state[$product_id])) {
                continue;
            }
            if (!isset($state[$product_id]['data']['product'])) {
                $state[$product_id]['data']['product'] = array();
            }
            $state[$product_id]['data']['product']['edit_datetime'] = array_key_exists('edit_datetime', $row)
                ? $row['edit_datetime']
                : null;
            $loaded[$product_id] = true;
        }
        $this->assertAllProductsLoaded($state, $loaded);

        return $state;
    }

    private function initializeState($operation, array $product_ids)
    {
        $state = array();
        foreach ($product_ids as $product_id) {
            $state[$product_id] = array(
                'version' => self::SNAPSHOT_VERSION,
                'operation' => $operation,
                'product_id' => $product_id,
                'data' => array(),
            );
        }

        return $state;
    }

    private function normalizeProductIds(array $raw_ids)
    {
        return array_values($this->normalizePositiveIds($raw_ids));
    }

    private function normalizePositiveIds(array $raw_ids)
    {
        $ids = array();
        foreach ($raw_ids as $raw_id) {
            $id = (int) $raw_id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    private function placeholders(array $values)
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    private function assertAllProductsLoaded(array $state, array $loaded)
    {
        if (count($loaded) !== count($state)) {
            throw new InvalidArgumentException($this->t('missing_products'));
        }
    }

    private function normalizeOperationLimit($value)
    {
        $value = (int) $value;
        if ($value <= 0) {
            $value = self::DEFAULT_OPERATION_LIMIT;
        }

        return min(self::MAX_OPERATION_LIMIT, $value);
    }

    private function t($key)
    {
        return shopMasseditorPluginI18nService::t($key, $this->language);
    }
}
