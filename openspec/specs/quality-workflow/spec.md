## Purpose

Зафиксировать рабочий процесс разработки MassEditor: OpenSpec на русском, TDD, сверка с документацией Webasyst/Shop-Script, проверки и ограничения безопасности.

## Requirements

### Requirement: OpenSpec на русском
Все OpenSpec proposals, specs, designs, tasks и отчеты по изменениям MUST вестись на русском языке.

#### Scenario: Новое изменение
- **WHEN** создается новый OpenSpec change
- **THEN** proposal MUST описывать пользовательский эффект, scope, non-goals, затронутые capability specs и ссылки на релевантную документацию Webasyst/Shop-Script

### Requirement: Сверка с документацией
Перед изменениями Webasyst/Shop-Script механизмов MUST выполняться сверка с официальной документацией или локально подтвержденным кодом проекта.

#### Scenario: Новый hook, action, settings или DB behavior
- **WHEN** изменение использует hook, backend route/action, settings.php, db.php, install/uninstall, waPlugin/waModel/waRequest API или Shop-Script model API
- **THEN** решение MUST быть подтверждено официальной документацией Webasyst/Shop-Script или существующим локальным кодом; неподтвержденные детали MUST быть помечены как `требуется проверка в документации`

### Requirement: TDD перед реализацией
Кодовые изменения MUST начинаться с теста ожидаемого поведения, затем выполняется минимальная реализация и релевантные проверки.

#### Scenario: PHP-изменение
- **WHEN** меняется PHP-сервис, action, модель, настройки или config
- **THEN** сначала MUST быть добавлен или обновлен тест в `tests/php/*Test.php`, затем реализация, затем `php -l` для измененных PHP-файлов и `bash tests/run-php-tests.sh`

#### Scenario: JS-изменение
- **WHEN** меняется `js/masseditor.js` или frontend-поведение backend UI
- **THEN** сначала MUST быть добавлен или обновлен тест в `tests/js/*.test.js`, затем реализация, затем `node --check wa-apps/shop/plugins/masseditor/js/masseditor.js` и `bash tests/run-js-tests.sh`

### Requirement: Безопасность backend-операций
Mutating paths MUST проверять права, CSRF, входные данные, whitelist значений и не раскрывать технические ошибки пользователю.

#### Scenario: Ошибка выполнения
- **WHEN** непредвиденное исключение возникает в backend action
- **THEN** UI MUST показать локализованную generic error, а технические детали MUST быть записаны в `shop/plugins/masseditor.log`

#### Scenario: SQL и связанные данные
- **WHEN** код строит SQL по пользовательским фильтрам или сортировкам
- **THEN** SQL MUST использовать параметры/плейсхолдеры, приведение типов и whitelist, а связанные данные MUST загружаться батчами без N+1

### Requirement: Проверки перед завершением
Перед завершением задачи MUST быть выполнены проверки, соответствующие затронутым файлам, и `openspec validate --strict --all`.

#### Scenario: Только документация/OpenSpec
- **WHEN** изменение затрагивает только документацию или OpenSpec
- **THEN** MUST быть выполнена OpenSpec-валидация, а тесты кода можно не запускать, если runtime-код не менялся

