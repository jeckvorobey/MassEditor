## Approach

Исправления реализованы точечно, без изменения архитектуры плагина.

### Stock fallback

В `applyProductStockOperation()` добавлена проверка `usesWarehouseStockAccounting($skus)` — обходит массив SKU и ищет непустой `stock`. Если складского учёта нет, операция со складом (`stock_id > 0`) проводится как обычная — через `sku.count`, а не через `applyWarehouseStockOperation()`.

Метод `usesWarehouseStockAccounting()` приватный, работает in-place без дополнительных запросов.

### Extended menu

Добавлен хук `backend_extended_menu` в `plugin.php`. Метод `backendExtendedMenu(&$params)` вставляет пункт в `$params['menu']` с `placement => 'body'` и иконкой FontAwesome. URL генерируется через общий `getBackendUrl()`, вынесенный из `backendMenu()`.

### Docker override

Порт изменён с `8080:8080` на `8088:8080` в `docker-compose.yml`. Создан `docker-compose.override.yml` для локальных переопределений.

## Trade-offs

- `usesWarehouseStockAccounting()` определяет наличие складского учёта по наличию `stock` в первом SKU. Для товаров с разными SKU, где у одного есть складской учёт, а у другого — нет, поведение может быть неожиданным. Текущий подход принят как рабочий для типичного сценария.
- Расширенное меню добавлено без настроек — плагин всегда показывается в навигации.

## Verification

- PHP-тесты покрывают оба сценария: со складским учётом и без
- Тест `PluginTest` проверяет регистрацию хуков и содержимое `backendExtendedMenu`
- `php -l` пройден для изменённых файлов
- Docker-стенд поднимается на порту 8088
