<?php

class shopMasseditorPluginMassOperationService
{
    const DEFAULT_OPERATION_LIMIT = 100;
    const APPLY_BATCH_SIZE = 20;
    const SKU_OPERATIONS = array('price', 'compare_price', 'availability');
    const PRICE_OPERATIONS = array('price', 'compare_price');
    const ALL_OPERATIONS = array('price', 'compare_price', 'visibility', 'availability', 'description', 'tags', 'url');

    private $selection_service;
    private $log_service;
    private $model;
    private $operation_limit;

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
    }

    public function apply(array $raw_request)
    {
        $request = $this->normalizeRequest($raw_request, true);
        $products = $this->selection_service->getByIds($request['product_ids']);
        $this->assertSelectedProductsLoaded($products, $request['product_ids']);
        $skus_by_product = $this->resolveSkusByProducts($products, $request['operation']);
        $count = count($products);

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
        );
    }

    private function normalizeRequest(array $raw_request, $require_confirmation)
    {
        $this->assertAdminRights();

        $product_ids = array();
        if (isset($raw_request['product_ids']) && is_array($raw_request['product_ids'])) {
            foreach ($raw_request['product_ids'] as $product_id) {
                $product_id = (int) $product_id;
                if ($product_id > 0) {
                    $product_ids[$product_id] = $product_id;
                }
            }
        }

        if (!$product_ids) {
            throw new InvalidArgumentException($this->t('validation_select_product'));
        }

        if (count($product_ids) > $this->operation_limit) {
            throw new InvalidArgumentException(
                $this->t('limit_error_prefix') . $this->operation_limit . $this->t('limit_error_suffix')
            );
        }

        $operation = isset($raw_request['operation']) ? trim((string) $raw_request['operation']) : '';
        if (!in_array($operation, self::ALL_OPERATIONS, true)) {
            throw new InvalidArgumentException($this->t('unknown_operation'));
        }

        $request = array(
            'product_ids' => array_values($product_ids),
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
        );

        if (in_array($operation, self::PRICE_OPERATIONS, true)) {
            $mode = isset($raw_request['mode']) ? (string) $raw_request['mode'] : 'set';
            if (!in_array($mode, array('set', 'percent'), true)) {
                throw new InvalidArgumentException($this->t('unknown_price_mode'));
            }

            $value = isset($raw_request['numeric_value']) ? str_replace(',', '.', trim((string) $raw_request['numeric_value'])) : '';
            if ($value === '' || !is_numeric($value)) {
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
        }

        if ($require_confirmation && empty($raw_request['confirm_apply'])) {
            throw new InvalidArgumentException($this->t('confirm_required'));
        }

        return $request;
    }

    private function resolveSkusByProducts(array $products, $operation)
    {
        if (in_array($operation, self::SKU_OPERATIONS, true)) {
            return $this->loadSkusByProducts($products);
        }

        return array();
    }

    private function loadSkusByProducts(array $products)
    {
        if (!$products) {
            return array();
        }

        $ids = array_map('intval', array_keys($products));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->model
            ->query(
                'SELECT id, product_id, price, compare_price, available
                 FROM shop_product_skus
                 WHERE product_id IN (' . $placeholders . ')',
                $ids
            )
            ->fetchAll();

        $result = array();
        foreach ($rows as $sku) {
            $result[$sku['product_id']][$sku['id']] = $sku;
        }

        return $result;
    }

    private function assertSelectedProductsLoaded(array $products, array $requested_ids)
    {
        if (count($products) !== count($requested_ids)) {
            throw new InvalidArgumentException($this->t('missing_products'));
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
            }
        }

        $product['skus'] = $product_skus;
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

    private function calculateNumericValue($old_value, $mode, $input_value)
    {
        $old_value = (float) $old_value;
        $input_value = (float) $input_value;

        if ($mode === 'percent') {
            $new_value = $old_value + ($old_value * $input_value / 100);
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
        $step = (float) $step;
        $value = (float) $value;
        if ($step <= 0) {
            return round($value, 4);
        }

        $ratio = $value / $step;
        if ($direction === 'up') {
            $ratio = ceil($ratio);
        } elseif ($direction === 'down') {
            $ratio = floor($ratio);
        } else {
            $ratio = round($ratio);
        }

        return round($ratio * $step, 4);
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
            $key = $request['mode'] === 'percent' ? 'description_price_percent' : 'description_price_set';
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

        return sprintf($this->t('description_url'), $request['url_mode'], (int) $count);
    }

    private function formatNumber($value)
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
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
        $allowed = array('', '1', '10', '100');
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
        return shopMasseditorPluginI18nService::t($key);
    }

    private function tp($singular, $plural, $count)
    {
        if (function_exists('_wp')) {
            return _wp($singular, $plural, $count);
        }

        return (int) $count === 1 ? $singular : $plural;
    }
}
