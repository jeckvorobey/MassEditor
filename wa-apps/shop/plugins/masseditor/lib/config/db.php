<?php

return array(
    'shop_masseditor_log' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'user_id' => array('int', 11, 'null' => 1, 'default' => null),
        'action_type' => array('varchar', 64, 'null' => 0, 'default' => ''),
        'entity_count' => array('int', 11, 'null' => 0, 'default' => '0'),
        'description' => array('text', 'null' => 1),
        'created_at' => array('datetime', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
            'created_at' => 'created_at',
            'action_type' => 'action_type',
        ),
    ),
    'shop_masseditor_rollback' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'log_id' => array('int', 11, 'null' => 0),
        'user_id' => array('int', 11, 'null' => 0),
        'action_type' => array('varchar', 64, 'null' => 0, 'default' => ''),
        'snapshot_version' => array('int', 11, 'null' => 0, 'default' => '1'),
        'expires_at' => array('datetime', 'null' => 0),
        'rolled_back_at' => array('datetime', 'null' => 1, 'default' => null),
        'rolled_back_by' => array('int', 11, 'null' => 1, 'default' => null),
        'created_at' => array('datetime', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => 'id',
            'log_id' => array('fields' => 'log_id', 'unique' => true),
            'user_id' => 'user_id',
            'expires_at' => 'expires_at',
        ),
    ),
    'shop_masseditor_rollback_item' => array(
        'rollback_id' => array('int', 11, 'null' => 0),
        'product_id' => array('int', 11, 'null' => 0),
        'before_state' => array('mediumtext', 'null' => 0),
        'after_state' => array('mediumtext', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('rollback_id', 'product_id'),
            'product_id' => 'product_id',
        ),
    ),
);
