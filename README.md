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
