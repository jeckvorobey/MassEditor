<?php

$model = new waModel();
$model->exec(
    'CREATE TABLE IF NOT EXISTS shop_masseditor_log (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NULL DEFAULT NULL,
        action_type VARCHAR(64) NOT NULL DEFAULT \'\',
        entity_count INT(11) NOT NULL DEFAULT 0,
        description TEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY created_at (created_at),
        KEY action_type (action_type)
    )'
);
