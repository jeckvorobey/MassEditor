<?php

class shopMasseditorPlugin extends shopPlugin
{
    public function getName()
    {
        return function_exists('_wp') ? _wp('Mass Editor') : 'Mass Editor';
    }

    public function getDescription()
    {
        $description = 'Backend-only plugin for safe bulk product editing.';

        return function_exists('_wp') ? _wp($description) : $description;
    }

    public function backendMenu()
    {
        $url = wa()->getAppUrl('shop') . '?plugin=masseditor';
        return [
            'core_li' => '<li class="no-tab"><a href="' . $url . '">' . htmlspecialchars($this->getName(), ENT_QUOTES, 'UTF-8') . '</a></li>',
        ];
    }
}
