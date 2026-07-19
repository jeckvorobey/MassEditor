<?php

class shopMasseditorPluginRollbackModel extends waModel
{
    const MUTATION_LOCK_NAME = 'shop_masseditor_mutation';

    protected $table = 'shop_masseditor_rollback';

    public function createSnapshot(array $data)
    {
        return $this->insert($data);
    }

    public function insertSnapshotItems($rollback_id, array $rows, $batch_size = 20)
    {
        $rollback_id = (int) $rollback_id;
        if ($rollback_id <= 0 || !$rows) {
            return;
        }

        $batch_size = min(100, max(1, (int) $batch_size));
        foreach (array_chunk($rows, $batch_size) as $chunk) {
            $values = array();
            $params = array();
            foreach ($chunk as $index => $row) {
                $values[] = '(i:rollback_id_' . $index . ', i:product_id_' . $index
                    . ', s:before_state_' . $index . ', s:after_state_' . $index . ')';
                $params['rollback_id_' . $index] = $rollback_id;
                $params['product_id_' . $index] = (int) $row['product_id'];
                $params['before_state_' . $index] = (string) $row['before_state'];
                $params['after_state_' . $index] = (string) $row['after_state'];
            }

            $this->exec(
                'INSERT INTO shop_masseditor_rollback_item
                    (rollback_id, product_id, before_state, after_state)
                 VALUES ' . implode(', ', $values),
                $params
            );
        }
    }

    public function acquireMutationLock($timeout = 5)
    {
        $timeout = min(10, max(0, (int) $timeout));
        $result = $this->query(
            'SELECT GET_LOCK(s:lock_name, i:timeout)',
            array(
                'lock_name' => self::MUTATION_LOCK_NAME,
                'timeout' => $timeout,
            )
        )->fetchField();

        return (int) $result === 1;
    }

    public function releaseMutationLock()
    {
        $result = $this->query(
            'SELECT RELEASE_LOCK(s:lock_name)',
            array('lock_name' => self::MUTATION_LOCK_NAME)
        )->fetchField();

        return (int) $result === 1;
    }

    public function findEligibleSnapshot($log_id, $user_id, $now)
    {
        $rows = $this->query(
            'SELECT r.id AS rollback_id, r.log_id, r.user_id, r.action_type,
                    r.snapshot_version, l.entity_count
             FROM shop_masseditor_rollback r
             JOIN shop_masseditor_log l ON l.id = r.log_id
             WHERE r.log_id = i:log_id
               AND r.user_id = i:user_id
               AND r.rolled_back_at IS NULL
               AND r.expires_at >= s:now
               AND r.snapshot_version = i:snapshot_version
               AND l.id = (SELECT MAX(id) FROM shop_masseditor_log)
               AND l.action_type = r.action_type
             LIMIT 1',
            array(
                'log_id' => (int) $log_id,
                'user_id' => (int) $user_id,
                'now' => (string) $now,
                'snapshot_version' => shopMasseditorPluginRollbackService::SNAPSHOT_VERSION,
            )
        )->fetchAll();

        return $rows ? reset($rows) : null;
    }

    public function findLatestEligibleSnapshot($user_id, $now)
    {
        $rows = $this->query(
            'SELECT r.id AS rollback_id, r.log_id
             FROM shop_masseditor_rollback r
             JOIN shop_masseditor_log l ON l.id = r.log_id
             WHERE r.user_id = i:user_id
               AND r.rolled_back_at IS NULL
               AND r.expires_at >= s:now
               AND r.snapshot_version = i:snapshot_version
               AND l.id = (SELECT MAX(id) FROM shop_masseditor_log)
               AND l.action_type = r.action_type
             LIMIT 1',
            array(
                'user_id' => (int) $user_id,
                'now' => (string) $now,
                'snapshot_version' => shopMasseditorPluginRollbackService::SNAPSHOT_VERSION,
            )
        )->fetchAll();

        return $rows ? reset($rows) : null;
    }

    public function getSnapshotStats($rollback_id)
    {
        $rows = $this->query(
            'SELECT COUNT(*) AS item_count,
                    COALESCE(SUM(OCTET_LENGTH(before_state) + OCTET_LENGTH(after_state)), 0) AS total_bytes
             FROM shop_masseditor_rollback_item
             WHERE rollback_id = i:rollback_id',
            array('rollback_id' => (int) $rollback_id)
        )->fetchAll();

        return $rows ? reset($rows) : array('item_count' => 0, 'total_bytes' => 0);
    }

    public function getSnapshotItems($rollback_id, $batch_size = 100)
    {
        $rollback_id = (int) $rollback_id;
        $batch_size = min(100, max(1, (int) $batch_size));
        $last_product_id = 0;
        $items = array();

        do {
            $rows = $this->query(
            'SELECT product_id, before_state, after_state
             FROM shop_masseditor_rollback_item
             WHERE rollback_id = i:rollback_id AND product_id > i:last_product_id
             ORDER BY product_id
             LIMIT ' . $batch_size,
                array(
                    'rollback_id' => $rollback_id,
                    'last_product_id' => $last_product_id,
                )
            )->fetchAll();
            foreach ($rows as $row) {
                $product_id = isset($row['product_id']) ? (int) $row['product_id'] : 0;
                if ($product_id <= $last_product_id) {
                    throw new RuntimeException('Invalid rollback item order.');
                }
                $last_product_id = $product_id;
                $items[] = $row;
            }
        } while (count($rows) === $batch_size);

        return $items;
    }

    public function purgeExpired($now)
    {
        $params = array('now' => (string) $now);
        $this->exec(
            'DELETE i FROM shop_masseditor_rollback_item i
             INNER JOIN shop_masseditor_rollback r ON r.id = i.rollback_id
             WHERE r.expires_at < s:now',
            $params
        );

        return $this->exec(
            'DELETE FROM shop_masseditor_rollback WHERE expires_at < s:now',
            $params
        );
    }

    public function markRolledBack($rollback_id, $user_id, $rolled_back_at)
    {
        return $this->exec(
            'UPDATE shop_masseditor_rollback
             SET rolled_back_at = s:rolled_back_at, rolled_back_by = i:user_id
             WHERE id = i:rollback_id AND rolled_back_at IS NULL',
            array(
                'rolled_back_at' => (string) $rolled_back_at,
                'user_id' => (int) $user_id,
                'rollback_id' => (int) $rollback_id,
            )
        );
    }

    public function resetRolledBack($rollback_id)
    {
        return $this->exec(
            'UPDATE shop_masseditor_rollback
             SET rolled_back_at = NULL, rolled_back_by = NULL
             WHERE id = i:rollback_id',
            array('rollback_id' => (int) $rollback_id)
        );
    }

    public function deleteSnapshot($rollback_id)
    {
        $rollback_id = (int) $rollback_id;
        if ($rollback_id <= 0) {
            return;
        }
        $this->exec(
            'DELETE FROM shop_masseditor_rollback_item WHERE rollback_id = i:rollback_id',
            array('rollback_id' => $rollback_id)
        );
        $this->exec(
            'DELETE FROM shop_masseditor_rollback WHERE id = i:rollback_id',
            array('rollback_id' => $rollback_id)
        );
    }
}
