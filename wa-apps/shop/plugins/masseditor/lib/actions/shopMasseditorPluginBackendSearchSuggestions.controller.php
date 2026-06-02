<?php

class shopMasseditorPluginBackendSearchSuggestionsController extends waJsonController
{
    /**
     * @var shopMasseditorPluginProductSelectionService
     */
    private $selection_service;

    public function __construct(shopMasseditorPluginProductSelectionService $selection_service = null)
    {
        if (method_exists(get_parent_class($this), '__construct')) {
            parent::__construct();
        }
        $this->selection_service = $selection_service ?: new shopMasseditorPluginProductSelectionService();
    }

    public function execute()
    {
        $this->assertAdminRights();

        $query = waRequest::get('query', '', waRequest::TYPE_STRING_TRIM);
        $this->response = array('suggestions' => array());
        if ($this->textLength($query) < 2) {
            return;
        }

        $filters = array(
            'status' => waRequest::get('status', 'all', waRequest::TYPE_STRING_TRIM),
            'availability' => waRequest::get('availability', 'all', waRequest::TYPE_STRING_TRIM),
            'category_id' => waRequest::get('category_id', 0, waRequest::TYPE_INT),
        );

        $this->response = array(
            'suggestions' => $this->selection_service->getSearchSuggestions($filters, $query, 10),
        );
    }

    private function assertAdminRights()
    {
        $user = wa()->getUser();
        if (!$user || !$user->isAdmin('shop')) {
            throw new RuntimeException(shopMasseditorPluginI18nService::t('admin_required'));
        }
    }

    private function textLength($value)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen((string) $value, 'UTF-8');
        }

        return strlen((string) $value);
    }
}
