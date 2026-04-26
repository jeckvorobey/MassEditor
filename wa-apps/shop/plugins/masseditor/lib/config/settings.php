<?php

return array(
    'page_size' => array(
        'title' => 'Размер страницы',
        'description' => 'Сколько товаров отображать в таблице.',
        'value' => '50',
        'control_type' => waHtmlControl::INPUT,
    ),
    'operation_limit' => array(
        'title' => 'Лимит товаров за операцию',
        'description' => 'Максимальное количество товаров, которое можно обработать за один запуск.',
        'value' => '100',
        'control_type' => waHtmlControl::INPUT,
    ),
    'log_retention_days' => array(
        'title' => 'Хранить журнал, дней',
        'description' => 'Через сколько дней старые записи журнала будут очищаться.',
        'value' => '90',
        'control_type' => waHtmlControl::INPUT,
    ),
    'date_format' => array(
        'title' => 'Формат даты',
        'description' => 'Как показывать дату и время во всех разделах плагина.',
        'value' => 'd.m.Y H:i',
        'control_type' => waHtmlControl::SELECT,
        'options' => array(
            array('value' => 'd.m.Y H:i', 'title' => 'd.m.Y H:i (26.04.2026 14:30)'),
            array('value' => 'd.m.Y H:i:s', 'title' => 'd.m.Y H:i:s (26.04.2026 14:30:45)'),
            array('value' => 'Y-m-d H:i', 'title' => 'Y-m-d H:i (2026-04-26 14:30)'),
            array('value' => 'm/d/Y h:i A', 'title' => 'm/d/Y h:i A (04/26/2026 02:30 PM)'),
        ),
    ),
    'theme_mode' => array(
        'title' => 'Режим темы',
        'description' => 'Оформление backend-экрана плагина.',
        'value' => 'auto',
        'control_type' => waHtmlControl::SELECT,
        'options' => array(
            array('value' => 'auto', 'title' => 'Auto'),
            array('value' => 'light', 'title' => 'Light'),
            array('value' => 'dark', 'title' => 'Dark'),
        ),
    ),
    'show_soon_operations' => array(
        'title' => 'Показывать операции "Скоро"',
        'description' => 'Показывать в библиотеке будущие, но пока неактивные операции.',
        'value' => '1',
        'control_type' => waHtmlControl::CHECKBOX,
    ),
);
