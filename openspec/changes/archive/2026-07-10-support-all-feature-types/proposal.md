## Why

Плагин MassEditor показывает в выпадающем списке характеристики только четырёх базовых типов: `varchar`, `text`, `double`, `int`. У пользователей Shop-Script характеристики товаров часто имеют другие типы: `boolean` (да/нет, например Wi-Fi), `dimension.*` (размерности — ширина, высота, объём в единицах), `color`, `select`, `radio`, `range`. Эти характеристики не отображаются в MassEditor, и пользователи не могут массово их редактировать. Это ограничивает полезность плагина для магазинов с разнообразным каталогом.

## What Changes

- Убрать жёсткий whitelist типов из `isFeatureTypeEditable()` в backend action и `featureValueTableSuffix()` в MassOperationService.
- Все характеристики (кроме `multiple=1` и дочерних `parent_id > 0`) будут отображаться в выпадающем списке.
- Для каждого типа характеристики UI будет показывать подходящий элемент ввода:
  - `varchar`, `text` — текстовое поле (как сейчас)
  - `double`, `int`, `dimension.*`, `range` — числовое поле
  - `boolean` — чекбокс или select «Да/Нет»
  - `color` — текстовое поле с подсказкой формата (hex)
  - `select`, `radio` — выпадающий список доступных значений из `shop_feature_values_*`
- Слой хранения будет маппить каждый тип на правильную таблицу значений (`shop_feature_values_varchar`, `shop_feature_values_text`, `shop_feature_values_double`).
- Валидация значений будет учитывать тип характеристики (числовая для double/int/dimension, паттерн для color и т.д.).

## Capabilities

### New Capabilities

_(нет новых capability)_

### Modified Capabilities

- `mass-operations`: расширение поддерживаемых типов характеристик с `{varchar, text, double, int}` до всех типов Shop-Script (boolean, dimension.*, color, select, radio, range и др.). Изменения в требованиях: сценарий «Базовые характеристики» должен допускать все типы, кроме `multiple=1`.

## Impact

- `lib/actions/shopMasseditorPluginBackend.action.php` — методы `decorateFeatures()`, `isFeatureTypeEditable()`; новый метод для подготовки данных характеристик с типом и доступными значениями.
- `lib/classes/shopMasseditorPluginMassOperationService.class.php` — методы `featureValueTableSuffix()`, `normalizeFeatureValue()`, `resolveFeature()`, `resolveOrCreateFeatureValueId()`, `findFeatureValueId()`.
- `templates/actions/backend/Backend.html` — условный рендеринг элемента ввода в зависимости от типа характеристики.
- `js/masseditor.js` — динамическое переключение типа поля ввода при выборе характеристики.
- `locale/ru_RU/LC_MESSAGES/` и `locale/en_US/LC_MESSAGES/` — новые i18n-ключи для типов и подсказок.
- `tests/php/MassOperationServiceTest.php` — новые тесты для boolean, dimension, color, select, radio, range.
- `tests/js/masseditor.test.js` — тесты динамического переключения UI.

### Документация Webasyst для сверки

- [Shop-Script: хуки для плагинов](https://developers.webasyst.ru/docs/plugin/hooks/shop) — проверить, нет ли хуков для кастомизации типов характеристик.
- [Файловая структура](https://developers.webasyst.ru/docs/cookbook/basics/file-structure/) — убедиться в расположении шаблонов и JS.
