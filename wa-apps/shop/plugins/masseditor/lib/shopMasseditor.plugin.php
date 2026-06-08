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
        $url = htmlspecialchars($this->getBackendUrl(), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($this->getName(), ENT_QUOTES, 'UTF-8');

        return [
            'core_li' => '<li class="no-tab"><a href="' . $url . '">' . $name . '</a></li>',
        ];
    }

    public function backendExtendedMenu(&$params)
    {
        $params['menu'][$this->getId() . '_item'] = [
            'name' => $this->getName(),
            'icon' => '<i class="fas fa-edit"></i>',
            'url' => $this->getBackendUrl(),
            'placement' => 'body',
        ];
    }

    private function getBackendUrl()
    {
        return wa()->getAppUrl('shop') . '?plugin=' . $this->getId();
    }
}
