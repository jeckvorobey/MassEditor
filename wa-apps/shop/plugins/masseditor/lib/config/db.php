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
);
