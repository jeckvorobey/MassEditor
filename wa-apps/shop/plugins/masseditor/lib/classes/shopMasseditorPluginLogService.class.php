<?php

class shopMasseditorPluginLogService
{
    /**
     * @var shopMasseditorPluginLogModel
     */
    private $log_model;

    public function __construct(shopMasseditorPluginLogModel $log_model = null)
    {
        $this->log_model = $log_model ?: new shopMasseditorPluginLogModel();
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
                 FROM shop_masseditor_log
                 ORDER BY id DESC
                 LIMIT ' . $limit
            )
            ->fetchAll();
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
}
