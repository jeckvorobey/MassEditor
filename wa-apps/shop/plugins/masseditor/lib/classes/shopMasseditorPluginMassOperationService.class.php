<?php

class shopMasseditorPluginMassOperationService
{
    const PREVIEW_LIMIT = 100;
    const APPLY_BATCH_SIZE = 20;

    /**
     * @var shopMasseditorPluginProductSelectionService
     */
    private $selection_service;

    /**
     * @var shopMasseditorPluginLogService
     */
    private $log_service;

    /**
     * @var waModel
     */
    private $model;

    public function __construct(
        shopMasseditorPluginProductSelectionService $selection_service = null,
        shopMasseditorPluginLogService $log_service = null,
        waModel $model = null
    ) {
        $this->selection_service = $selection_service ?: new shopMasseditorPluginProductSelectionService();
        $this->log_service = $log_service ?: new shopMasseditorPluginLogService();
        $this->model = $model ?: new waModel();
    }

    public function preview(array $raw_request)
    {
        $request = $this->normalizeRequest($raw_request, false);
        $products = $this->selection_service->getByIds($request['product_ids'], self::PREVIEW_LIMIT);
        $this->assertSelectedProductsLoaded($products, $request['product_ids']);
        $rows = $this->buildPreviewRows($products, $request);

        return array(
            'request' => $request,
            'rows' => $rows,
            'summary' => $this->buildSummary($request, count($rows)),
        );
    }

    public function apply(array $raw_request)
    {
        $request = $this->normalizeRequest($raw_request, true);
        $products = $this->selection_service->getByIds($request['product_ids']);
        $this->assertSelectedProductsLoaded($products, $request['product_ids']);
        $rows = $this->buildPreviewRows($products, $request);

        $needs_skus = in_array($request['operation'], array('price', 'compare_price', 'availability'), true);
        $skus_by_product = $needs_skus ? $this->loadSkusByProducts($products) : array();

        // Оборачиваем пакетное изменение в транзакцию, чтобы не оставлять частично примененный результат.
        $this->model->exec('START TRANSACTION');

        try {
            $chunks = array_chunk($products, self::APPLY_BATCH_SIZE, true);

            foreach ($chunks as $chunk) {
                foreach ($chunk as $product_data) {
                    $skus = isset($skus_by_product[$product_data['id']]) ? $skus_by_product[$product_data['id']] : array();
                    $this->applyToProduct($product_data, $request, $skus);
                }
            }

            $this->log_service->log(
                $request['operation'],
                count($products),
                $this->buildDescription($request, count($products))
            );

            $this->model->exec('COMMIT');
        } catch (Exception $e) {
            $this->model->exec('ROLLBACK');
            throw $e;
        }

        return array(
            'request' => $request,
            'rows' => $rows,
            'summary' => $this->buildSummary($request, count($rows)),
            'message' => 'Операция успешно применена.',
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
            throw new InvalidArgumentException('Выберите хотя бы один товар.');
        }

        if (count($product_ids) > self::PREVIEW_LIMIT) {
            throw new InvalidArgumentException('За одну операцию можно обработать не более ' . self::PREVIEW_LIMIT . ' товаров.');
        }

        $operation = isset($raw_request['operation']) ? (string) $raw_request['operation'] : '';
        if (!in_array($operation, array('price', 'compare_price', 'visibility', 'availability'), true)) {
            throw new InvalidArgumentException('Неизвестная массовая операция.');
        }

        $request = array(
            'product_ids' => array_values($product_ids),
            'operation' => $operation,
            'mode' => null,
            'numeric_value' => null,
            'visibility_status' => null,
            'availability_value' => null,
        );

        if (in_array($operation, array('price', 'compare_price'), true)) {
            $mode = isset($raw_request['mode']) ? (string) $raw_request['mode'] : 'set';
            if (!in_array($mode, array('set', 'percent'), true)) {
                throw new InvalidArgumentException('Неизвестный режим изменения цены.');
            }

            $value = isset($raw_request['numeric_value']) ? str_replace(',', '.', trim((string) $raw_request['numeric_value'])) : '';
            if ($value === '' || !is_numeric($value)) {
                throw new InvalidArgumentException('Укажите корректное числовое значение.');
            }

            $request['mode'] = $mode;
            $request['numeric_value'] = (float) $value;
        } elseif ($operation === 'visibility') {
            $status = isset($raw_request['visibility_status']) ? (int) $raw_request['visibility_status'] : 1;
            if (!in_array($status, array(1, 0, -1), true)) {
                throw new InvalidArgumentException('Некорректное значение видимости.');
            }

            $request['visibility_status'] = $status;
        } elseif ($operation === 'availability') {
            $availability = isset($raw_request['availability_value']) ? (int) $raw_request['availability_value'] : 1;
            if (!in_array($availability, array(0, 1), true)) {
                throw new InvalidArgumentException('Некорректное значение доступности.');
            }

            $request['availability_value'] = $availability;
        }

        if ($require_confirmation && empty($raw_request['confirm_apply'])) {
            throw new InvalidArgumentException('Подтвердите применение изменений.');
        }

        return $request;
    }

    private function buildPreviewRows(array $products, array $request)
    {
        if (!$products) {
            throw new InvalidArgumentException('Выбранные товары не найдены.');
        }

        $needs_skus = in_array($request['operation'], array('price', 'compare_price', 'availability'), true);
        $skus_by_product = $needs_skus ? $this->loadSkusByProducts($products) : array();

        $rows = array();

        foreach ($products as $product_data) {
            $row = array(
                'id' => $product_data['id'],
                'name' => $product_data['name'],
                'operation_label' => $this->getOperationLabel($request['operation']),
                'old_value' => '',
                'new_value' => '',
                'details' => '',
            );

            if ($needs_skus) {
                $skus = isset($skus_by_product[$product_data['id']]) ? $skus_by_product[$product_data['id']] : array();
                $main_sku = $this->resolveMainSku($product_data, $skus);

                if (!$main_sku) {
                    throw new RuntimeException('Не удалось определить основную модификацию товара ID ' . (int) $product_data['id'] . '.');
                }

                if ($request['operation'] === 'price') {
                    $row['old_value'] = $this->formatNumber($main_sku['price']);
                    $row['new_value'] = $this->formatNumber(
                        $this->calculateNumericValue($main_sku['price'], $request['mode'], $request['numeric_value'])
                    );
                    $row['details'] = 'Изменение будет применено ко всем SKU товара.';
                } elseif ($request['operation'] === 'compare_price') {
                    $row['old_value'] = $this->formatNumber($main_sku['compare_price']);
                    $row['new_value'] = $this->formatNumber(
                        $this->calculateNumericValue($main_sku['compare_price'], $request['mode'], $request['numeric_value'])
                    );
                    $row['details'] = 'Изменение будет применено ко всем SKU товара.';
                } elseif ($request['operation'] === 'availability') {
                    $row['old_value'] = $this->summarizeAvailability($skus);
                    $row['new_value'] = $request['availability_value'] ? 'Доступен' : 'Недоступен';
                    $row['details'] = 'Флаг available будет изменен у всех SKU товара.';
                }
            } elseif ($request['operation'] === 'visibility') {
                $row['old_value'] = $this->formatVisibilityStatus($product_data['status']);
                $row['new_value'] = $this->formatVisibilityStatus($request['visibility_status']);
                $row['details'] = 'Изменяется поле shop_product.status.';
            }

            $rows[] = $row;
        }

        return $rows;
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
        // Перед применением работаем только с полностью подтвержденным набором товаров.
        if (count($products) !== count($requested_ids)) {
            throw new InvalidArgumentException('Часть выбранных товаров не найдена. Повторите выбор перед применением.');
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

        // Сохраняем через shopProduct, чтобы Shop-Script синхронизировал связанные данные.
        $product = new shopProduct((int) $product_data['id']);
        $product_skus = $skus ?: $product->getSkus();

        foreach ($product_skus as $sku_id => $sku) {
            if ($request['operation'] === 'price') {
                $product_skus[$sku_id]['price'] = $this->calculateNumericValue($sku['price'], $request['mode'], $request['numeric_value']);
            } elseif ($request['operation'] === 'compare_price') {
                $product_skus[$sku_id]['compare_price'] = $this->calculateNumericValue($sku['compare_price'], $request['mode'], $request['numeric_value']);
            } elseif ($request['operation'] === 'availability') {
                $product_skus[$sku_id]['available'] = $request['availability_value'];
            }
        }

        $product['skus'] = $product_skus;
        $product->save();
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
            throw new InvalidArgumentException('Итоговое значение не может быть отрицательным.');
        }

        return round($new_value, 4);
    }

    private function resolveMainSku(array $product_data, array $skus)
    {
        $main_sku_id = isset($product_data['sku_id']) ? (int) $product_data['sku_id'] : 0;
        if ($main_sku_id > 0 && isset($skus[$main_sku_id])) {
            return $skus[$main_sku_id];
        }

        if ($skus) {
            return reset($skus);
        }

        return null;
    }

    private function summarizeAvailability(array $skus)
    {
        $has_available = false;
        $has_unavailable = false;

        foreach ($skus as $sku) {
            if (!empty($sku['available'])) {
                $has_available = true;
            } else {
                $has_unavailable = true;
            }
        }

        if ($has_available && $has_unavailable) {
            return 'Смешано';
        }

        return $has_available ? 'Доступен' : 'Недоступен';
    }

    private function getOperationLabel($operation)
    {
        if ($operation === 'price') {
            return 'Цена';
        }
        if ($operation === 'compare_price') {
            return 'Compare price';
        }
        if ($operation === 'visibility') {
            return 'Видимость';
        }

        return 'Доступность';
    }

    private function formatVisibilityStatus($status)
    {
        if ((int) $status === 1) {
            return 'Опубликован';
        }
        if ((int) $status === 0) {
            return 'Скрыт';
        }
        if ((int) $status === -1) {
            return 'Неопубликован';
        }

        return (string) $status;
    }

    private function buildSummary(array $request, $count)
    {
        $summary = 'Товаров в операции: ' . (int) $count . '. ';

        if (in_array($request['operation'], array('price', 'compare_price'), true)) {
            $summary .= $request['mode'] === 'percent'
                ? 'Режим: изменение на процент.'
                : 'Режим: установка фиксированного значения.';
        } elseif ($request['operation'] === 'visibility') {
            $summary .= 'Новое состояние: ' . $this->formatVisibilityStatus($request['visibility_status']) . '.';
        } else {
            $summary .= 'Новое состояние: ' . ($request['availability_value'] ? 'доступен' : 'недоступен') . '.';
        }

        return $summary;
    }

    private function buildDescription(array $request, $count)
    {
        if ($request['operation'] === 'price' || $request['operation'] === 'compare_price') {
            return sprintf(
                '%s: %s %s для %d товаров',
                $this->getOperationLabel($request['operation']),
                $request['mode'] === 'percent' ? 'процент' : 'значение',
                $request['numeric_value'],
                (int) $count
            );
        }

        if ($request['operation'] === 'visibility') {
            return sprintf(
                'Видимость: %s для %d товаров',
                $this->formatVisibilityStatus($request['visibility_status']),
                (int) $count
            );
        }

        return sprintf(
            'Доступность: %s для %d товаров',
            $request['availability_value'] ? 'доступен' : 'недоступен',
            (int) $count
        );
    }

    private function formatNumber($value)
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    private function assertAdminRights()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new RuntimeException('Недостаточно прав для массового редактирования товаров.');
        }
    }
}
