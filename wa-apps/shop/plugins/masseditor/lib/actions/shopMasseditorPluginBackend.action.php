<?php

class shopMasseditorPluginBackendAction extends waViewAction
{
    public function execute()
    {
        /** @var shopMasseditorPlugin $plugin */
        $plugin = wa('shop')->getPlugin('masseditor');
        $settings = $this->getPluginSettings($plugin);
        $selection_service = new shopMasseditorPluginProductSelectionService();
        $log_service = new shopMasseditorPluginLogService();
        $operation_service = new shopMasseditorPluginMassOperationService(
            $selection_service,
            $log_service,
            null,
            $settings['operation_limit']
        );

        $log_service->purgeOlderThanDays($settings['log_retention_days']);

        $filters = array(
            'query' => waRequest::get('query', '', waRequest::TYPE_STRING_TRIM),
            'status' => waRequest::get('status', 'all', waRequest::TYPE_STRING_TRIM),
            'category_id' => waRequest::get('category_id', 0, waRequest::TYPE_INT),
            'page' => waRequest::get('page', 1, waRequest::TYPE_INT),
        );
        $active_tab = waRequest::request('view', 'products', waRequest::TYPE_STRING_TRIM);
        if (!in_array($active_tab, array('products', 'log', 'settings'), true)) {
            $active_tab = 'products';
        }

        $selection = $selection_service->getPage($filters, $settings['page_size']);
        $errors = array();
        $result_message = null;
        $selected_product_ids = array();
        $operation_form = array(
            'operation' => 'price',
            'mode' => 'set',
            'numeric_value' => '',
            'round_step' => '',
            'round_direction' => 'nearest',
            'compare_price_mode' => 'keep',
            'compare_price_value' => '',
            'visibility_status' => 1,
            'availability_value' => 1,
            'description_mode' => 'replace',
            'text_value' => '',
            'tags_mode' => 'add',
            'tags_value' => '',
            'url_mode' => 'regenerate',
            'url_value' => '',
        );

        if (waRequest::getMethod() === 'post') {
            try {
                if (waRequest::post('save_settings', 0, waRequest::TYPE_INT)) {
                    $settings = $this->savePluginSettings($plugin, $settings);
                    $selection = $selection_service->getPage($filters, $settings['page_size']);
                    $result_message = 'Настройки сохранены.';
                    $active_tab = 'settings';
                } elseif (waRequest::post('do_apply', 0, waRequest::TYPE_INT)) {
                    $operation_payload = $this->readOperationPayload();
                    $operation_form = $this->mergeOperationForm($operation_form, $operation_payload);
                    $selected_product_ids = $this->normalizeSelectedProductIds($operation_payload['product_ids']);
                    $result = $operation_service->apply($operation_payload);
                    $result_message = $result['message'];
                    $selection = $selection_service->getPage($filters, $settings['page_size']);
                    $selected_product_ids = array();
                    $operation_form = $this->mergeOperationForm($operation_form, $result['request']);
                    $active_tab = 'products';
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
                if (waRequest::post('do_apply', 0, waRequest::TYPE_INT)) {
                    $operation_payload = $this->readOperationPayload();
                    $operation_form = $this->mergeOperationForm($operation_form, $operation_payload);
                    $selected_product_ids = $this->normalizeSelectedProductIds($operation_payload['product_ids']);
                    $active_tab = 'products';
                } else {
                    $active_tab = 'settings';
                }
            }
        }

        $categories = $selection_service->getCategories();
        $recent_logs = $this->decorateLogs($log_service->getLatest(20));
        $last_log = $recent_logs ? reset($recent_logs) : null;
        $operations = $this->getOperationsLibrary($settings['show_soon_operations']);

        $this->view->assign(array(
            'page_title' => $plugin->getName(),
            'plugin_id' => $plugin->getId(),
            'plugin_static_url' => $plugin->getPluginStaticUrl(),
            'products' => $selection['products'],
            'categories' => $categories,
            'filters' => $selection['filters'],
            'pagination' => $selection['pagination'],
            'errors' => $errors,
            'result_message' => $result_message,
            'recent_logs' => $recent_logs,
            'recent_logs_count' => count($recent_logs),
            'last_log' => $last_log,
            'operation_form' => $operation_form,
            'operations' => $operations,
            'selected_product_ids_map' => array_fill_keys($selected_product_ids, true),
            'has_active_filters' => $selection['filters']['query'] !== '' || $selection['filters']['status'] !== 'all' || !empty($selection['filters']['category_id']),
            'filter_reset_url' => '?plugin=' . $plugin->getId() . '&view=products',
            'active_tab' => $active_tab,
            'settings' => $settings,
            'theme_class' => $settings['theme_mode'] === 'dark' ? 'theme-dark' : '',
        ));
    }

    private function readOperationPayload()
    {
        return array(
            'product_ids' => waRequest::post('product_ids', array(), waRequest::TYPE_ARRAY),
            'operation' => waRequest::post('operation', 'price', waRequest::TYPE_STRING_TRIM),
            'mode' => waRequest::post('mode', 'set', waRequest::TYPE_STRING_TRIM),
            'numeric_value' => waRequest::post('numeric_value', '', waRequest::TYPE_STRING_TRIM),
            'round_step' => waRequest::post('round_step', '', waRequest::TYPE_STRING_TRIM),
            'round_direction' => waRequest::post('round_direction', 'nearest', waRequest::TYPE_STRING_TRIM),
            'compare_price_mode' => waRequest::post('compare_price_mode', 'keep', waRequest::TYPE_STRING_TRIM),
            'compare_price_value' => waRequest::post('compare_price_value', '', waRequest::TYPE_STRING_TRIM),
            'visibility_status' => waRequest::post('visibility_status', 1, waRequest::TYPE_INT),
            'availability_value' => waRequest::post('availability_value', 1, waRequest::TYPE_INT),
            'description_mode' => waRequest::post('description_mode', 'replace', waRequest::TYPE_STRING_TRIM),
            'text_value' => waRequest::post('text_value', '', waRequest::TYPE_STRING_TRIM),
            'tags_mode' => waRequest::post('tags_mode', 'add', waRequest::TYPE_STRING_TRIM),
            'tags_value' => waRequest::post('tags_value', '', waRequest::TYPE_STRING_TRIM),
            'url_mode' => waRequest::post('url_mode', 'regenerate', waRequest::TYPE_STRING_TRIM),
            'url_value' => waRequest::post('url_value', '', waRequest::TYPE_STRING_TRIM),
            'confirm_apply' => waRequest::post('confirm_apply', 0, waRequest::TYPE_INT),
        );
    }

    private function mergeOperationForm(array $current_form, array $payload)
    {
        foreach ($current_form as $key => $value) {
            if (array_key_exists($key, $payload)) {
                $current_form[$key] = $payload[$key];
            }
        }

        return $current_form;
    }

    private function normalizeSelectedProductIds(array $raw_ids)
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

    private function decorateLogs(array $logs)
    {
        foreach ($logs as &$log) {
            $log['action_label'] = $this->getActionLabel(isset($log['action_type']) ? $log['action_type'] : '');
        }
        unset($log);

        return $logs;
    }

    private function getActionLabel($action_type)
    {
        if ($action_type === 'price') {
            return 'Цена';
        }
        if ($action_type === 'compare_price') {
            return 'Compare price';
        }
        if ($action_type === 'visibility') {
            return 'Видимость';
        }
        if ($action_type === 'availability') {
            return 'Доступность';
        }

        return (string) $action_type;
    }

    private function getPluginSettings(shopMasseditorPlugin $plugin)
    {
        return array(
            'page_size' => $this->normalizeIntSetting($plugin->getSettings('page_size'), 50, 10, 200),
            'operation_limit' => $this->normalizeIntSetting($plugin->getSettings('operation_limit'), 100, 1, 1000),
            'log_retention_days' => $this->normalizeIntSetting($plugin->getSettings('log_retention_days'), 90, 1, 3650),
            'theme_mode' => $this->normalizeThemeMode($plugin->getSettings('theme_mode')),
            'show_soon_operations' => (int) !!$plugin->getSettings('show_soon_operations'),
        );
    }

    private function savePluginSettings(shopMasseditorPlugin $plugin, array $current_settings)
    {
        $settings = array(
            'page_size' => $this->normalizeIntSetting(waRequest::post('page_size', $current_settings['page_size'], waRequest::TYPE_INT), 50, 10, 200),
            'operation_limit' => $this->normalizeIntSetting(waRequest::post('operation_limit', $current_settings['operation_limit'], waRequest::TYPE_INT), 100, 1, 1000),
            'log_retention_days' => $this->normalizeIntSetting(waRequest::post('log_retention_days', $current_settings['log_retention_days'], waRequest::TYPE_INT), 90, 1, 3650),
            'theme_mode' => $this->normalizeThemeMode(waRequest::post('theme_mode', $current_settings['theme_mode'], waRequest::TYPE_STRING_TRIM)),
            'show_soon_operations' => waRequest::post('show_soon_operations', 0, waRequest::TYPE_INT) ? 1 : 0,
        );

        $plugin->saveSettings($settings);

        return $settings;
    }

    private function normalizeIntSetting($value, $default, $min, $max)
    {
        $value = (int) $value;
        if ($value <= 0) {
            $value = $default;
        }

        return min($max, max($min, $value));
    }

    private function normalizeThemeMode($value)
    {
        $value = (string) $value;
        if (!in_array($value, array('auto', 'light', 'dark'), true)) {
            return 'auto';
        }

        return $value;
    }

    private function getOperationsLibrary($show_soon_operations)
    {
        $groups = array(
            array(
                'title' => 'Цены и SKU',
                'items' => array(
                    array('id' => 'price', 'label' => 'Изменить цену', 'enabled' => true),
                    array('id' => 'compare_price', 'label' => 'Изменить compare price', 'enabled' => true),
                    array('id' => 'sku_generator', 'label' => 'SKU generator', 'enabled' => false),
                ),
            ),
            array(
                'title' => 'Контент',
                'items' => array(
                    array('id' => 'visibility', 'label' => 'Изменить видимость', 'enabled' => true),
                    array('id' => 'availability', 'label' => 'Изменить доступность', 'enabled' => true),
                    array('id' => 'description', 'label' => 'Описание', 'enabled' => true),
                    array('id' => 'tags', 'label' => 'Теги', 'enabled' => true),
                ),
            ),
            array(
                'title' => 'Медиа',
                'items' => array(
                    array('id' => 'product_images', 'label' => 'Фото товаров', 'enabled' => false),
                    array('id' => 'video', 'label' => 'Видео', 'enabled' => false),
                ),
            ),
            array(
                'title' => 'Связи',
                'items' => array(
                    array('id' => 'cross_selling', 'label' => 'Cross-selling', 'enabled' => false),
                    array('id' => 'similar_products', 'label' => 'Similar products', 'enabled' => false),
                ),
            ),
            array(
                'title' => 'URL и страницы',
                'items' => array(
                    array('id' => 'url', 'label' => 'URL товаров', 'enabled' => true),
                    array('id' => 'product_pages', 'label' => 'Страницы товара', 'enabled' => false),
                ),
            ),
            array(
                'title' => 'Параметры и характеристики',
                'items' => array(
                    array('id' => 'features', 'label' => 'Характеристики', 'enabled' => false),
                ),
            ),
        );

        if ($show_soon_operations) {
            return $groups;
        }

        foreach ($groups as &$group) {
            $group['items'] = array_values(array_filter($group['items'], wa_lambda('$item', 'return !empty($item["enabled"]);')));
        }
        unset($group);

        return array_values(array_filter($groups, wa_lambda('$group', 'return !empty($group["items"]);')));
    }
}
