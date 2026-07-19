<?php

$model = new waModel();
$model->exec(
    'CREATE TABLE IF NOT EXISTS shop_masseditor_rollback (
        id INT(11) NOT NULL AUTO_INCREMENT,
        log_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        action_type VARCHAR(64) NOT NULL DEFAULT \'\',
        snapshot_version INT(11) NOT NULL DEFAULT 1,
        expires_at DATETIME NOT NULL,
        rolled_back_at DATETIME NULL DEFAULT NULL,
        rolled_back_by INT(11) NULL DEFAULT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY log_id (log_id),
        KEY user_id (user_id),
        KEY expires_at (expires_at)
    ) ENGINE=InnoDB'
);
$model->exec(
    'CREATE TABLE IF NOT EXISTS shop_masseditor_rollback_item (
        rollback_id INT(11) NOT NULL,
        product_id INT(11) NOT NULL,
        before_state MEDIUMTEXT NOT NULL,
        after_state MEDIUMTEXT NOT NULL,
        PRIMARY KEY (rollback_id, product_id),
        KEY product_id (product_id)
    ) ENGINE=InnoDB'
);
