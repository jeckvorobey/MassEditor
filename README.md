# Mass Editor Product

## Назначение

`masseditorproduct` — backend-only плагин для Shop-Script, который развивается поэтапно как безопасный массовый редактор товаров.

Текущий статус:

- Этап 1: создан каркас плагина и backend-экран
- Этап 2: добавлена таблица логов и сервис записи операций
- Этап 3: добавлена базовая загрузка товаров, фильтр по названию и статусу, пагинация
- Этапы 4–6: добавлено применение массовых операций для цены, compare price, видимости и доступности через явное подтверждение
- Этапы 7–9: добавлены операции для описаний, тегов и URL, расширены фильтры и таблица товаров
- Этап 10: добавлена двуязычность интерфейса `ru_RU` / `en_US` с настройкой языка

## Структура

```text
wa-apps/shop/plugins/masseditorproduct/
├── lib/actions/   backend actions
├── lib/classes/   сервисы плагина, включая i18n
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
shop_masseditorproduct_log
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
shopMasseditorproductPluginLogService
```

### Список товаров

Текущая версия backend-экрана:

- читает товары из `shop_product`
- показывает ID, название, URL, описание, категории, теги, статус, доступность, цену, compare price, остаток и дату изменения
- поддерживает фильтр по названию или артикулу
- поддерживает фильтр по статусу (`all`, `published`, `hidden`, `unpublished`)
- поддерживает фильтр по категории и доступности
- сохраняет выбранные товары при переходе между страницами
- использует настраиваемый размер страницы

### Массовые операции

Поддерживаются операции:

- изменение цены
- изменение `compare price`
- изменение видимости товара
- изменение доступности товара
- изменение описания
- изменение тегов
- изменение URL товара

Для цены и `compare price` доступны режимы:

- установка фиксированного значения
- изменение на процент
- округление до 1, 10 или 100

Для описаний доступны режимы полной замены, добавления в начало и добавления в конец.
Для тегов доступны добавление, удаление и замена списка тегов.
Для URL доступны генерация из названия и шаблон с переменными `{name}`, `{id}`, `{current_url}`.

Поток операции:

1. пользователь отмечает товары
2. выбирает тип операции
3. заполняет параметры операции
4. проверяет выбранное действие в модальном окне подтверждения
5. подтверждает применение
6. сервер повторно валидирует входные данные
7. изменения применяются батчами
8. результат записывается в `shop_masseditorproduct_log`

### Локализация

Интерфейс поддерживает два языка:

- русский (`ru_RU`)
- английский (`en_US`)

Настройка `interface_language` хранится в стандартных настройках плагина и принимает значения:

- `auto` — язык выбирается по текущей локали Webasyst через `wa()->getLocale()`;
- `ru_RU` — принудительно русский интерфейс;
- `en_US` — принудительно английский интерфейс.

В режиме `auto` локали, начинающиеся с `ru`, открывают русский интерфейс. Все остальные локали открывают английский интерфейс. Это используется как безопасная локальная замена определению источника установки из маркетплейса: подтвержденного API Webasyst для чтения marketplace-источника установки в коде плагина сейчас не используется.

Переведены:

- основной backend-интерфейс;
- вкладки, фильтры, таблицы, настройки и модальное подтверждение;
- JS-уведомления и клиентская валидация;
- серверные ошибки и новые записи журнала;
- названия групп и операций.

## Ограничения текущего этапа

Пока не реализованы:

- изменение остатков с учетом всех режимов складского учета

## Ограничение по остаткам

Массовое изменение остатков пока не включено в MVP этого репозитория.

Причина: для безопасной поддержки всех режимов складского учета требуется дополнительная проверка в документации и/или локальном коде Shop-Script, чтобы не сломать сценарии с `shop_product_stocks`, несколькими складами и автоматической коррекцией агрегированных остатков.

## Проверка

Минимальная локальная проверка после PHP-изменений:

```bash
php -l wa-apps/shop/plugins/masseditorproduct/lib/actions/shopMasseditorproductPluginBackend.action.php
php -l wa-apps/shop/plugins/masseditorproduct/lib/classes/shopMasseditorproductPluginI18nService.class.php
php -l wa-apps/shop/plugins/masseditorproduct/lib/classes/shopMasseditorproductPluginProductSelectionService.class.php
php -l wa-apps/shop/plugins/masseditorproduct/lib/classes/shopMasseditorproductPluginLogService.class.php
php -l wa-apps/shop/plugins/masseditorproduct/lib/classes/shopMasseditorproductPluginMassOperationService.class.php
```

## Автотесты

В репозитории есть два независимых набора тестов:

- PHP: `tests/php/` через `PHPUnit PHAR`
- JS: `tests/js/` через встроенный `node:test`

Команды запуска из корня репозитория:

```bash
bash tests/run-js-tests.sh
bash tests/run-php-tests.sh
```

Перед первым PHP-прогоном нужно один раз скачать `phpunit.phar`:

```bash
curl -L https://phar.phpunit.de/phpunit-10.phar -o tests/phpunit.phar
```

PHP-тесты используют локальный bootstrap с заглушками Webasyst/Shop-Script и
проверяют сервисы плагина, helper-логику backend action и публичное меню
плагина. JS-тесты покрывают `masseditorproduct.js`: выбор товаров, localStorage,
валидацию, confirm modal и клиентские i18n-строки.

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
- MassEditorProduct после входа: `http://localhost:8080/webasyst/shop/?plugin=masseditorproduct`

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
- Добавить экспорт/фильтрацию журнала операций, если плагин будет использоваться на реальных каталогах.
- При появлении подтвержденного API Webasyst для определения marketplace-источника установки можно заменить текущий fallback по локали Webasyst на прямой источник.
