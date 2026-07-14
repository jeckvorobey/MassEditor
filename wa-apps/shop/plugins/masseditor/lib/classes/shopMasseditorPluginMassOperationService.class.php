<?php

class shopMasseditorPluginMassOperationService
{
    const DEFAULT_OPERATION_LIMIT = 100;
    const APPLY_BATCH_SIZE = 20;
    const FEATURE_VALUES_LIMIT = 1000;
    const SKU_OPERATIONS = array('price', 'compare_price', 'availability', 'stock');
    const PRICE_OPERATIONS = array('price', 'compare_price');
    const PRICE_MODES = array('set', 'add', 'subtract', 'increase_percent', 'decrease_percent');
    const ALL_OPERATIONS = array('price', 'compare_price', 'visibility', 'availability', 'description', 'tags', 'url', 'stock', 'features', 'categories', 'video');

    private $selection_service;
    private $log_service;
    private $model;
    private $operation_limit;
    private $language;

    public function __construct(
        shopMasseditorPluginProductSelectionService $selection_service = null,
        shopMasseditorPluginLogService $log_service = null,
        waModel $model = null,
        $operation_limit = null,
        $language = null
    ) {
        $this->selection_service = $selection_service ?: new shopMasseditorPluginProductSelectionService();
        $this->log_service = $log_service ?: new shopMasseditorPluginLogService();
        $this->model = $model ?: new waModel();
        $this->operation_limit = $this->normalizeOperationLimit($operation_limit);
        $this->language = $language;
    }

    public function apply(array $raw_request)
    {
        $request = $this->normalizeRequest($raw_request, true);
        $products = $this->selection_service->getByIds($request['product_ids']);
        $this->assertSelectedProductsLoaded($products, $request['product_ids']);
        $skus_by_product = $this->resolveSkusByProducts($products, $request['operation']);
        $this->assertStockRequestMatchesProductAccounting($request, $skus_by_product);
        $skipped_count = 0;
        if ($request['operation'] === 'stock' && (int) $request['stock_id'] === 0 && $request['stock_type_filter'] !== 'all') {
            $filtered = $this->filterProductsByStockType($products, $skus_by_product, $request['stock_type_filter']);
            $products = $filtered['products'];
            $skipped_count = $filtered['skipped'];
        }

        $count = count($products);

        if ($count === 0) {
            return array(
                'request' => $request,
                'summary' => $this->buildSummary($request, 0),
                'message' => $this->t('operation_success'),
                'skipped' => $skipped_count,
            );
        }

        $this->model->exec('START TRANSACTION');

        try {
            $chunks = array_chunk($products, self::APPLY_BATCH_SIZE, true);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $product_data) {
                    $product_skus = isset($skus_by_product[$product_data['id']]) ? $skus_by_product[$product_data['id']] : array();
                    $this->applyToProduct($product_data, $request, $product_skus);
                }
            }

            $this->log_service->log(
                $request['operation'],
                $count,
                $this->buildDescription($request, $count)
            );

            $this->model->exec('COMMIT');
        } catch (Exception $e) {
            $this->model->exec('ROLLBACK');
            throw $e;
        }

        return array(
            'request' => $request,
            'summary' => $this->buildSummary($request, $count),
            'message' => $this->t('operation_success'),
            'skipped' => $skipped_count,
        );
    }

    private function normalizeRequest(array $raw_request, $require_confirmation)
    {
        $this->assertAdminRights();

        $operation = isset($raw_request['operation']) ? trim((string) $raw_request['operation']) : '';
        if (!in_array($operation, self::ALL_OPERATIONS, true)) {
            throw new InvalidArgumentException($this->t('unknown_operation'));
        }

        $selection = $this->resolveSelection($raw_request);
        $product_ids = $selection['product_ids'];

        if (!$product_ids) {
            throw new InvalidArgumentException($this->t('validation_select_product'));
        }

        if (count($product_ids) > $this->operation_limit) {
            throw new InvalidArgumentException(
                $this->t('limit_error_prefix') . $this->operation_limit . $this->t('limit_error_suffix')
            );
        }

        $request = array(
            'product_ids' => array_values($product_ids),
            'selection_mode' => $selection['selection_mode'],
            'filters' => $selection['filters'],
            'operation' => $operation,
            'mode' => 'set',
            'numeric_value' => null,
            'visibility_status' => null,
            'availability_value' => null,
            'round_step' => '',
            'round_direction' => 'nearest',
            'compare_price_mode' => 'keep',
            'compare_price_value' => null,
            'description_mode' => 'replace',
            'text_value' => '',
            'tags_mode' => 'add',
            'tags_value' => '',
            'url_mode' => 'regenerate',
            'url_value' => '',
            'stock_id' => 0,
            'stock_name' => '',
            'stock_mode' => 'set',
            'stock_type_filter' => 'all',
            'stock_value' => null,
            'stock_ids' => array(),
            'feature_id' => 0,
            'feature_name' => '',
            'feature_code' => '',
            'feature_type' => '',
            'feature_multiple' => false,
            'feature_value_id' => null,
            'feature_value' => '',
            'feature_value_ids' => array(),
            'feature_values_by_id' => array(),
            'feature_current_value_ids' => array(),
            'feature_mode' => 'set',
            'category_id' => 0,
            'category_name' => '',
            'categories_mode' => 'add',
            'video_mode' => 'set',
            'video_url' => '',
        );

        if (in_array($operation, self::PRICE_OPERATIONS, true)) {
            $mode = isset($raw_request['mode']) ? (string) $raw_request['mode'] : 'set';
            if (!in_array($mode, self::PRICE_MODES, true)) {
                throw new InvalidArgumentException($this->t('unknown_price_mode'));
            }

            $value = isset($raw_request['numeric_value']) ? str_replace(',', '.', trim((string) $raw_request['numeric_value'])) : '';
            if ($value === '' || !is_numeric($value) || (float) $value < 0) {
                throw new InvalidArgumentException($this->t('invalid_numeric'));
            }

            $request['mode'] = $mode;
            $request['numeric_value'] = (float) $value;
            $request['round_step'] = $this->normalizeRoundStep(isset($raw_request['round_step']) ? $raw_request['round_step'] : '');
            $request['round_direction'] = $this->normalizeRoundDirection(
                isset($raw_request['round_direction']) ? $raw_request['round_direction'] : 'nearest'
            );

            if ($operation === 'price') {
                $request['compare_price_mode'] = $this->normalizeComparePriceMode(
                    isset($raw_request['compare_price_mode']) ? $raw_request['compare_price_mode'] : 'keep'
                );
                if ($request['compare_price_mode'] === 'coefficient') {
                    $compare_price_value = isset($raw_request['compare_price_value'])
                        ? str_replace(',', '.', trim((string) $raw_request['compare_price_value']))
                        : '';
                    if ($compare_price_value === '' || !is_numeric($compare_price_value)) {
                        throw new InvalidArgumentException($this->t('validation_compare_coefficient'));
                    }
                    $request['compare_price_value'] = (float) $compare_price_value;
                }
            }
        } elseif ($operation === 'visibility') {
            $status = isset($raw_request['visibility_status']) ? (int) $raw_request['visibility_status'] : 1;
            if (!in_array($status, array(1, 0, -1), true)) {
                throw new InvalidArgumentException($this->t('invalid_visibility'));
            }
            $request['visibility_status'] = $status;
        } elseif ($operation === 'availability') {
            $availability = isset($raw_request['availability_value']) ? (int) $raw_request['availability_value'] : 1;
            if (!in_array($availability, array(0, 1), true)) {
                throw new InvalidArgumentException($this->t('invalid_availability'));
            }
            $request['availability_value'] = $availability;
        } elseif ($operation === 'description') {
            $request['description_mode'] = $this->normalizeDescriptionMode(
                isset($raw_request['description_mode']) ? $raw_request['description_mode'] : 'replace'
            );
            $request['text_value'] = isset($raw_request['text_value']) ? trim((string) $raw_request['text_value']) : '';
            if ($request['text_value'] === '') {
                throw new InvalidArgumentException($this->t('validation_description'));
            }
        } elseif ($operation === 'tags') {
            $request['tags_mode'] = $this->normalizeTagsMode(isset($raw_request['tags_mode']) ? $raw_request['tags_mode'] : 'add');
            $request['tags_value'] = $this->normalizeTagList(isset($raw_request['tags_value']) ? (string) $raw_request['tags_value'] : '');
            if (!$request['tags_value']) {
                throw new InvalidArgumentException($this->t('validation_tags'));
            }
        } elseif ($operation === 'url') {
            $request['url_mode'] = $this->normalizeUrlMode(isset($raw_request['url_mode']) ? $raw_request['url_mode'] : 'regenerate');
            $request['url_value'] = isset($raw_request['url_value']) ? trim((string) $raw_request['url_value']) : '';
            if ($request['url_mode'] === 'template' && $request['url_value'] === '') {
                throw new InvalidArgumentException($this->t('validation_url_template'));
            }
        } elseif ($operation === 'stock') {
            $stock_id = isset($raw_request['stock_id']) ? (int) $raw_request['stock_id'] : 0;
            $stocks = $stock_id > 0 ? $this->resolveStocks($stock_id) : array(
                'selected' => array('id' => 0, 'name' => $this->t('stock_no_warehouse')),
                'ids' => array(),
            );
            $stock = $stocks['selected'];
            $stock_mode = $this->normalizeStockMode(isset($raw_request['stock_mode']) ? $raw_request['stock_mode'] : 'set');
            $request['stock_id'] = (int) $stock['id'];
            $request['stock_name'] = (string) $stock['name'];
            $request['stock_mode'] = $stock_mode;
            $request['stock_value'] = $stock_mode === 'infinite'
                ? null
                : $this->normalizeNonNegativeNumber(isset($raw_request['stock_value']) ? $raw_request['stock_value'] : '', 'invalid_stock_value');
            $request['stock_ids'] = $stocks['ids'];
            $request['stock_type_filter'] = $this->normalizeStockTypeFilter(
                isset($raw_request['stock_type_filter']) ? $raw_request['stock_type_filter'] : 'all'
            );
        } elseif ($operation === 'features') {
            $feature = $this->resolveFeature(isset($raw_request['feature_id']) ? (int) $raw_request['feature_id'] : 0);
            $multiple = !empty($feature['multiple']);
            $default_mode = $multiple ? 'replace' : 'set';
            $feature_mode = $this->normalizeFeatureMode(
                isset($raw_request['feature_mode']) ? $raw_request['feature_mode'] : $default_mode,
                $multiple
            );
            $request['feature_id'] = (int) $feature['id'];
            $request['feature_name'] = (string) $feature['name'];
            $request['feature_code'] = (string) $feature['code'];
            $request['feature_type'] = (string) $feature['type'];
            $request['feature_multiple'] = $multiple;
            $request['feature_mode'] = $feature_mode;

            if ($multiple) {
                $value_ids = $this->normalizeFeatureValueIds(
                    isset($raw_request['feature_value_ids']) && is_array($raw_request['feature_value_ids'])
                        ? $raw_request['feature_value_ids']
                        : array()
                );
                if ($feature_mode !== 'clear' && !$value_ids) {
                    throw new InvalidArgumentException($this->t('validation_feature_existing_value'));
                }

                $request['feature_value_ids'] = $value_ids;
                $request['feature_values_by_id'] = $this->loadFeatureValuesByIds($feature, $value_ids);
                if (in_array($feature_mode, array('add', 'remove'), true)) {
                    $request['feature_current_value_ids'] = $this->loadCurrentFeatureValueIds(
                        $request['product_ids'],
                        (int) $feature['id']
                    );
                    $current_ids = array();
                    foreach ($request['feature_current_value_ids'] as $product_value_ids) {
                        foreach ($product_value_ids as $value_id) {
                            $current_ids[$value_id] = $value_id;
                        }
                    }
                    $request['feature_values_by_id'] += $this->loadFeatureValuesByIds($feature, array_values($current_ids));
                }
            } else {
                $request['feature_value'] = isset($raw_request['feature_value']) ? trim((string) $raw_request['feature_value']) : '';
                if ($feature_mode === 'set') {
                    if ($request['feature_value'] === '') {
                        throw new InvalidArgumentException($this->t('validation_feature_value'));
                    }
                    $request['feature_value'] = $this->normalizeFeatureValue($feature, $request['feature_value']);
                }
            }
        } elseif ($operation === 'categories') {
            $category = $this->resolveCategory(isset($raw_request['category_id']) ? (int) $raw_request['category_id'] : 0);
            $request['category_id'] = (int) $category['id'];
            $request['category_name'] = (string) $category['name'];
            $request['categories_mode'] = $this->normalizeCategoriesMode(isset($raw_request['categories_mode']) ? $raw_request['categories_mode'] : 'add');
        } elseif ($operation === 'video') {
            $video_mode = isset($raw_request['video_mode']) ? (string) $raw_request['video_mode'] : 'set';
            if (!in_array($video_mode, array('set', 'clear'), true)) {
                throw new InvalidArgumentException($this->t('invalid_video_mode'));
            }
            $video_url = isset($raw_request['video_url']) ? trim((string) $raw_request['video_url']) : '';
            if ($video_mode === 'set' && !$this->isValidHttpUrl($video_url)) {
                throw new InvalidArgumentException($this->t('validation_video_url'));
            }
            $request['video_mode'] = $video_mode;
            $request['video_url'] = $video_mode === 'clear' ? '' : $video_url;
        }

        if ($require_confirmation && empty($raw_request['confirm_apply'])) {
            throw new InvalidArgumentException($this->t('confirm_required'));
        }

        return $request;
    }


    private function resolveSelection(array $raw_request)
    {
        $mode = isset($raw_request['selection_mode']) ? (string) $raw_request['selection_mode'] : 'ids';
        if ($mode === 'filter') {
            $filters = isset($raw_request['filters']) && is_array($raw_request['filters'])
                ? $raw_request['filters']
                : array(
                    'query' => isset($raw_request['query']) ? $raw_request['query'] : '',
                    'status' => isset($raw_request['status']) ? $raw_request['status'] : 'all',
                    'availability' => isset($raw_request['availability']) ? $raw_request['availability'] : 'all',
                    'category_id' => isset($raw_request['filter_category_id']) ? $raw_request['filter_category_id'] : (isset($raw_request['category_filter_id']) ? $raw_request['category_filter_id'] : 0),
                );
            $ids = $this->selection_service->getIdsByFilters($filters, $this->operation_limit + 1);

            return array(
                'selection_mode' => 'filter',
                'filters' => $filters,
                'product_ids' => $this->normalizeProductIds($ids),
            );
        }

        return array(
            'selection_mode' => 'ids',
            'filters' => array(),
            'product_ids' => $this->normalizeProductIds(isset($raw_request['product_ids']) && is_array($raw_request['product_ids']) ? $raw_request['product_ids'] : array()),
        );
    }

    private function normalizeProductIds(array $raw_ids)
    {
        $product_ids = array();
        foreach ($raw_ids as $product_id) {
            $product_id = (int) $product_id;
            if ($product_id > 0) {
                $product_ids[$product_id] = $product_id;
            }
        }

        return array_values($product_ids);
    }

    private function resolveSkusByProducts(array $products, $operation)
    {
        if (in_array($operation, self::SKU_OPERATIONS, true)) {
            return $this->loadSkusByProducts($products, $operation === 'stock');
        }

        return array();
    }

    private function loadSkusByProducts(array $products, $with_stock = false)
    {
        if (!$products) {
            return array();
        }

        $ids = array_map('intval', array_keys($products));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->model
            ->query(
                'SELECT id, product_id, price, compare_price, available, count
                 FROM shop_product_skus
                 WHERE product_id IN (' . $placeholders . ')',
                $ids
            )
            ->fetchAll();

        $result = array();
        $sku_ids = array();
        $sku_product_ids = array();
        foreach ($rows as $sku) {
            $result[$sku['product_id']][$sku['id']] = $sku;
            $sku_ids[] = (int) $sku['id'];
            $sku_product_ids[(int) $sku['id']] = (int) $sku['product_id'];
        }

        if ($with_stock && $sku_ids) {
            $stock_placeholders = implode(',', array_fill(0, count($sku_ids), '?'));
            $stock_rows = $this->model
                ->query(
                    'SELECT sku_id, stock_id, count
                     FROM shop_product_stocks
                     WHERE sku_id IN (' . $stock_placeholders . ')',
                    $sku_ids
                )
                ->fetchAll();

            foreach ($stock_rows as $stock_row) {
                $sku_id = (int) $stock_row['sku_id'];
                $stock_id = (int) $stock_row['stock_id'];
                if (isset($sku_product_ids[$sku_id])) {
                    $product_id = $sku_product_ids[$sku_id];
                    $result[$product_id][$sku_id]['stock'][$stock_id] = $stock_row['count'];
                }
            }
        }

        return $result;
    }

    private function assertSelectedProductsLoaded(array $products, array $requested_ids)
    {
        if (count($products) !== count($requested_ids)) {
            throw new InvalidArgumentException($this->t('missing_products'));
        }
    }

    private function assertStockRequestMatchesProductAccounting(array $request, array $skus_by_product)
    {
        if ($request['operation'] !== 'stock' || (int) $request['stock_id'] > 0) {
            return;
        }

        if ($request['stock_type_filter'] !== 'all') {
            return;
        }

        foreach ($skus_by_product as $skus) {
            foreach ($skus as $sku) {
                if (!empty($sku['stock']) && is_array($sku['stock'])) {
                    throw new InvalidArgumentException($this->t('validation_stock_required_for_accounted_products'));
                }
            }
        }
    }

    private function applyToProduct(array $product_data, array $request, array $skus = array())
    {
        if ($request['operation'] === 'visibility') {
            $this->model->exec(
                'UPDATE shop_product
                 SET status = i:status, edit_datetime = s:edited_at
                 WHERE id = i:id',
                array(
                    'status' => $request['visibility_status'],
                    'edited_at' => date('Y-m-d H:i:s'),
                    'id' => (int) $product_data['id'],
                )
            );

            return;
        }

        if ($request['operation'] === 'tags') {
            $this->applyTagsOperation((int) $product_data['id'], $request);
            return;
        }

        if ($request['operation'] === 'categories') {
            $this->applyCategoryOperation((int) $product_data['id'], $request);
            return;
        }

        if ($request['operation'] === 'features') {
            $this->applyFeatureOperation((int) $product_data['id'], $request);
            return;
        }

        if ($request['operation'] === 'video') {
            $product = new shopProduct((int) $product_data['id']);
            if (!$product->checkRights()) {
                throw new RuntimeException($this->t('product_edit_denied'));
            }
            $product['video_url'] = $request['video_url'];
            $product->save();
            return;
        }

        $use_warehouse_stock = $request['operation'] === 'stock'
            && $request['stock_id'] > 0;

        if ($use_warehouse_stock) {
            $this->applyWarehouseStockOperation((int) $product_data['id'], $request, $skus);
            return;
        }

        $product = new shopProduct((int) $product_data['id']);

        if ($request['operation'] === 'description') {
            $current_description = isset($product['description']) ? (string) $product['description'] : '';
            $this->model->exec(
                'UPDATE shop_product
                 SET description = s:description, edit_datetime = s:edited_at
                 WHERE id = i:id',
                array(
                    'description' => $this->buildDescriptionValue($current_description, $request),
                    'edited_at' => date('Y-m-d H:i:s'),
                    'id' => (int) $product_data['id'],
                )
            );
            return;
        }

        if ($request['operation'] === 'url') {
            $product['url'] = $this->buildProductUrl($product_data, $request);
            $product->save();
            return;
        }

        $product_skus = $skus ?: $product->getSkus();
        foreach ($product_skus as $sku_id => $sku) {
            if ($request['operation'] === 'price') {
                $old_price = (float) $sku['price'];
                $new_price = $this->calculateNumericValue($old_price, $request['mode'], $request['numeric_value']);
                $product_skus[$sku_id]['price'] = $this->applyRounding($new_price, $request['round_step'], $request['round_direction']);
                $product_skus[$sku_id]['compare_price'] = $this->calculatePriceComparePrice(
                    $old_price,
                    isset($sku['compare_price']) ? $sku['compare_price'] : null,
                    $product_skus[$sku_id]['price'],
                    $request
                );
            } elseif ($request['operation'] === 'compare_price') {
                $new_compare = $this->calculateNumericValue((float) $sku['compare_price'], $request['mode'], $request['numeric_value']);
                $product_skus[$sku_id]['compare_price'] = $this->applyRounding(
                    $new_compare,
                    $request['round_step'],
                    $request['round_direction']
                );
            } elseif ($request['operation'] === 'availability') {
                $product_skus[$sku_id]['available'] = $request['availability_value'];
            } elseif ($request['operation'] === 'stock') {
                if ($use_warehouse_stock) {
                    $product_skus[$sku_id]['stock'][$request['stock_id']] = $this->calculateStockValue(
                        isset($sku['stock'][$request['stock_id']]) ? $sku['stock'][$request['stock_id']] : null,
                        $request
                    );
                } else {
                    $product_skus[$sku_id]['count'] = $this->calculateStockValue(
                        isset($sku['count']) ? $sku['count'] : null,
                        $request
                    );
                }
            }
        }

        $product['skus'] = $product_skus;
        $product->save();
        if ($request['operation'] === 'stock') {
            $this->correctProductStockCounts((int) $product_data['id']);
        }
    }

    private function applyWarehouseStockOperation($product_id, array $request, array $skus)
    {
        $rows = array();
        $stock_ids = isset($request['stock_ids']) && $request['stock_ids']
            ? $request['stock_ids']
            : array($request['stock_id']);

        foreach ($skus as $sku_id => $sku) {
            foreach ($stock_ids as $stock_id) {
                $stock_id = (int) $stock_id;
                $old_value = isset($sku['stock']) && array_key_exists($stock_id, $sku['stock'])
                    ? $sku['stock'][$stock_id]
                    : 0;
                $new_value = $stock_id === (int) $request['stock_id']
                    ? $this->calculateStockValue($old_value, $request)
                    : $old_value;

                $rows[] = array(
                    'sku_id' => (int) $sku_id,
                    'stock_id' => $stock_id,
                    'product_id' => (int) $product_id,
                    'count' => $new_value === null ? null : (float) $new_value,
                );
            }
        }

        if (!$rows) {
            return;
        }

        $values = array();
        $params = array();
        foreach ($rows as $index => $row) {
            $values[] = '(i:sku_id_' . $index . ', i:stock_id_' . $index . ', i:product_id_' . $index . ', :count_' . $index . ')';
            $params['sku_id_' . $index] = (int) $row['sku_id'];
            $params['stock_id_' . $index] = (int) $row['stock_id'];
            $params['product_id_' . $index] = (int) $row['product_id'];
            $params['count_' . $index] = $row['count'];
        }

        $this->model->exec(
            'REPLACE INTO shop_product_stocks (sku_id, stock_id, product_id, count) VALUES ' . implode(', ', $values),
            $params
        );
        $this->model->exec(
            'UPDATE shop_product SET edit_datetime = s:edited_at WHERE id = i:product_id',
            array('edited_at' => date('Y-m-d H:i:s'), 'product_id' => (int) $product_id)
        );
        $this->correctProductStockCounts((int) $product_id);
    }

    private function correctProductStockCounts($product_id)
    {
        $product_model = new shopProductModel();
        $product_model->correct((int) $product_id);
    }

    private function usesWarehouseStockAccounting(array $skus)
    {
        foreach ($skus as $sku) {
            if (!empty($sku['stock']) && is_array($sku['stock'])) {
                return true;
            }
        }

        return false;
    }

    private function filterProductsByStockType(array $products, array $skus_by_product, $filter)
    {
        $filtered = array();
        $skipped = 0;

        foreach ($products as $product_id => $product_data) {
            $skus = isset($skus_by_product[$product_id]) ? $skus_by_product[$product_id] : array();
            $has_warehouse = $this->usesWarehouseStockAccounting($skus);

            if ($filter === 'without_warehouse' && $has_warehouse) {
                $skipped++;
                continue;
            }
            if ($filter === 'with_warehouse' && !$has_warehouse) {
                $skipped++;
                continue;
            }

            $filtered[$product_id] = $product_data;
        }

        return array('products' => $filtered, 'skipped' => $skipped);
    }

    private function applyCategoryOperation($product_id, array $request)
    {
        $product_id = (int) $product_id;
        $category_id = (int) $request['category_id'];

        if ($request['categories_mode'] === 'remove') {
            $this->model->exec(
                'DELETE FROM shop_category_products WHERE product_id = i:product_id AND category_id = i:category_id',
                array('product_id' => $product_id, 'category_id' => $category_id)
            );
            return;
        }

        if ($request['categories_mode'] === 'replace_main') {
            $this->model->exec(
                'UPDATE shop_product SET category_id = i:category_id, edit_datetime = s:edited_at WHERE id = i:product_id',
                array('category_id' => $category_id, 'edited_at' => date('Y-m-d H:i:s'), 'product_id' => $product_id)
            );
        }

        $this->model->exec(
            'INSERT IGNORE INTO shop_category_products (category_id, product_id) VALUES (i:category_id, i:product_id)',
            array('category_id' => $category_id, 'product_id' => $product_id)
        );
    }

    private function applyFeatureOperation($product_id, array $request)
    {
        $product = new shopProduct((int) $product_id);
        $feature_value = $request['feature_mode'] === 'clear' ? array() : $request['feature_value'];

        if (!empty($request['feature_multiple'])) {
            $current_ids = isset($request['feature_current_value_ids'][$product_id])
                ? $request['feature_current_value_ids'][$product_id]
                : array();
            $selected_ids = $request['feature_value_ids'];

            if ($request['feature_mode'] === 'add') {
                $final_ids = array_values(array_unique(array_merge($current_ids, $selected_ids)));
            } elseif ($request['feature_mode'] === 'remove') {
                $final_ids = array_values(array_diff($current_ids, $selected_ids));
            } elseif ($request['feature_mode'] === 'clear') {
                $final_ids = array();
            } else {
                $final_ids = $selected_ids;
            }

            $feature_value = array();
            foreach ($final_ids as $value_id) {
                if (!array_key_exists($value_id, $request['feature_values_by_id'])) {
                    throw new InvalidArgumentException($this->t('validation_feature_existing_value'));
                }
                $feature_value[] = $request['feature_values_by_id'][$value_id];
            }
        }

        $product['features'] = array($request['feature_code'] => $feature_value);
        $product->save();
    }

    private function applyTagsOperation($product_id, array $request)
    {
        $tags_model = new shopProductTagsModel();

        if ($request['tags_mode'] === 'add') {
            $tags_model->addTags($product_id, $request['tags_value']);
        } elseif ($request['tags_mode'] === 'remove') {
            $tags_model->deleteTags($product_id, $request['tags_value']);
        } else {
            $product = new shopProduct($product_id);
            $tags_model->setData($product, $request['tags_value']);
        }
    }

    private function buildDescriptionValue($current_value, array $request)
    {
        $current_value = (string) $current_value;
        $text_value = (string) $request['text_value'];

        if ($request['description_mode'] === 'prepend') {
            if ($text_value === '' || $current_value === '') {
                return $text_value . $current_value;
            }

            return rtrim($text_value) . ' ' . ltrim($current_value);
        }
        if ($request['description_mode'] === 'append') {
            if ($current_value === '' || $text_value === '') {
                return $current_value . $text_value;
            }

            return rtrim($current_value) . ' ' . ltrim($text_value);
        }

        return $text_value;
    }

    private function buildProductUrl(array $product_data, array $request)
    {
        if ($request['url_mode'] === 'template') {
            $base_url = strtr($request['url_value'], array(
                '{id}' => (string) $product_data['id'],
                '{name}' => $product_data['name'],
                '{current_url}' => (string) ifset($product_data['url'], ''),
            ));
        } else {
            $base_url = $product_data['name'];
        }

        return $this->suggestUniqueProductUrl($base_url, (int) $product_data['id']);
    }

    private function suggestUniqueProductUrl($base_value, $product_id)
    {
        $url = shopHelper::transliterate((string) $base_value);
        if ($url === '') {
            $url = 'product_' . $product_id;
        }

        $max_tries = 20;
        for ($try = 0; $try <= $max_tries; $try++) {
            $candidate = $url . ($try > 0 ? '_' . $try : '');
            if (!shopHelper::isProductUrlInUse(array('url' => $candidate, 'id' => $product_id))) {
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf($this->t('unique_url_failed'), $product_id));
    }

    private function calculateStockValue($old_value, array $request)
    {
        if ($request['stock_mode'] === 'infinite') {
            return null;
        }

        $input_value = (float) $request['stock_value'];
        if ($request['stock_mode'] === 'set') {
            return $input_value;
        }

        $old_value = $old_value === null ? 0.0 : (float) $old_value;
        $new_value = $request['stock_mode'] === 'increase'
            ? $old_value + $input_value
            : $old_value - $input_value;

        if ($new_value < 0) {
            throw new InvalidArgumentException($this->t('negative_stock_value'));
        }

        return round($new_value, 4);
    }

    private function calculateNumericValue($old_value, $mode, $input_value)
    {
        $old_value = (float) $old_value;
        $input_value = (float) $input_value;

        if ($mode === 'add') {
            $new_value = $old_value + $input_value;
        } elseif ($mode === 'subtract') {
            $new_value = $old_value - $input_value;
        } elseif ($mode === 'increase_percent') {
            $new_value = $old_value + ($old_value * $input_value / 100);
        } elseif ($mode === 'decrease_percent') {
            $new_value = $old_value - ($old_value * $input_value / 100);
        } else {
            $new_value = $input_value;
        }

        if ($new_value < 0) {
            throw new InvalidArgumentException($this->t('negative_value'));
        }

        return round($new_value, 4);
    }

    private function applyRounding($value, $step, $direction)
    {
        $value = (float) $value;
        if ($step === '') {
            return round($value, 4);
        }

        $digits = (int) $step;
        $multiplier = pow(10, $digits);
        $scaled_value = $value * $multiplier;

        if ($direction === 'up') {
            $rounded_value = ceil($scaled_value);
        } elseif ($direction === 'down') {
            $rounded_value = floor($scaled_value);
        } else {
            $rounded_value = round($scaled_value);
        }

        return round($rounded_value / $multiplier, 4);
    }

    private function calculatePriceComparePrice($old_price, $old_compare_price, $new_price, array $request)
    {
        $mode = $request['compare_price_mode'];

        if ($mode === 'keep') {
            return $old_compare_price;
        }
        if ($mode === 'set_old_price') {
            return $old_price;
        }
        if ($mode === 'clear') {
            return null;
        }
        if ($mode === 'coefficient') {
            return round($new_price * (float) $request['compare_price_value'], 4);
        }

        return $old_compare_price;
    }

    private function getOperationLabel($operation)
    {
        $labels = array(
            'price' => $this->t('operation_price'),
            'compare_price' => $this->t('operation_compare_price'),
            'visibility' => $this->t('operation_visibility'),
            'availability' => $this->t('operation_availability'),
            'description' => $this->t('operation_description'),
            'tags' => $this->t('operation_tags'),
            'url' => $this->t('operation_url'),
            'stock' => $this->t('operation_stock'),
            'features' => $this->t('operation_features'),
            'categories' => $this->t('operation_categories'),
            'video' => $this->t('operation_video'),
        );

        return isset($labels[$operation]) ? $labels[$operation] : (string) $operation;
    }

    private function formatVisibilityStatus($status)
    {
        if ((int) $status === 1) {
            return $this->t('published');
        }
        if ((int) $status === 0) {
            return $this->t('hidden');
        }
        if ((int) $status === -1) {
            return $this->t('unpublished');
        }

        return (string) $status;
    }

    private function buildSummary(array $request, $count)
    {
        return sprintf(
            '%s · %s',
            $this->getOperationLabel($request['operation']),
            sprintf($this->tp('%d product', '%d products', (int) $count), (int) $count)
        );
    }

    private function buildDescription(array $request, $count)
    {
        if (in_array($request['operation'], self::PRICE_OPERATIONS, true)) {
            $key = $this->priceDescriptionKey($request['mode']);
            return sprintf($this->t($key), $this->getOperationLabel($request['operation']), $this->formatNumber($request['numeric_value']), (int) $count);
        }

        if ($request['operation'] === 'visibility') {
            return sprintf($this->t('description_visibility'), $this->formatVisibilityStatus($request['visibility_status']), (int) $count);
        }

        if ($request['operation'] === 'availability') {
            return sprintf($this->t('description_availability'), $request['availability_value'] ? $this->t('available') : $this->t('unavailable'), (int) $count);
        }

        if ($request['operation'] === 'description') {
            return sprintf($this->t('description_description'), $request['description_mode'], (int) $count);
        }

        if ($request['operation'] === 'tags') {
            return sprintf($this->t('description_tags'), $request['tags_mode'], (int) $count);
        }

        if ($request['operation'] === 'url') {
            return sprintf($this->t('description_url'), $request['url_mode'], (int) $count);
        }

        if ($request['operation'] === 'stock') {
            return sprintf($this->t('description_stock'), $request['stock_name'], $request['stock_mode'], (int) $count);
        }

        if ($request['operation'] === 'features') {
            return sprintf($this->t('description_features'), $request['feature_name'], $request['feature_mode'], (int) $count);
        }

        if ($request['operation'] === 'video') {
            return sprintf($this->t('description_video'), $request['video_mode'], (int) $count);
        }

        return sprintf($this->t('description_categories'), $request['category_name'], $request['categories_mode'], (int) $count);
    }

    private function priceDescriptionKey($mode)
    {
        $keys = array(
            'set' => 'description_price_set',
            'add' => 'description_price_add',
            'subtract' => 'description_price_subtract',
            'increase_percent' => 'description_price_increase_percent',
            'decrease_percent' => 'description_price_decrease_percent',
        );

        return isset($keys[$mode]) ? $keys[$mode] : 'description_price_set';
    }

    private function formatNumber($value)
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    private function normalizeNonNegativeNumber($value, $message_key)
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ($value === '' || !is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException($this->t($message_key));
        }

        return (float) $value;
    }

    private function isValidHttpUrl($value)
    {
        if (strlen((string) $value) > 255) {
            return false;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, array('http', 'https'), true);
    }

    private function normalizeStockMode($value)
    {
        $value = (string) $value;
        if (!in_array($value, array('set', 'increase', 'decrease', 'infinite'), true)) {
            throw new InvalidArgumentException($this->t('invalid_stock_mode'));
        }

        return $value;
    }

    private function normalizeStockTypeFilter($value)
    {
        $value = (string) $value;
        if (!in_array($value, array('all', 'without_warehouse', 'with_warehouse'), true)) {
            return 'all';
        }

        return $value;
    }

    private function normalizeFeatureMode($value, $multiple = false)
    {
        $value = (string) $value;
        $allowed = $multiple ? array('replace', 'add', 'remove', 'clear') : array('set', 'clear');
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException($this->t('invalid_feature_mode'));
        }

        return $value;
    }

    private function normalizeCategoriesMode($value)
    {
        $value = (string) $value;
        if (!in_array($value, array('add', 'remove', 'replace_main'), true)) {
            throw new InvalidArgumentException($this->t('invalid_categories_mode'));
        }

        return $value;
    }

    private function resolveStocks($stock_id)
    {
        if ((int) $stock_id <= 0) {
            throw new InvalidArgumentException($this->t('validation_stock'));
        }

        $rows = $this->model
            ->query('SELECT id, name FROM shop_stock ORDER BY sort ASC, id ASC')
            ->fetchAll();
        $selected = null;
        $ids = array();
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $ids[] = $id;
            if ($id === (int) $stock_id) {
                $selected = $row;
            }
        }

        if (!$selected) {
            throw new InvalidArgumentException($this->t('validation_stock'));
        }

        return array(
            'selected' => $selected,
            'ids' => $ids,
        );
    }

    private function resolveCategory($category_id)
    {
        if ((int) $category_id <= 0) {
            throw new InvalidArgumentException($this->t('validation_category'));
        }

        $rows = $this->model
            ->query('SELECT id, name FROM shop_category WHERE id = i:category_id', array('category_id' => (int) $category_id))
            ->fetchAll();
        if (!$rows) {
            throw new InvalidArgumentException($this->t('validation_category'));
        }

        return reset($rows);
    }

    private function resolveFeature($feature_id)
    {
        if ((int) $feature_id <= 0) {
            throw new InvalidArgumentException($this->t('validation_feature'));
        }

        $rows = $this->model
            ->query(
                'SELECT id, code, name, type, selectable, multiple, parent_id FROM shop_feature WHERE id = i:feature_id',
                array('feature_id' => (int) $feature_id)
            )
            ->fetchAll();
        if (!$rows) {
            throw new InvalidArgumentException($this->t('validation_feature'));
        }

        $feature = reset($rows);
        if (!empty($feature['parent_id'])) {
            throw new InvalidArgumentException($this->t('unsupported_feature_type'));
        }

        if (!isset($feature['code']) || trim((string) $feature['code']) === '') {
            throw new InvalidArgumentException($this->t('validation_feature'));
        }

        if (!$this->featureTypeConfig(isset($feature['type']) ? $feature['type'] : '')) {
            throw new InvalidArgumentException($this->t('unsupported_feature_type'));
        }

        return $feature;
    }

    private function normalizeFeatureValueIds(array $raw_ids)
    {
        if (count($raw_ids) > self::FEATURE_VALUES_LIMIT) {
            throw new InvalidArgumentException($this->t('feature_values_limit'));
        }

        $ids = array();
        foreach ($raw_ids as $raw_id) {
            $id = (int) $raw_id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function loadFeatureValuesByIds(array $feature, array $value_ids)
    {
        if (!$value_ids) {
            return array();
        }

        $suffix = $this->featureValueTableSuffix(isset($feature['type']) ? $feature['type'] : '');
        if (!$suffix) {
            throw new InvalidArgumentException($this->t('unsupported_feature_type'));
        }

        $value_ids = array_values(array_unique(array_map('intval', $value_ids)));
        $placeholders = implode(',', array_fill(0, count($value_ids), '?'));
        $params = array_merge(array((int) $feature['id']), $value_ids);
        $rows = $this->model
            ->query(
                'SELECT id, feature_id, value
                 FROM shop_feature_values_' . $suffix . '
                 WHERE feature_id = ? AND id IN (' . $placeholders . ')',
                $params
            )
            ->fetchAll();

        $values = array();
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > 0 && isset($row['feature_id']) && (int) $row['feature_id'] === (int) $feature['id']) {
                $values[$id] = $row['value'];
            }
        }

        if (count($values) !== count($value_ids)) {
            throw new InvalidArgumentException($this->t('validation_feature_existing_value'));
        }

        return $values;
    }

    private function loadCurrentFeatureValueIds(array $product_ids, $feature_id)
    {
        if (!$product_ids) {
            return array();
        }

        $product_ids = array_values(array_unique(array_map('intval', $product_ids)));
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $params = array_merge(array((int) $feature_id), $product_ids);
        $rows = $this->model
            ->query(
                'SELECT product_id, feature_value_id
                 FROM shop_product_features
                 WHERE feature_id = ?
                   AND product_id IN (' . $placeholders . ')
                   AND sku_id IS NULL',
                $params
            )
            ->fetchAll();

        $result = array();
        foreach ($rows as $row) {
            $product_id = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            $value_id = isset($row['feature_value_id']) ? (int) $row['feature_value_id'] : 0;
            if ($product_id > 0 && $value_id > 0) {
                $result[$product_id][$value_id] = $value_id;
            }
        }

        foreach ($result as $product_id => $value_ids) {
            $result[$product_id] = array_values($value_ids);
        }

        return $result;
    }

    private function resolveOrCreateFeatureValueId(array $feature, $value)
    {
        $id = $this->findFeatureValueId($feature, $value);
        if ($id > 0) {
            return $id;
        }

        $suffix = $this->featureValueTableSuffix(isset($feature['type']) ? $feature['type'] : '');
        if (!$suffix) {
            throw new InvalidArgumentException($this->t('unsupported_feature_type'));
        }

        $this->model->exec(
            'INSERT INTO shop_feature_values_' . $suffix . ' (feature_id, value) VALUES (i:feature_id, :value)',
            array('feature_id' => (int) $feature['id'], 'value' => $value)
        );

        $id = $this->findFeatureValueId($feature, $value);
        if ($id <= 0) {
            throw new InvalidArgumentException($this->t('validation_feature_existing_value'));
        }

        return $id;
    }

    private function findFeatureValueId(array $feature, $value)
    {
        $suffix = $this->featureValueTableSuffix(isset($feature['type']) ? $feature['type'] : '');
        if (!$suffix) {
            throw new InvalidArgumentException($this->t('unsupported_feature_type'));
        }

        $rows = $this->model
            ->query(
                'SELECT id FROM shop_feature_values_' . $suffix . ' WHERE feature_id = i:feature_id AND value = :value',
                array('feature_id' => (int) $feature['id'], 'value' => $value)
            )
            ->fetchAll();
        if (!$rows) {
            return 0;
        }

        $row = reset($rows);
        return isset($row['id']) ? (int) $row['id'] : 0;
    }

    private function normalizeFeatureValue(array $feature, $value)
    {
        $config = $this->featureTypeConfig(isset($feature['type']) ? $feature['type'] : '');
        if (!$config) {
            throw new InvalidArgumentException($this->t('unsupported_feature_type'));
        }

        if ($config['validate'] === 'numeric') {
            $value = str_replace(',', '.', trim((string) $value));
            if ($value === '' || !is_numeric($value)) {
                throw new InvalidArgumentException($this->t('validation_feature_numeric_value'));
            }

            return (float) $value;
        }

        if ($config['validate'] === 'boolean') {
            $value = trim((string) $value);
            if ($value !== '1' && $value !== '0') {
                throw new InvalidArgumentException($this->t('validation_feature_boolean_value'));
            }

            return $value;
        }

        return trim((string) $value);
    }

    private function featureBaseType($type)
    {
        $type = strtolower((string) $type);
        if (strpos($type, '.') !== false) {
            $type = substr($type, 0, strpos($type, '.'));
        }

        return $type;
    }

    private function featureTypeConfig($type)
    {
        $base = $this->featureBaseType($type);

        $configs = array(
            'varchar'   => array('table' => 'varchar', 'validate' => 'string'),
            'text'      => array('table' => 'text',    'validate' => 'string'),
            'double'    => array('table' => 'double',  'validate' => 'numeric'),
            'int'       => array('table' => 'double',  'validate' => 'numeric'),
            'boolean'   => array('table' => 'varchar', 'validate' => 'boolean'),
            'color'     => array('table' => 'color',     'validate' => 'string'),
            'dimension' => array('table' => 'dimension', 'validate' => 'numeric'),
            'range'     => array('table' => 'range',     'validate' => 'numeric'),
            'select'    => array('table' => 'varchar', 'validate' => 'string'),
            'radio'     => array('table' => 'varchar', 'validate' => 'string'),
        );

        return isset($configs[$base]) ? $configs[$base] : null;
    }

    private function featureValueTableSuffix($type)
    {
        $config = $this->featureTypeConfig($type);

        return $config ? $config['table'] : null;
    }

    public function getFeatureValues($feature_id, $table_suffix)
    {
        $allowed_suffixes = array('varchar', 'text', 'double', 'range', 'color', 'dimension');
        if (!in_array($table_suffix, $allowed_suffixes, true)) {
            return array();
        }

        $rows = $this->model
            ->query(
                'SELECT id, value FROM shop_feature_values_' . $table_suffix . ' WHERE feature_id = i:feature_id ORDER BY value ASC',
                array('feature_id' => (int) $feature_id)
            )
            ->fetchAll();

        return $rows ? $rows : array();
    }

    public function getFeatureValuesMap(array $features)
    {
        $groups = array();
        foreach ($features as $feature) {
            if (empty($feature['selectable']) && empty($feature['multiple'])) {
                continue;
            }
            $feature_id = isset($feature['id']) ? (int) $feature['id'] : 0;
            $suffix = $this->featureValueTableSuffix(isset($feature['type']) ? $feature['type'] : '');
            if ($feature_id > 0 && $suffix) {
                $groups[$suffix][$feature_id] = $feature_id;
            }
        }

        $result = array();
        foreach ($groups as $suffix => $feature_ids) {
            $feature_ids = array_values($feature_ids);
            $feature_ids_map = array_fill_keys($feature_ids, true);
            $placeholders = implode(',', array_fill(0, count($feature_ids), '?'));
            $rows = $this->model
                ->query(
                    'SELECT id, feature_id, value FROM shop_feature_values_' . $suffix
                    . ' WHERE feature_id IN (' . $placeholders . ') ORDER BY feature_id ASC, value ASC',
                    $feature_ids
                )
                ->fetchAll();
            foreach ($rows as $row) {
                $feature_id = isset($row['feature_id']) ? (int) $row['feature_id'] : 0;
                if (isset($feature_ids_map[$feature_id])) {
                    $result[$feature_id][] = $row;
                }
            }
        }

        return $result;
    }

    public function getFeatureUiConfig($type)
    {
        $base = $this->featureBaseType($type);

        $ui_map = array(
            'varchar'   => 'text',
            'text'      => 'textarea',
            'double'    => 'number',
            'int'       => 'number',
            'boolean'   => 'boolean_select',
            'color'     => 'text',
            'dimension' => 'number',
            'range'     => 'number',
            'select'    => 'select',
            'radio'     => 'select',
        );

        $ui = isset($ui_map[$base]) ? $ui_map[$base] : 'text';
        $table = $this->featureValueTableSuffix($type);

        return array('ui' => $ui, 'table' => $table);
    }

    private function normalizeOperationLimit($operation_limit)
    {
        $operation_limit = (int) $operation_limit;
        if ($operation_limit <= 0) {
            return self::DEFAULT_OPERATION_LIMIT;
        }

        return min(1000, $operation_limit);
    }

    private function normalizeRoundStep($value)
    {
        $allowed = array('', '0', '1', '2', '3', '4');
        $value = (string) $value;
        if (!in_array($value, $allowed, true)) {
            return '';
        }

        return $value;
    }

    private function normalizeRoundDirection($value)
    {
        $allowed = array('nearest', 'up', 'down');
        $value = (string) $value;
        if (!in_array($value, $allowed, true)) {
            return 'nearest';
        }

        return $value;
    }

    private function normalizeComparePriceMode($value)
    {
        $allowed = array('keep', 'set_old_price', 'clear', 'coefficient');
        $value = (string) $value;
        if (!in_array($value, $allowed, true)) {
            return 'keep';
        }

        return $value;
    }

    private function normalizeDescriptionMode($value)
    {
        $allowed = array('replace', 'prepend', 'append');
        $value = (string) $value;
        if (!in_array($value, $allowed, true)) {
            return 'replace';
        }

        return $value;
    }

    private function normalizeTagsMode($value)
    {
        $allowed = array('add', 'remove', 'replace');
        $value = (string) $value;
        if (!in_array($value, $allowed, true)) {
            return 'add';
        }

        return $value;
    }

    private function normalizeTagList($value)
    {
        $parts = preg_split('/[\r\n,]+/', (string) $value);
        $result = array();
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $result[] = $part;
            }
        }

        return array_values(array_unique($result));
    }

    private function normalizeUrlMode($value)
    {
        $allowed = array('regenerate', 'template');
        $value = (string) $value;
        if (!in_array($value, $allowed, true)) {
            return 'regenerate';
        }

        return $value;
    }

    private function assertAdminRights()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new RuntimeException($this->t('admin_required'));
        }
    }

    private function t($key)
    {
        return shopMasseditorPluginI18nService::t($key, $this->language);
    }

    private function tp($singular, $plural, $count)
    {
        return shopMasseditorPluginI18nService::tp($singular, $plural, $count, $this->language);
    }
}
