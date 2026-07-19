<?php

class shopMasseditorPluginOperationRequestService
{
    public function readPost()
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
            'selection_mode' => waRequest::post('selection_mode', 'ids', waRequest::TYPE_STRING_TRIM),
            'filters' => waRequest::post('filters', array(), waRequest::TYPE_ARRAY),
            'stock_id' => waRequest::post('stock_id', 0, waRequest::TYPE_INT),
            'stock_mode' => waRequest::post('stock_mode', 'set', waRequest::TYPE_STRING_TRIM),
            'stock_value' => waRequest::post('stock_value', '', waRequest::TYPE_STRING_TRIM),
            'stock_type_filter' => waRequest::post('stock_type_filter', 'all', waRequest::TYPE_STRING_TRIM),
            'feature_id' => waRequest::post('feature_id', 0, waRequest::TYPE_INT),
            'feature_mode' => waRequest::post('feature_mode', 'set', waRequest::TYPE_STRING_TRIM),
            'feature_value' => waRequest::post('feature_value', '', waRequest::TYPE_STRING_TRIM),
            'feature_value_ids' => waRequest::post('feature_value_ids', array(), waRequest::TYPE_ARRAY),
            'category_id' => waRequest::post('category_id', 0, waRequest::TYPE_INT),
            'categories_mode' => waRequest::post('categories_mode', 'add', waRequest::TYPE_STRING_TRIM),
            'video_mode' => waRequest::post('video_mode', 'set', waRequest::TYPE_STRING_TRIM),
            'video_url' => waRequest::post('video_url', '', waRequest::TYPE_STRING_TRIM),
            'confirm_apply' => waRequest::post('confirm_apply', 0, waRequest::TYPE_INT),
        );
    }
}
