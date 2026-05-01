<?php

class shopMasseditorproductPluginLogService
{
    /**
     * @var shopMasseditorproductPluginLogModel
     */
    private $log_model;

    public function __construct(shopMasseditorproductPluginLogModel $log_model = null)
    {
        $this->log_model = $log_model ?: new shopMasseditorproductPluginLogModel();
    }

    public function log($action_type, $entity_count, $description = null, $user_id = null)
    {
        $action_type = trim((string) $action_type);
        if ($action_type === '') {
            throw new InvalidArgumentException('Action type is required.');
        }

        $entity_count = max(0, (int) $entity_count);
        $user_id = $this->normalizeUserId($user_id);
        $description = $this->normalizeDescription($description);

        return $this->log_model->insert(array(
            'user_id' => $user_id,
            'action_type' => $action_type,
            'entity_count' => $entity_count,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function getLatest($limit = 10)
    {
        $limit = max(1, (int) $limit);

        return $this->log_model
            ->query(
                'SELECT id, user_id, action_type, entity_count, description, created_at
                 FROM shop_masseditorproduct_log
                 ORDER BY id DESC
                 LIMIT ' . $limit
            )
            ->fetchAll();
    }

    public function getPage($page = 1, $page_size = 20)
    {
        $page_size = $this->normalizePageSize($page_size);
        $total = (int) $this->log_model
            ->query('SELECT COUNT(*) FROM shop_masseditorproduct_log')
            ->fetchField();

        $page = $this->normalizePage($page, $total, $page_size);
        $offset = ($page - 1) * $page_size;

        $logs = $this->log_model
            ->query(
                'SELECT id, user_id, action_type, entity_count, description, created_at
                 FROM shop_masseditorproduct_log
                 ORDER BY id DESC
                 LIMIT ' . (int) $page_size . ' OFFSET ' . (int) $offset
            )
            ->fetchAll();

        return array(
            'logs' => $logs,
            'pagination' => array(
                'page' => $page,
                'page_size' => $page_size,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $page_size)),
            ),
        );
    }

    public function purgeOlderThanDays($days)
    {
        $days = (int) $days;
        if ($days <= 0) {
            return 0;
        }

        return $this->log_model->exec(
            'DELETE FROM shop_masseditorproduct_log
             WHERE created_at < s:threshold',
            array(
                'threshold' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days')),
            )
        );
    }

    private function normalizeUserId($user_id)
    {
        if ($user_id === null) {
            // По умолчанию журнал пишет ID текущего backend-пользователя.
            $current_user = wa()->getUser();
            if ($current_user) {
                $user_id = (int) $current_user->getId();
            }
        }

        $user_id = (int) $user_id;

        return $user_id > 0 ? $user_id : null;
    }

    private function normalizeDescription($description)
    {
        if ($description === null) {
            return null;
        }

        $description = trim((string) $description);

        return $description !== '' ? $description : null;
    }

    private function normalizePage($page, $total, $page_size)
    {
        $page = max(1, (int) $page);
        $pages = max(1, (int) ceil($total / $page_size));

        return min($page, $pages);
    }

    private function normalizePageSize($page_size)
    {
        $page_size = (int) $page_size;
        if ($page_size <= 0) {
            $page_size = 20;
        }

        return min(200, $page_size);
    }
}
