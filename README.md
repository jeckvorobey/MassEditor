# Mass Editor

## Назначение

`masseditor` — backend-only плагин для Shop-Script, который развивается поэтапно как безопасный массовый редактор товаров.

Текущий статус:

- Этап 1: создан каркас плагина и backend-экран
- Этап 2: добавлена таблица логов и сервис записи операций
- Этап 3: добавлена базовая загрузка товаров, фильтр по названию и статусу, пагинация
- Этапы 4–6: добавлены предпросмотр и применение массовых операций для цены, compare price, видимости и доступности

## Структура

```text
wa-apps/shop/plugins/masseditor/
├── lib/actions/   backend actions
├── lib/classes/   сервисы плагина
├── lib/config/    конфигурация и схема БД
├── lib/models/    модели таблиц плагина и подтвержденных таблиц Shop-Script
├── templates/     backend-шаблоны
├── js/            клиентский UX-код
└── css/           backend-стили
```

## Что уже реализовано

### Логи операций

Собственная таблица:

```text
shop_masseditor_log
```

Поля:

- `id`
- `user_id`
- `action_type`
- `entity_count`
- `description`
- `created_at`

Запись лога выполняет сервис:

```php
shopMasseditorPluginLogService
```

### Список товаров

Текущая версия backend-экрана:

- читает товары из `shop_product`
- показывает ID, название, статус, цену, compare price, остаток, дату изменения
- поддерживает фильтр по названию
- поддерживает фильтр по статусу (`all`, `published`, `hidden`, `unpublished`)
- использует фиксированный размер страницы `50`

### Массовые операции

Поддерживаются операции:

- изменение цены
- изменение `compare price`
- изменение видимости товара
- изменение доступности товара

Для цены и `compare price` доступны режимы:

- установка фиксированного значения
- изменение на процент

Поток операции:

1. пользователь отмечает товары
2. выбирает тип операции
3. получает серверный предпросмотр без записи в БД
4. подтверждает применение
5. сервер повторно валидирует входные данные
6. изменения применяются батчами
7. результат записывается в `shop_masseditor_log`

## Ограничения текущего этапа

Пока не реализованы:

- изменение остатков с учетом всех режимов складского учета

## Ограничение по остаткам

Массовое изменение остатков пока не включено в MVP этого репозитория.

Причина: для безопасной поддержки всех режимов складского учета требуется дополнительная проверка в документации и/или локальном коде Shop-Script, чтобы не сломать сценарии с `shop_product_stocks`, несколькими складами и автоматической коррекцией агрегированных остатков.

## Проверка

Минимальная локальная проверка после PHP-изменений:

```bash
php -l wa-apps/shop/plugins/masseditor/lib/actions/shopMasseditorPluginBackend.action.php
php -l wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginProductSelectionService.class.php
php -l wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginLogService.class.php
php -l wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginMassOperationService.class.php
```

## Локальная проверка в Docker

Для ручной проверки нужен отдельный стенд Webasyst + Shop-Script. В репозитории
есть dev-only compose-конфигурация:

```bash
cd docker
docker compose up -d --build
```

После запуска:

- Webasyst: `http://localhost:8080`
- backend: `http://localhost:8080/webasyst/`
- MassEditor после входа: `http://localhost:8080/webasyst/shop/?plugin=masseditor&action=backend`

Подробная инструкция и проверочные команды лежат в [docker/README.md](docker/README.md).

Важно: текущий Docker-стенд предназначен для локальной разработки. Он использует
PHP built-in server, а не полноценную связку Nginx + PHP-FPM. Это допустимо для
быстрой ручной проверки плагина, но не является production-конфигурацией.

## Документационные опоры

Текущая структура плагина сверена с официальной документацией Webasyst:

- структура плагина: `wa-apps/[app_id]/plugins/[plugin_id]/`;
- базовая конфигурация: `lib/config/plugin.php`;
- схема собственных таблиц: `lib/config/db.php`;
- регистрация плагина в `wa-config/apps/[app_id]/plugins.php`;
- хук `backend_menu` для пункта меню в backend Shop-Script.

Полезные источники:

- https://developers.webasyst.ru/docs/cookbook/plugins/
- https://developers.webasyst.ru/tutorials/shop-plugin-tutorial/
- https://developers.webasyst.ru/docs/plugin/hooks/shop
- https://developers.webasyst.ru/docs/store/product-update/

## Что улучшить дальше

Приоритетные улучшения:

- Добавить автоматические проверки сервисов плагина на тестовой БД или через минимальный Webasyst bootstrap.
- Закрепить версии Webasyst Framework и Shop-Script в Docker, чтобы стенд не менялся неожиданно после rebuild volume.
- Решить судьбу `docker/nginx.conf`: удалить как неиспользуемый файл или добавить отдельный Nginx + PHP-FPM профиль для более реалистичного стенда.
- Добавить метаобновления при будущих изменениях схемы БД: `lib/config/db.php` работает для новых установок, но для обновлений уже установленного плагина нужны отдельные update-скрипты.
- Улучшить UX массовых операций: дизейблить нерелевантные поля в зависимости от выбранной операции и показывать более явные предупреждения перед применением.
- Добавить экспорт/фильтрацию журнала операций, если плагин будет использоваться на реальных каталогах.
