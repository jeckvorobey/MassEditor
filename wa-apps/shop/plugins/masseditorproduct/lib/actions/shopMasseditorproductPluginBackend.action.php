<?php

class shopMasseditorproductPluginBackendAction extends waViewAction
{
    public function execute()
    {
        $this->assertAdminRights();

        /** @var shopMasseditorproductPlugin $plugin */
        $plugin = wa('shop')->getPlugin('masseditorproduct');
        $settings = $this->getPluginSettings($plugin);
        $language = $settings['interface_language'];
        $texts = shopMasseditorproductPluginI18nService::getTexts($language);
        $selection_service = new shopMasseditorproductPluginProductSelectionService();
        $log_service = new shopMasseditorproductPluginLogService();
        $operation_service = new shopMasseditorproductPluginMassOperationService(
            $selection_service,
            $log_service,
            null,
            $settings['operation_limit'],
            $language
        );

        $log_service->purgeOlderThanDays($settings['log_retention_days']);

        $filters = array(
            'query' => waRequest::get('query', '', waRequest::TYPE_STRING_TRIM),
            'status' => waRequest::get('status', 'all', waRequest::TYPE_STRING_TRIM),
            'availability' => waRequest::get('availability', 'all', waRequest::TYPE_STRING_TRIM),
            'category_id' => waRequest::get('category_id', 0, waRequest::TYPE_INT),
            'page' => waRequest::get('page', 1, waRequest::TYPE_INT),
        );
        $log_page = waRequest::get('log_page', 1, waRequest::TYPE_INT);
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
                    $language = $settings['interface_language'];
                    $texts = shopMasseditorproductPluginI18nService::getTexts($language);
                    $selection = $selection_service->getPage($filters, $settings['page_size']);
                    $result_message = $texts['settings_saved'];
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
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
                $this->restorePostStateAfterError($operation_form, $selected_product_ids, $active_tab);
            } catch (Exception $e) {
                $this->logUnexpectedException($e);
                $errors[] = $texts['generic_operation_error'];
                $this->restorePostStateAfterError($operation_form, $selected_product_ids, $active_tab);
            }
        }

        $categories = $selection_service->getCategories();
        $log_selection = $log_service->getPage($log_page, 20);
        $recent_logs = $this->decorateLogs($log_selection['logs'], $language);
        $last_log = $this->decorateLogs($log_service->getLatest(1), $language);
        $last_log = $last_log ? reset($last_log) : null;
        $operations = $this->getOperationsLibrary($settings['show_soon_operations'], $language);

        $this->view->assign(array(
            'page_title' => $plugin->getName(),
            'plugin_id' => $plugin->getId(),
            'plugin_static_url' => $plugin->getPluginStaticUrl(),
            'products' => $this->decorateProducts($selection['products']),
            'categories' => $categories,
            'filters' => $selection['filters'],
            'pagination' => $selection['pagination'],
            'pagination_ui' => $this->buildPaginationUi($selection['pagination']),
            'errors' => $errors,
            'result_message' => $result_message,
            'recent_logs' => $recent_logs,
            'recent_logs_count' => $log_selection['pagination']['total'],
            'log_pagination' => $log_selection['pagination'],
            'log_pagination_ui' => $this->buildPaginationUi($log_selection['pagination']),
            'last_log' => $last_log,
            'operation_form' => $operation_form,
            'operations' => $operations,
            'selected_product_ids_map' => array_fill_keys($selected_product_ids, true),
            'has_active_filters' => $selection['filters']['query'] !== '' || $selection['filters']['status'] !== 'all' || $selection['filters']['availability'] !== 'all' || !empty($selection['filters']['category_id']),
            'filter_reset_url' => '?plugin=' . $plugin->getId() . '&view=products',
            'active_tab' => $active_tab,
            'settings' => $settings,
            'date_format_options' => $this->getDateFormatOptions(),
            'interface_language_options' => $this->getInterfaceLanguageOptions($language),
            'interface_language_setting' => $settings['interface_language_setting'],
            'language' => $language,
            'texts' => $texts,
            'js_i18n_json' => json_encode(shopMasseditorproductPluginI18nService::getJsTexts($language), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
            'theme_class' => $settings['theme_mode'] === 'dark' ? 'theme-dark' : '',
        ));
    }

    private function buildPaginationUi(array $pagination)
    {
        $page = isset($pagination['page']) ? max(1, (int) $pagination['page']) : 1;
        $pages = isset($pagination['pages']) ? max(1, (int) $pagination['pages']) : 1;
        $page_size = isset($pagination['page_size']) ? max(1, (int) $pagination['page_size']) : 1;
        $total = isset($pagination['total']) ? max(0, (int) $pagination['total']) : 0;

        $from = $total > 0 ? (($page - 1) * $page_size) + 1 : 0;
        $to = $total > 0 ? min($total, $page * $page_size) : 0;

        $start = max(1, $page - 2);
        $end = min($pages, $page + 2);
        if (($end - $start) < 4) {
            if ($start === 1) {
                $end = min($pages, $start + 4);
            }
            if ($end === $pages) {
                $start = max(1, $end - 4);
            }
        }

        $items = array();

        if ($start > 1) {
            $items[] = array('type' => 'page', 'value' => 1, 'active' => $page === 1);
            if ($start > 2) {
                $items[] = array('type' => 'ellipsis');
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $items[] = array('type' => 'page', 'value' => $i, 'active' => $page === $i);
        }

        if ($end < $pages) {
            if ($end < $pages - 1) {
                $items[] = array('type' => 'ellipsis');
            }
            $items[] = array('type' => 'page', 'value' => $pages, 'active' => $page === $pages);
        }

        return array(
            'from' => $from,
            'to' => $to,
            'total' => $total,
            'has_prev' => $page > 1,
            'prev_page' => max(1, $page - 1),
            'has_next' => $page < $pages,
            'next_page' => min($pages, $page + 1),
            'items' => $items,
        );
    }

    private function decorateProducts(array $products)
    {
        foreach ($products as &$product) {
            $product['price_view'] = $this->formatDecimalForView(isset($product['price']) ? $product['price'] : '');
            $product['compare_price_view'] = $this->formatDecimalForView(isset($product['compare_price']) ? $product['compare_price'] : '');
            $product['description_preview'] = $this->buildDescriptionPreview(isset($product['description']) ? $product['description'] : '');
            $product['edit_datetime_view'] = $this->formatDateForView(
                isset($product['edit_datetime']) ? $product['edit_datetime'] : '',
                $this->getCurrentDateFormat()
            );
        }
        unset($product);

        return $products;
    }

    private function formatDecimalForView($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (strpos($value, '.') === false) {
            return $value;
        }

        $value = rtrim($value, '0');
        $value = rtrim($value, '.');

        if ($value === '' || $value === '-0') {
            return '0';
        }

        return $value;
    }

    private function buildDescriptionPreview($value)
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8')));
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > 160 ? mb_substr($text, 0, 157, 'UTF-8') . '...' : $text;
        }

        return strlen($text) > 160 ? substr($text, 0, 157) . '...' : $text;
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
                $current_form[$key] = $this->normalizeOperationFormValue($key, $payload[$key]);
            }
        }

        return $current_form;
    }

    private function normalizeOperationFormValue($key, $value)
    {
        if ($key === 'tags_value' && is_array($value)) {
            $tags = array();
            foreach ($value as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }

            return implode(', ', array_values(array_unique($tags)));
        }

        return $value;
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

    private function restorePostStateAfterError(array &$operation_form, array &$selected_product_ids, &$active_tab)
    {
        if (waRequest::post('do_apply', 0, waRequest::TYPE_INT)) {
            $operation_payload = $this->readOperationPayload();
            $operation_form = $this->mergeOperationForm($operation_form, $operation_payload);
            $selected_product_ids = $this->normalizeSelectedProductIds($operation_payload['product_ids']);
            $active_tab = 'products';
            return;
        }

        $active_tab = 'settings';
    }

    private function logUnexpectedException(Exception $e)
    {
        $message = get_class($e) . ': ' . $e->getMessage();
        if (class_exists('waLog')) {
            waLog::log($message, 'shop/plugins/masseditorproduct.log');
            return;
        }

        error_log($message);
    }

    private function decorateLogs(array $logs, $language = shopMasseditorproductPluginI18nService::RU)
    {
        $user_names = $this->resolveUserNames($logs);
        $date_format = $this->getCurrentDateFormat();

        foreach ($logs as &$log) {
            $log['action_label'] = $this->getActionLabel(isset($log['action_type']) ? $log['action_type'] : '', $language);
            $user_id = isset($log['user_id']) ? (int) $log['user_id'] : 0;
            $log['user_name'] = isset($user_names[$user_id]) ? $user_names[$user_id] : null;
            $log['created_at_view'] = $this->formatDateForView(
                isset($log['created_at']) ? $log['created_at'] : '',
                $date_format
            );
        }
        unset($log);

        return $logs;
    }

    private function resolveUserNames(array $logs)
    {
        $user_ids = array();

        foreach ($logs as $log) {
            $user_id = isset($log['user_id']) ? (int) $log['user_id'] : 0;
            if ($user_id > 0) {
                $user_ids[$user_id] = $user_id;
            }
        }

        if (!$user_ids) {
            return array();
        }

        $names = array();
        foreach ($user_ids as $user_id) {
            try {
                $contact = new waContact($user_id);
                $name = trim($contact->getName());
                if ($name !== '') {
                    $names[$user_id] = $name;
                }
            } catch (Exception $e) {
            }
        }

        return $names;
    }

    private function getActionLabel($action_type, $language = shopMasseditorproductPluginI18nService::RU)
    {
        if ($action_type === 'price') {
            return shopMasseditorproductPluginI18nService::t('action_price', $language);
        }
        if ($action_type === 'compare_price') {
            return shopMasseditorproductPluginI18nService::t('action_compare_price', $language);
        }
        if ($action_type === 'visibility') {
            return shopMasseditorproductPluginI18nService::t('action_visibility', $language);
        }
        if ($action_type === 'availability') {
            return shopMasseditorproductPluginI18nService::t('action_availability', $language);
        }

        return (string) $action_type;
    }

    private function getPluginSettings(shopMasseditorproductPlugin $plugin)
    {
        return array(
            'page_size' => $this->normalizeIntSetting($plugin->getSettings('page_size'), 50, 10, 200),
            'operation_limit' => $this->normalizeIntSetting($plugin->getSettings('operation_limit'), 100, 1, 1000),
            'log_retention_days' => $this->normalizeIntSetting($plugin->getSettings('log_retention_days'), 90, 1, 3650),
            'date_format' => $this->normalizeDateFormat($plugin->getSettings('date_format')),
            'theme_mode' => $this->normalizeThemeMode($plugin->getSettings('theme_mode')),
            'show_soon_operations' => (int) !!$plugin->getSettings('show_soon_operations'),
            'interface_language_setting' => shopMasseditorproductPluginI18nService::normalizeSetting($plugin->getSettings('interface_language')),
            'interface_language' => shopMasseditorproductPluginI18nService::resolveLanguage($plugin->getSettings('interface_language')),
        );
    }

    private function savePluginSettings(shopMasseditorproductPlugin $plugin, array $current_settings)
    {
        $settings = array(
            'page_size' => $this->normalizeIntSetting(waRequest::post('page_size', $current_settings['page_size'], waRequest::TYPE_INT), 50, 10, 200),
            'operation_limit' => $this->normalizeIntSetting(waRequest::post('operation_limit', $current_settings['operation_limit'], waRequest::TYPE_INT), 100, 1, 1000),
            'log_retention_days' => $this->normalizeIntSetting(waRequest::post('log_retention_days', $current_settings['log_retention_days'], waRequest::TYPE_INT), 90, 1, 3650),
            'date_format' => $this->normalizeDateFormat(waRequest::post('date_format', $current_settings['date_format'], waRequest::TYPE_STRING_TRIM)),
            'theme_mode' => $this->normalizeThemeMode(waRequest::post('theme_mode', $current_settings['theme_mode'], waRequest::TYPE_STRING_TRIM)),
            'show_soon_operations' => waRequest::post('show_soon_operations', 0, waRequest::TYPE_INT) ? 1 : 0,
            'interface_language' => shopMasseditorproductPluginI18nService::normalizeSetting(waRequest::post('interface_language', $current_settings['interface_language_setting'], waRequest::TYPE_STRING_TRIM)),
        );

        $plugin->saveSettings($settings);
        $settings['interface_language_setting'] = $settings['interface_language'];
        $settings['interface_language'] = shopMasseditorproductPluginI18nService::resolveLanguage($settings['interface_language_setting']);

        return $settings;
    }

    private function assertAdminRights()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new RuntimeException(shopMasseditorproductPluginI18nService::t('admin_required', shopMasseditorproductPluginI18nService::resolveLanguage(self::getPluginLanguageSetting())));
        }
    }

    private static function getPluginLanguageSetting()
    {
        try {
            $plugin = wa('shop')->getPlugin('masseditorproduct');
            if ($plugin) {
                return $plugin->getSettings('interface_language');
            }
        } catch (Exception $e) {
        }

        return shopMasseditorproductPluginI18nService::AUTO;
    }

    private function normalizeIntSetting($value, $default, $min, $max)
    {
        $value = (int) $value;
        if ($value <= 0) {
            $value = $default;
        }

        return min($max, max($min, $value));
    }

    private function normalizeDateFormat($value)
    {
        $value = (string) $value;
        $options = $this->getDateFormatOptions();

        return isset($options[$value]) ? $value : 'd.m.Y H:i';
    }

    private function normalizeThemeMode($value)
    {
        $value = (string) $value;
        if (!in_array($value, array('auto', 'light', 'dark'), true)) {
            return 'auto';
        }

        return $value;
    }

    private function getCurrentDateFormat()
    {
        /** @var shopMasseditorproductPlugin $plugin */
        $plugin = wa('shop')->getPlugin('masseditorproduct');

        return $this->normalizeDateFormat($plugin->getSettings('date_format'));
    }

    private function formatDateForView($value, $format)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date($format, $timestamp);
    }

    private function getDateFormatOptions()
    {
        return array(
            'd.m.Y H:i' => '26.04.2026 14:30',
            'd.m.Y H:i:s' => '26.04.2026 14:30:45',
            'Y-m-d H:i' => '2026-04-26 14:30',
            'm/d/Y h:i A' => '04/26/2026 02:30 PM',
        );
    }

    private function getInterfaceLanguageOptions($language)
    {
        $raw_options = shopMasseditorproductPluginI18nService::getLanguageOptions();
        $options = array();
        foreach ($raw_options as $value => $titles) {
            $options[$value] = isset($titles[$language]) ? $titles[$language] : $value;
        }

        return $options;
    }

    private function getOperationsLibrary($show_soon_operations, $language = shopMasseditorproductPluginI18nService::RU)
    {
        $groups = array(
            array(
                'title' => shopMasseditorproductPluginI18nService::t('group_prices', $language),
                'items' => array(
                    array('id' => 'price', 'label' => shopMasseditorproductPluginI18nService::t('operation_price', $language), 'enabled' => true),
                    array('id' => 'compare_price', 'label' => shopMasseditorproductPluginI18nService::t('operation_compare_price', $language), 'enabled' => true),
                    array('id' => 'sku_generator', 'label' => 'SKU generator', 'enabled' => false),
                ),
            ),
            array(
                'title' => shopMasseditorproductPluginI18nService::t('group_content', $language),
                'items' => array(
                    array('id' => 'visibility', 'label' => shopMasseditorproductPluginI18nService::t('operation_visibility', $language), 'enabled' => true),
                    array('id' => 'availability', 'label' => shopMasseditorproductPluginI18nService::t('operation_availability', $language), 'enabled' => true),
                    array('id' => 'description', 'label' => shopMasseditorproductPluginI18nService::t('operation_description', $language), 'enabled' => true),
                    array('id' => 'tags', 'label' => shopMasseditorproductPluginI18nService::t('operation_tags', $language), 'enabled' => true),
                ),
            ),
            array(
                'title' => shopMasseditorproductPluginI18nService::t('group_media', $language),
                'items' => array(
                    array('id' => 'product_images', 'label' => shopMasseditorproductPluginI18nService::t('product_images', $language), 'enabled' => false),
                    array('id' => 'video', 'label' => shopMasseditorproductPluginI18nService::t('video', $language), 'enabled' => false),
                ),
            ),
            array(
                'title' => shopMasseditorproductPluginI18nService::t('group_links', $language),
                'items' => array(
                    array('id' => 'cross_selling', 'label' => 'Cross-selling', 'enabled' => false),
                    array('id' => 'similar_products', 'label' => 'Similar products', 'enabled' => false),
                ),
            ),
            array(
                'title' => shopMasseditorproductPluginI18nService::t('group_url_pages', $language),
                'items' => array(
                    array('id' => 'url', 'label' => shopMasseditorproductPluginI18nService::t('operation_url', $language), 'enabled' => true),
                    array('id' => 'product_pages', 'label' => shopMasseditorproductPluginI18nService::t('product_pages', $language), 'enabled' => false),
                ),
            ),
            array(
                'title' => shopMasseditorproductPluginI18nService::t('group_features', $language),
                'items' => array(
                    array('id' => 'features', 'label' => shopMasseditorproductPluginI18nService::t('features', $language), 'enabled' => false),
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
