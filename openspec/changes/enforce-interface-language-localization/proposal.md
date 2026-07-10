## Why

При русской настройке интерфейса уведомление о необходимости выбрать конкретный склад показывается на английском языке. Это нарушает единый языковой опыт backend UI и показывает, что для части видимых строк не гарантирована полнота словаря `ru_RU`.

Изменение нужно сейчас, чтобы исправить видимый дефект и закрепить в OpenSpec постоянное правило: каждый пользовательский текст, включая уведомления и сообщения валидации, должен соответствовать выбранному языку интерфейса.

Перед реализацией необходимо свериться с официальной документацией Webasyst о [локализации плагинов](https://developers.webasyst.ru/docs/features/localization/) и [основах разработки плагинов](https://developers.webasyst.ru/docs/cookbook/plugins/): для плагинов используются собственные gettext-каталоги и вызовы `_wp()`/i18n-слой.

## What Changes

- Исправить экспорт в JS-словарь сообщения «For products with warehouse stock accounting, select a specific warehouse.» и обеспечить, что toast-заголовок, текст, доступная подпись кнопки закрытия и аналогичные JS-уведомления используют словарь текущего языка.
- Добавить тесты, проверяющие русские и английские тексты для уведомления о выборе склада и отсутствие английского fallback при наличии русского перевода.
- Уточнить capability-spec `backend-ui-i18n`: все видимые backend-тексты, в том числе toast-уведомления, ошибки валидации и aria-label, должны быть полностью на `ru_RU` или `en_US` согласно `interface_language`.
- Зафиксировать это обязательное требование в проектной документации OpenSpec (`openspec/config.yaml` и `OPENSPEC_ROADMAP.md`), чтобы оно учитывалось при подготовке и реализации всех последующих изменений.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `backend-ui-i18n`: уточняется языковой контракт видимого backend UI, JS-уведомлений и доступных подписей.

## Impact

- Код: `wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginI18nService.class.php`, `wa-apps/shop/plugins/masseditor/js/masseditor.js` (если потребуется устранить иной путь fallback).
- Локализация: `locale/ru_RU/LC_MESSAGES/shop_masseditor.po`, `locale/en_US/LC_MESSAGES/shop_masseditor.po` и соответствующие `.mo` после сборки.
- Тесты: `tests/js/masseditor.test.js`, при необходимости PHP-тест i18n-сервиса.
- Документация процесса: `openspec/config.yaml`, `OPENSPEC_ROADMAP.md`, delta-spec `backend-ui-i18n`.
- Внешние API, схема БД, права доступа, CSRF и массовая бизнес-логика не меняются.
