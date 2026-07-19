<?php

class shopMasseditorPluginBackendApplyController extends waJsonController
{
    public function execute()
    {
        try {
            $this->assertAdminRights();
        } catch (RuntimeException $e) {
            $this->errors = array($e->getMessage());
            return;
        }

        /** @var shopMasseditorPlugin $plugin */
        $plugin = wa('shop')->getPlugin('masseditor');
        $language = shopMasseditorPluginI18nService::resolveLanguage($plugin->getSettings('interface_language'));
        $operation_limit = $this->normalizeOperationLimit($plugin->getSettings('operation_limit'));
        $request_service = new shopMasseditorPluginOperationRequestService();
        $operation_service = $this->createOperationService($operation_limit, $language);

        try {
            $result = $operation_service->apply($request_service->readPost());
            $this->response = array(
                'message' => $this->formatResultMessage($result, $language),
                'reload' => true,
                'reset_selection' => true,
            );
        } catch (InvalidArgumentException $e) {
            $this->errors = array($e->getMessage());
        } catch (Exception $e) {
            waLog::log(
                get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString(),
                'shop/plugins/masseditor.log'
            );
            $this->errors = array(shopMasseditorPluginI18nService::t('generic_operation_error', $language));
        }
    }

    protected function createOperationService($operation_limit, $language)
    {
        return new shopMasseditorPluginMassOperationService(
            new shopMasseditorPluginProductSelectionService(),
            new shopMasseditorPluginLogService(),
            null,
            $operation_limit,
            $language
        );
    }

    private function formatResultMessage(array $result, $language)
    {
        $summary = isset($result['summary']) ? trim((string) $result['summary']) : '';
        $message = isset($result['message']) ? trim((string) $result['message']) : '';
        $skipped = isset($result['skipped']) ? (int) $result['skipped'] : 0;
        $text = $summary !== '' && $message !== '' ? $summary . ' · ' . $message : ($summary !== '' ? $summary : $message);

        if ($skipped > 0) {
            $text .= ' · ' . sprintf(shopMasseditorPluginI18nService::t('stock_skipped_by_filter', $language), $skipped);
        }

        return $text;
    }

    private function normalizeOperationLimit($value)
    {
        $value = (int) $value;
        if ($value <= 0) {
            $value = shopMasseditorPluginMassOperationService::DEFAULT_OPERATION_LIMIT;
        }

        return min(1000, max(1, $value));
    }

    private function assertAdminRights()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new RuntimeException(
                shopMasseditorPluginI18nService::t(
                    'admin_required',
                    shopMasseditorPluginI18nService::resolveLanguage($this->getPluginLanguageSetting())
                )
            );
        }
    }

    private function getPluginLanguageSetting()
    {
        try {
            $plugin = wa('shop')->getPlugin('masseditor');
            return $plugin ? $plugin->getSettings('interface_language') : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
