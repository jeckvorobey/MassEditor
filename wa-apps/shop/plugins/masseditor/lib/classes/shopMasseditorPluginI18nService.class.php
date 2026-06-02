<?php

class shopMasseditorPluginI18nService
{
    const RU = 'ru_RU';
    const EN = 'en_US';

    private static $messages = array(
        'plugin_name' => 'Mass Editor',
        'plugin_description' => 'Backend-only plugin for safe bulk product editing.',
        'subtitle' => 'Bulk product editing.',
        'nav_products' => 'Products',
        'nav_log' => 'Log',
        'nav_settings' => 'Settings',
        'tabs_aria_label' => 'Mass Editor sections',
        'stats_found' => 'Products found',
        'stats_selected' => 'Selected',
        'stats_last_operation' => 'Last operation',
        'no_data' => 'No data',
        'log_empty_short' => 'Log is empty',
        'products_word' => 'products',
        'records_word' => 'records',
        'filters' => 'Filters',
        'search' => 'Search',
        'search_placeholder' => 'Name, SKU, URL, description, tag, or category',
        'search_suggestions_loading' => 'Loading suggestions...',
        'status' => 'Status',
        'all' => 'All',
        'published_plural' => 'Published products',
        'hidden_plural' => 'Hidden products',
        'unpublished_plural' => 'Unpublished products',
        'category' => 'Category',
        'all_categories' => 'All categories',
        'availability' => 'Availability',
        'available' => 'Available',
        'unavailable' => 'Unavailable',
        'apply' => 'Apply',
        'reset' => 'Reset',
        'operations' => 'Operations',
        'soon' => 'Soon',
        'operation_parameters' => 'Operation parameters',
        'operation_hint' => 'Selected products will be processed only after confirmation in the modal window.',
        'mode' => 'Mode',
        'set_value' => 'Set value',
        'add_value' => 'Add value',
        'subtract_value' => 'Subtract value',
        'increase_percent' => 'Increase by percent',
        'decrease_percent' => 'Decrease by percent',
        'value' => 'Value',
        'rounding' => 'Decimal places',
        'no_rounding' => 'No rounding',
        'round_digits_0' => '0 digits after decimal point',
        'round_digits_1' => '1 digit after decimal point',
        'round_digits_2' => '2 digits after decimal point',
        'round_digits_3' => '3 digits after decimal point',
        'round_digits_4' => '4 digits after decimal point',
        'direction' => 'Direction',
        'round_nearest' => 'Nearest',
        'round_up' => 'Up',
        'round_down' => 'Down',
        'keep_unchanged' => 'Keep unchanged',
        'write_old_price' => 'Write old price',
        'clear' => 'Clear',
        'multiply_coefficient' => 'Multiply by coefficient',
        'coefficient' => 'Coefficient',
        'visibility' => 'Visibility',
        'published' => 'Published',
        'hidden' => 'Hidden',
        'unpublished' => 'Unpublished',
        'description_mode' => 'Description mode',
        'replace' => 'Replace',
        'prepend' => 'Add to beginning',
        'append' => 'Add to end',
        'text' => 'Text',
        'tags_mode' => 'Tags mode',
        'add' => 'Add',
        'remove' => 'Remove',
        'tags' => 'Tags',
        'tags_placeholder' => 'comma-separated or one per line',
        'url_mode' => 'URL mode',
        'regenerate_from_name' => 'Generate from name',
        'template' => 'Template',
        'url_template' => 'URL template',
        'ready_title' => 'Ready to apply',
        'ready_empty' => 'Select products to process.',
        'ready_selected_suffix' => 'action will be written to the log',
        'selected_counter_separator' => 'of',
        'id' => 'ID',
        'date' => 'Date',
        'name' => 'Name',
        'url' => 'URL',
        'description' => 'Description',
        'categories' => 'Categories',
        'price' => 'Price',
        'stock' => 'Stock',
        'stock_warehouses' => 'Warehouses',
        'changed' => 'Changed',
        'select_all_page' => 'Select all products on this page',
        'sku_missing' => 'SKU is not set',
        'products_not_found' => 'Products not found.',
        'change_filter' => 'Change the filter and try again.',
        'shown' => 'Shown',
        'pagination_products' => 'Product pagination',
        'pagination_log' => 'Log pagination',
        'prev_page' => 'Previous page',
        'next_page' => 'Next page',
        'mobile_default_operation' => 'Change price',
        'confirm_title' => 'Confirm bulk change',
        'confirm_hint' => 'Check the parameters before applying.',
        'close' => 'Close',
        'changed_products' => 'Products to be changed',
        'operation' => 'Operation',
        'confirm_warning_title' => 'This action cannot be automatically undone.',
        'confirm_warning_text' => 'The operation will be written to the log and available for audit.',
        'cancel' => 'Cancel',
        'apply_changes' => 'Apply changes',
        'user' => 'User',
        'count' => 'Count',
        'log_empty_title' => 'Log is empty.',
        'log_empty_hint' => 'Records will appear here after the first confirmed operation.',
        'settings_performance' => 'Performance',
        'page_size' => 'Page size',
        'page_size_hint' => 'How many products to show in the table.',
        'operation_limit' => 'Products per operation limit',
        'operation_limit_hint' => 'Maximum products per single run.',
        'log_retention' => 'Keep log, days',
        'log_retention_hint' => 'Old records will be cleaned automatically.',
        'date_format' => 'Date format',
        'date_format_hint' => 'Choose how dates and times are shown in all plugin sections.',
        'appearance' => 'Appearance',
        'theme_mode' => 'Theme mode',
        'theme_mode_hint' => 'Auto uses the default backend theme.',
        'theme_auto' => 'Auto',
        'theme_light' => 'Light',
        'theme_dark' => 'Dark',
        'interface_language' => 'Interface language',
        'interface_language_hint' => 'Defaults to the current Webasyst locale until you choose a language.',
        'language_ru' => 'Russian',
        'language_en' => 'English',
        'show_soon_operations' => 'Show "Soon" operations',
        'show_soon_operations_hint' => 'Controls future operations visibility in the library.',
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
        'save_settings' => 'Save settings',
        'settings_saved' => 'Settings saved.',
        'generic_operation_error' => 'The operation could not be completed. Try again or check the error log.',
        'admin_required' => 'Not enough rights to manage Mass Editor.',
        'action_price' => 'Price',
        'action_compare_price' => 'Compare price',
        'action_visibility' => 'Visibility',
        'action_availability' => 'Availability',
        'operation_price' => 'Change price',
        'operation_compare_price' => 'Change compare price',
        'operation_visibility' => 'Change visibility',
        'operation_availability' => 'Change availability',
        'operation_description' => 'Description',
        'operation_tags' => 'Tags',
        'operation_url' => 'Product URLs',
        'operation_sku_generator' => 'SKU generator',
        'operation_cross_selling' => 'Cross-selling',
        'operation_similar_products' => 'Similar products',
        'group_prices' => 'Prices and SKU',
        'group_content' => 'Content',
        'group_media' => 'Media',
        'group_links' => 'Links',
        'group_url_pages' => 'URLs and pages',
        'group_features' => 'Parameters and features',
        'compare_price' => 'Compare price',
        'product_images' => 'Product photos',
        'video' => 'Video',
        'product_pages' => 'Product pages',
        'features' => 'Features',
        'operation_stock' => 'Stock by warehouse',
        'operation_features' => 'Basic feature editing',
        'operation_categories' => 'Categories',
        'action_stock' => 'Stock',
        'action_features' => 'Features',
        'action_categories' => 'Categories',
        'stock_warehouse' => 'Warehouse',
        'select_stock' => 'No warehouse',
        'stock_no_warehouse' => 'No warehouse',
        'stock_mode' => 'Stock mode',
        'stock_set' => 'Set stock',
        'stock_increase' => 'Increase stock',
        'stock_decrease' => 'Decrease stock',
        'stock_infinite' => 'Make infinite',
        'stock_value' => 'Stock value',
        'feature' => 'Feature',
        'select_feature' => 'Select feature',
        'feature_mode' => 'Feature mode',
        'feature_value' => 'Feature value',
        'select_category' => 'Select category',
        'category_mode' => 'Category mode',
        'add_to_category' => 'Add to category',
        'remove_from_category' => 'Remove from category',
        'replace_main_category' => 'Replace main category',
        'select_all_found' => 'Select all found products',
        'filter_selection_summary' => 'All products by current filter',
        'toast_success' => 'Success',
        'toast_error' => 'Error',
        'toast_info' => 'Message',
        'toast_close' => 'Close notification',
        'validation_select_product' => 'Select at least one product.',
        'validation_numeric' => 'Enter a value for the bulk operation.',
        'validation_description' => 'Enter description text.',
        'validation_tags' => 'Enter at least one tag.',
        'validation_url_template' => 'Enter a URL template.',
        'validation_compare_coefficient' => 'Enter a compare price coefficient.',
        'validation_stock' => 'Select a warehouse.',
        'invalid_stock_mode' => 'Invalid stock mode.',
        'invalid_stock_value' => 'Enter a valid stock value.',
        'negative_stock_value' => 'Stock cannot become negative.',
        'validation_feature' => 'Select a feature.',
        'invalid_feature_mode' => 'Invalid feature mode.',
        'validation_feature_value' => 'Enter a feature value.',
        'unsupported_feature_type' => 'Feature type is not supported in this version.',
        'validation_feature_existing_value' => 'Select an existing feature value.',
        'validation_category' => 'Select a category.',
        'invalid_categories_mode' => 'Invalid category mode.',
        'value_from_product_name' => 'From product name',
        'operation_success' => 'Operation applied successfully.',
        'limit_error_prefix' => 'No more than ',
        'limit_error_suffix' => ' products can be processed in one operation.',
        'unknown_operation' => 'Unknown bulk operation.',
        'unknown_price_mode' => 'Unknown price change mode.',
        'invalid_numeric' => 'Enter a valid numeric value.',
        'invalid_visibility' => 'Invalid visibility value.',
        'invalid_availability' => 'Invalid availability value.',
        'confirm_required' => 'Confirm applying changes.',
        'missing_products' => 'Some selected products were not found. Select them again before applying.',
        'unique_url_failed' => 'Could not find a unique URL for product ID %d.',
        'negative_value' => 'The final value cannot be negative.',
        'description_price_set' => '%s: value %s for %d products',
        'description_price_add' => '%s: add value %s for %d products',
        'description_price_subtract' => '%s: subtract value %s for %d products',
        'description_price_increase_percent' => '%s: increase by %s%% for %d products',
        'description_price_decrease_percent' => '%s: decrease by %s%% for %d products',
        'description_visibility' => 'Visibility: %s for %d products',
        'description_availability' => 'Availability: %s for %d products',
        'description_description' => 'Description: mode %s for %d products',
        'description_tags' => 'Tags: mode %s for %d products',
        'description_url' => 'URL: mode %s for %d products',
        'description_stock' => 'Stock: %s, mode %s for %d products',
        'description_features' => 'Feature: %s, mode %s for %d products',
        'description_categories' => 'Category: %s, mode %s for %d products',
    );

    private static $jsKeys = array(
        'enabled',
        'disabled',
        'toast_success',
        'toast_error',
        'toast_info',
        'toast_close',
        'search_suggestions_loading',
        'operation_price',
        'operation_compare_price',
        'operation_visibility',
        'operation_availability',
        'operation_description',
        'operation_tags',
        'operation_url',
        'operation_stock',
        'operation_features',
        'operation_categories',
        'operation_parameters',
        'ready_empty',
        'ready_selected_suffix',
        'products_word',
        'stats_selected',
        'selected_counter_separator',
        'mobile_default_operation',
        'validation_select_product',
        'validation_numeric',
        'validation_description',
        'validation_tags',
        'validation_url_template',
        'validation_compare_coefficient',
        'validation_stock',
        'invalid_stock_value',
        'validation_feature',
        'validation_feature_value',
        'validation_category',
        'filter_selection_summary',
        'stock_infinite',
        'clear',
        'value_from_product_name',
    );

    private static $catalogs = array();

    public static function normalizeLanguageSetting($value)
    {
        $value = (string) $value;
        if (in_array($value, array(self::RU, self::EN), true)) {
            return $value;
        }

        return '';
    }

    public static function resolveLanguage($setting = null)
    {
        $setting = self::normalizeLanguageSetting($setting);
        if ($setting !== '') {
            return $setting;
        }

        try {
            $locale = wa()->getLocale();
        } catch (Exception $e) {
            $locale = self::RU;
        }

        return strpos((string) $locale, 'en') === 0 ? self::EN : self::RU;
    }

    public static function getLanguageOptions($language = null)
    {
        $texts = self::getTexts($language);

        return array(
            self::RU => isset($texts['language_ru']) ? $texts['language_ru'] : 'Russian',
            self::EN => isset($texts['language_en']) ? $texts['language_en'] : 'English',
        );
    }

    public static function t($key, $language = null, $default = null)
    {
        $message = self::msgid($key, $default);
        if ($language !== null) {
            return self::translateMsgid($message, $language);
        }

        return function_exists('_wp') ? _wp($message) : $message;
    }

    public static function tp($singular, $plural, $count, $language = null)
    {
        if ($language !== null) {
            return self::translatePlural($singular, $plural, (int) $count, $language);
        }

        if (function_exists('_wp')) {
            return _wp($singular, $plural, $count);
        }

        return (int) $count === 1 ? $singular : $plural;
    }

    public static function getTexts($language = null)
    {
        $texts = array();
        foreach (self::$messages as $key => $msgid) {
            $texts[$key] = self::t($key, $language);
        }

        return $texts;
    }

    public static function getJsTexts($language = null)
    {
        $texts = self::getTexts($language);
        $result = array();
        foreach (self::$jsKeys as $key) {
            $result[$key] = isset($texts[$key]) ? $texts[$key] : $key;
        }

        return $result;
    }

    public static function msgid($key, $default = null)
    {
        if (isset(self::$messages[$key])) {
            return self::$messages[$key];
        }

        return $default === null ? $key : $default;
    }

    private static function translateMsgid($msgid, $language)
    {
        $language = self::resolveLanguage($language);
        $catalog = self::loadCatalog($language);

        return isset($catalog['messages'][$msgid]) ? $catalog['messages'][$msgid] : $msgid;
    }

    private static function translatePlural($singular, $plural, $count, $language)
    {
        $language = self::resolveLanguage($language);
        $catalog = self::loadCatalog($language);
        if (isset($catalog['plurals'][$singular])) {
            $index = self::pluralIndex($language, $count);
            if (isset($catalog['plurals'][$singular][$index])) {
                return $catalog['plurals'][$singular][$index];
            }
        }

        return $count === 1 ? $singular : $plural;
    }

    private static function loadCatalog($language)
    {
        $language = self::resolveLanguage($language);
        if (isset(self::$catalogs[$language])) {
            return self::$catalogs[$language];
        }

        $catalog = array('messages' => array(), 'plurals' => array());
        $path = dirname(dirname(dirname(__FILE__))) . '/locale/' . $language . '/LC_MESSAGES/shop_masseditor.po';
        if (!is_readable($path)) {
            self::$catalogs[$language] = $catalog;
            return $catalog;
        }

        $entry = array();
        $current = null;
        foreach (file($path) as $line) {
            $line = trim($line);
            if ($line === '') {
                self::storeCatalogEntry($catalog, $entry);
                $entry = array();
                $current = null;
                continue;
            }
            if (strpos($line, '#') === 0) {
                continue;
            }
            if (preg_match('/^(msgid|msgid_plural|msgstr(?:\[(\d+)\])?)\s+"(.*)"$/', $line, $matches)) {
                $current = $matches[1];
                $value = stripcslashes($matches[3]);
                if (strpos($current, 'msgstr[') === 0) {
                    $entry['msgstr_plural'][(int) $matches[2]] = $value;
                } else {
                    $entry[$current] = $value;
                }
                continue;
            }
            if ($current !== null && preg_match('/^"(.*)"$/', $line, $matches)) {
                if (strpos($current, 'msgstr[') === 0) {
                    $entry['msgstr_plural'][(int) substr($current, 7, 1)] .= stripcslashes($matches[1]);
                } else {
                    $entry[$current] .= stripcslashes($matches[1]);
                }
            }
        }
        self::storeCatalogEntry($catalog, $entry);

        self::$catalogs[$language] = $catalog;
        return $catalog;
    }

    private static function storeCatalogEntry(array &$catalog, array $entry)
    {
        if (!isset($entry['msgid']) || $entry['msgid'] === '') {
            return;
        }

        if (isset($entry['msgid_plural'])) {
            $catalog['plurals'][$entry['msgid']] = isset($entry['msgstr_plural']) ? $entry['msgstr_plural'] : array();
            return;
        }

        if (isset($entry['msgstr']) && $entry['msgstr'] !== '') {
            $catalog['messages'][$entry['msgid']] = $entry['msgstr'];
        }
    }

    private static function pluralIndex($language, $count)
    {
        if ($language === self::RU) {
            $mod10 = $count % 10;
            $mod100 = $count % 100;
            if ($mod10 === 1 && $mod100 !== 11) {
                return 0;
            }
            if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
                return 1;
            }

            return 2;
        }

        return $count === 1 ? 0 : 1;
    }
}
