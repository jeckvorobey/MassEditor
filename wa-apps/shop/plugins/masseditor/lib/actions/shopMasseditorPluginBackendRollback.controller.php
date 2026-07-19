<?php

class shopMasseditorPluginBackendRollbackController extends waJsonController
{
    public function execute()
    {
        $language = shopMasseditorPluginI18nService::resolveLanguage($this->getPluginLanguageSetting());
        if (waRequest::getMethod() !== 'post') {
            $this->errors = array(shopMasseditorPluginI18nService::t('rollback_invalid_request', $language));
            return;
        }

        try {
            $this->assertAdminRights($language);
        } catch (RuntimeException $e) {
            $this->errors = array($e->getMessage());
            return;
        }

        /** @var shopMasseditorPlugin $plugin */
        $plugin = wa('shop')->getPlugin('masseditor');
        $operation_limit = $this->normalizeOperationLimit($plugin->getSettings('operation_limit'));
        $service = $this->createRollbackService($operation_limit, $language);

        try {
            $result = $service->rollback(
                waRequest::post('log_id', null),
                waRequest::post('confirm_rollback', 0, waRequest::TYPE_INT)
            );
            $this->response = array(
                'message' => isset($result['message'])
                    ? (string) $result['message']
                    : shopMasseditorPluginI18nService::t('rollback_success', $language),
                'reload' => true,
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

    protected function createRollbackService($operation_limit, $language)
    {
        return new shopMasseditorPluginRollbackService(
            null,
            null,
            new shopMasseditorPluginLogService(),
            $operation_limit,
            $language
        );
    }

    private function assertAdminRights($language)
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new RuntimeException(shopMasseditorPluginI18nService::t('admin_required', $language));
        }
    }

    private function normalizeOperationLimit($value)
    {
        $value = (int) $value;
        if ($value <= 0) {
            $value = shopMasseditorPluginMassOperationService::DEFAULT_OPERATION_LIMIT;
        }

        return min(1000, max(1, $value));
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
