<?php

class shopMasseditorPlugin extends shopPlugin
{
    public function backendMenu()
    {
        $url = wa()->getAppUrl('shop') . '?plugin=masseditor&action=backend';
        return [
            'core_li' => '<li class="no-tab"><a href="' . $url . '">Mass Editor</a></li>',
        ];
    }
}
