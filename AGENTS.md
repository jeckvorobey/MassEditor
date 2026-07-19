# Руководство по репозиторию

## Структура проекта и модулей

Это backend-only плагин Shop-Script `masseditor`. Код находится в `wa-apps/shop/plugins/masseditor/`:

- `lib/actions/` backend-контроллеры.
- `lib/classes/` сервисы массовых операций, выбора товаров, логов и i18n.
- `lib/models/` модели таблиц плагина и Shop-Script.
- `lib/config/` метаданные плагина, настройки, install/uninstall, схема БД и store support config.
- `templates/`, `js/`, `css/`, `img/`, `locale/` содержат backend UI, клиентское поведение, ассеты и переводы.

Тесты находятся в `tests/php/` и `tests/js/`, Docker-стенд - в `docker/`, материалы публикации - в `docs/`.

## Команды сборки, тестов и разработки

Если не указано иначе, запускайте команды из корня репозитория:

```bash
bash tests/run-js-tests.sh
```
Запускает JS-тесты на `node:test` для `wa-apps/shop/plugins/masseditor/js/masseditor.js`.

```bash
bash tests/run-php-tests.sh
```
Запускает PHPUnit 10 через `tests/phpunit.phar`. Если файла нет, скачайте:
`curl -L https://phar.phpunit.de/phpunit-10.phar -o tests/phpunit.phar`.

```bash
cd docker && docker compose up -d --build
```
Поднимает стенд Webasyst + Shop-Script: `http://localhost:8088/webasyst/shop/?plugin=masseditor`.

## Стиль кода и соглашения об именах

Сохраняйте PHP-структуру в стиле Webasyst: классы используют префикс `shopMasseditorPlugin...`, файлы называются через `.class.php`, `.model.php` или action-формат вроде `shopMasseditorPluginBackend.action.php`. В PHP используйте отступ 4 пробела. Контроллеры держите тонкими, повторно используемую логику выносите в `lib/classes/`. JavaScript в `masseditor.js` должен оставаться простым и без зависимостей. Видимый backend-текст локализуйте через `.po` и i18n-слой плагина, не хардкодьте строки в шаблонах или JS.

## Обязательные правила разработки

Для любой задачи сначала используйте подходящие project skills из `.agents/skills/` и доступные global skills, затем изучайте релевантную официальную документацию Webasyst/Shop-Script по теме изменения. Код пишите по TDD: сначала тест ожидаемого поведения, затем минимальная реализация и релевантные проверки. Соблюдайте паттерны проекта: тонкие actions, сервисы в `lib/classes/`, модели в `lib/models/`, локализация через i18n-слой. Не допускайте N+1 запросов к БД; загружайте связанные данные батчами.

Для подготовки версии, Webasyst Store-материалов, changelog, meta-update или релизного архива обязательно используйте project skill `webasyst-release`. Он должен анализировать фактический код, Git history/diff, тесты, конфигурацию, локали и предыдущие release-документы целевого продукта, заполнять единые project-owned шаблоны и не хранить внутри skill список функций MassEditor. Публичные claims допускаются только с evidence; Store description является кумулятивным, release note содержит только delta версии, а changelog сохраняет все опубликованные версии без пропусков.

## Документация Webasyst

Перед изменениями сверяйтесь с официальной документацией:

- [Основы разработки плагинов](https://developers.webasyst.ru/docs/cookbook/plugins/)
- [Файловая структура](https://developers.webasyst.ru/docs/cookbook/basics/file-structure/)
- [Настройки плагина](https://developers.webasyst.ru/docs/cookbook/plugins/plugin-settings/)
- [Класс waPlugin](https://developers.webasyst.ru/docs/cookbook/basics/classes/waPlugin/)
- [Shop-Script: хуки для плагинов](https://developers.webasyst.ru/docs/plugin/hooks/shop)

## Правила тестирования

PHP-тесты добавляйте в `tests/php/*Test.php`, JS-тесты - в `tests/js/*.test.js`. Покрывайте сервисы, helper-логику backend action, сохранение выбора, валидацию, модальное подтверждение и локализуемые тексты. После PHP-изменений запускайте `php -l` для изменённых файлов.

## Коммиты и Pull Request

В истории используются короткие русские commit subject, например `Выровнил локализацию backend и дефолты MassEditor`. Держите один коммит в рамках одного изменения. В PR описывайте пользовательский эффект, перечисляйте запущенные тесты, отмечайте Docker/ручную проверку при необходимости и прикладывайте скриншоты для изменений backend UI или store-материалов.

## Безопасность и конфигурация

Не расширяйте бизнес-логику массовых операций без явного scope. Сохраняйте подтверждение перед записью и серверную валидацию. SQL пишите через безопасные API Webasyst/waModel, плейсхолдеры, приведение типов и whitelist для полей/сортировки; не склеивайте пользовательский ввод в SQL. Уделяйте максимум внимания правам доступа, CSRF, валидации, экранированию вывода и безопасной записи логов. `docker/` предназначен только для разработки.
