<?php

class shopMasseditorPluginBackendAction extends waViewAction
{
    public function execute()
    {
        /** @var shopMasseditorPlugin $plugin */
        $plugin = wa('shop')->getPlugin('masseditor');
        $selection_service = new shopMasseditorPluginProductSelectionService();
        $log_service = new shopMasseditorPluginLogService();
        $operation_service = new shopMasseditorPluginMassOperationService($selection_service, $log_service);

        // Все параметры фильтра читаются только из GET и сразу приводятся к ожидаемым типам.
        $filters = array(
            'query' => waRequest::get('query', '', waRequest::TYPE_STRING_TRIM),
            'status' => waRequest::get('status', 'all', waRequest::TYPE_STRING_TRIM),
            'page' => waRequest::get('page', 1, waRequest::TYPE_INT),
        );
        $selection = $selection_service->getPage($filters);
        $preview = null;
        $errors = array();
        $result_message = null;
        $operation_form = array(
            'operation' => 'price',
            'mode' => 'set',
            'numeric_value' => '',
            'visibility_status' => 1,
            'availability_value' => 1,
        );

        if (waRequest::getMethod() === 'post') {
            $operation_payload = array(
                'product_ids' => waRequest::post('product_ids', array(), waRequest::TYPE_ARRAY),
                'operation' => waRequest::post('operation', '', waRequest::TYPE_STRING_TRIM),
                'mode' => waRequest::post('mode', 'set', waRequest::TYPE_STRING_TRIM),
                'numeric_value' => waRequest::post('numeric_value', '', waRequest::TYPE_STRING_TRIM),
                'visibility_status' => waRequest::post('visibility_status', 1, waRequest::TYPE_INT),
                'availability_value' => waRequest::post('availability_value', 1, waRequest::TYPE_INT),
                'confirm_apply' => waRequest::post('confirm_apply', 0, waRequest::TYPE_INT),
            );
            $operation_form = array(
                'operation' => $operation_payload['operation'] ?: 'price',
                'mode' => $operation_payload['mode'] ?: 'set',
                'numeric_value' => $operation_payload['numeric_value'],
                'visibility_status' => $operation_payload['visibility_status'],
                'availability_value' => $operation_payload['availability_value'],
            );

            try {
                if (waRequest::post('do_preview', 0, waRequest::TYPE_INT)) {
                    $preview = $operation_service->preview($operation_payload);
                    $operation_form = $preview['request'];
                } elseif (waRequest::post('do_apply', 0, waRequest::TYPE_INT)) {
                    $result = $operation_service->apply($operation_payload);
                    $preview = null;
                    $result_message = $result['message'];
                    $selection = $selection_service->getPage($filters);
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $this->view->assign(array(
            'page_title' => $plugin->getName(),
            'plugin_id' => $plugin->getId(),
            'plugin_static_url' => $plugin->getPluginStaticUrl(),
            'products' => $selection['products'],
            'filters' => $selection['filters'],
            'pagination' => $selection['pagination'],
            'preview' => $preview,
            'errors' => $errors,
            'result_message' => $result_message,
            'recent_logs' => $log_service->getLatest(10),
            'operation_form' => $operation_form,
        ));
    }
}
