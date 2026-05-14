<?php

return array(
    'page_size' => array(
        'title' => _wp('Page size'),
        'description' => _wp('How many products to show in the table.'),
        'value' => '50',
        'control_type' => waHtmlControl::INPUT,
    ),
    'operation_limit' => array(
        'title' => _wp('Products per operation limit'),
        'description' => _wp('Maximum products per single run.'),
        'value' => '100',
        'control_type' => waHtmlControl::INPUT,
    ),
    'log_retention_days' => array(
        'title' => _wp('Keep log, days'),
        'description' => _wp('Old records will be cleaned automatically.'),
        'value' => '90',
        'control_type' => waHtmlControl::INPUT,
    ),
    'date_format' => array(
        'title' => _wp('Date format'),
        'description' => _wp('Choose how dates and times are shown in all plugin sections.'),
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
        'title' => _wp('Theme mode'),
        'description' => _wp('Auto uses the default backend theme.'),
        'value' => 'auto',
        'control_type' => waHtmlControl::SELECT,
        'options' => array(
            array('value' => 'auto', 'title' => _wp('Auto')),
            array('value' => 'light', 'title' => _wp('Light')),
            array('value' => 'dark', 'title' => _wp('Dark')),
        ),
    ),
    'interface_language' => array(
        'title' => _wp('Interface language'),
        'description' => _wp('Defaults to the current Webasyst locale until you choose a language.'),
        'value' => '',
        'control_type' => waHtmlControl::SELECT,
        'options' => array(
            array('value' => 'ru_RU', 'title' => _wp('Russian')),
            array('value' => 'en_US', 'title' => _wp('English')),
        ),
    ),
    'show_soon_operations' => array(
        'title' => _wp('Show "Soon" operations'),
        'description' => _wp('Controls future operations visibility in the library.'),
        'value' => '0',
        'control_type' => waHtmlControl::CHECKBOX,
    ),
);
