<?php

class shopMasseditorPlugin extends shopPlugin
{
    public function getName()
    {
        return shopMasseditorPluginI18nService::getPluginName(
            shopMasseditorPluginI18nService::resolveLanguage($this->getSettings('interface_language'))
        );
    }

    public function getDescription()
    {
        return shopMasseditorPluginI18nService::getPluginDescription(
            shopMasseditorPluginI18nService::resolveLanguage($this->getSettings('interface_language'))
        );
    }

    public function backendMenu()
    {
        $url = wa()->getAppUrl('shop') . '?plugin=masseditor';
        return [
            'core_li' => '<li class="no-tab"><a href="' . $url . '">' . htmlspecialchars($this->getName(), ENT_QUOTES, 'UTF-8') . '</a></li>',
        ];
    }
}
