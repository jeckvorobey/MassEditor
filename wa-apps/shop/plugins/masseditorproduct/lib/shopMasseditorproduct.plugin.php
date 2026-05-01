<?php

class shopMasseditorproductPlugin extends shopPlugin
{
    public function backendMenu()
    {
        $url = wa()->getAppUrl('shop') . '?plugin=masseditorproduct';
        return [
            'core_li' => '<li class="no-tab"><a href="' . $url . '">Mass Editor Product</a></li>',
        ];
    }
}
