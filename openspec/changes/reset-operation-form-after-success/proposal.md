## Why

После успешного применения массовой операции backend повторно показывает значения из отправленной формы. Это создаёт риск случайно применить те же параметры повторно вместо начала следующей операции с исходными настройками.

## What Changes

- После успешного применения любой массовой операции action будет передавать в шаблон исходное состояние формы.
- При ошибке валидации или выполнения введённые значения останутся в форме для исправления и повторной отправки.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `mass-operations`: после успешной массовой операции UI возвращается к дефолтным параметрам формы.

## Impact

- `wa-apps/shop/plugins/masseditor/lib/actions/shopMasseditorPluginBackend.action.php`
- `tests/php/BackendActionTest.php`
- OpenSpec-спецификация массовых операций.

Изменение не добавляет новых пользовательских текстов, API, зависимостей или записей в БД. Перед реализацией сверены [документация плагинов Webasyst](https://developers.webasyst.ru/docs/cookbook/plugins/) и локальный action плагина; используются навыки `webasyst-development`, `openspec` и `php-tdd-developer`.
