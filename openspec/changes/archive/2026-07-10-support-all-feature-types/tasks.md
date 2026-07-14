## 1. TDD: Тесты для поддержки всех типов характеристик

- [x] 1.1 Добавить тест `testApplyFeatureOperationSupportsBooleanType` — boolean "1"/"0" сохраняется в varchar-таблицу, невалидные значения отклоняются
- [x] 1.2 Добавить тест `testApplyFeatureOperationSupportsDimensionType` — dimension.* числовое значение сохраняется в double-таблицу
- [x] 1.3 Добавить тест `testApplyFeatureOperationSupportsColorType` — color строковое значение сохраняется в varchar-таблицу
- [x] 1.4 Добавить тест `testApplyFeatureOperationSupportsSelectType` — select значение из списка сохраняется в varchar-таблицу
- [x] 1.5 Добавить тест `testApplyFeatureOperationSupportsRadioType` — radio значение из списка сохраняется в varchar-таблицу
- [x] 1.6 Добавить тест `testApplyFeatureOperationSupportsRangeType` — range числовое значение сохраняется в double-таблицу
- [x] 1.7 Добавить тест `testApplyFeatureOperationClearWorksForAllTypes` — clear-режим удаляет запись из shop_product_features для любого типа
- [x] 1.8 Добавить тест `testApplyFeatureOperationRejectsUnknownTypeWithFallback` — неизвестный тип использует fallback varchar

## 2. Backend: MassOperationService — типо-конфигурация и маппинг

- [x] 2.1 Добавить метод `featureTypeConfig($type)` с полным маппингом типов на table/validate/ui
- [x] 2.2 Заменить `featureValueTableSuffix()` на делегирование к `featureTypeConfig()`
- [x] 2.3 Расширить `normalizeFeatureValue()` — добавить валидацию для boolean, dimension, color, select, radio, range
- [x] 2.4 Обновить `resolveFeature()` — убрать фильтр по типу, оставить только reject для `multiple=1`
- [x] 2.5 Добавить метод `getFeatureValues($feature_id, $table_suffix)` для загрузки доступных значений selectable-характеристик
- [x] 2.6 Запустить тесты, убедиться что все 8 новых тестов проходят

## 3. Backend: Backend.action — убрать фильтр типов

- [x] 3.1 Удалить метод `isFeatureTypeEditable()` из Backend.action.php
- [x] 3.2 Упростить `decorateFeatures()` — фильтровать только `multiple=1`, не фильтровать по типу
- [x] 3.3 Добавить предзагрузку значений selectable-характеристик в `$feature_values_map`
- [x] 3.4 Передать `feature_values_map` и конфигурацию типов в шаблон
- [x] 3.5 Запустить `php -l` для проверки синтаксиса изменённых файлов

## 4. Backend: Новые методы в ProductSelectionService

- [x] 4.1 Добавить метод `getFeatureValues($feature_id, $table_suffix)` — SELECT value FROM shop_feature_values_{suffix} WHERE feature_id = :id ORDER BY value
- [x] 4.2 Параметризовать SQL, использовать whitelist для суффикса таблицы
- [x] 4.3 Добавить тест `testGetFeatureValuesReturnsSelectableValues`

## 5. Template: Типо-зависимые виджеты ввода

- [x] 5.1 Добавить `data-ui` и `data-selectable` атрибуты на `<option>` в select характеристик
- [x] 5.2 Добавить скрытые виджеты: text input, number input, textarea, select (boolean + selectable)
- [x] 5.3 Внедрить JSON `feature_values_map` в `<script>` тег для JS
- [x] 5.4 Проверить экранирование вывода всех новых данных в шаблоне

## 6. JS: Динамическое переключение виджетов

- [x] 6.1 Добавить обработчик события `change` на `#masseditor-feature-id`
- [x] 6.2 Реализовать функцию `showFeatureWidget(uiType, featureId)` — скрыть все виджеты, показать нужный
- [x] 6.3 Для selectable-характеристик заполнить `<select>` значениями из `feature_values_map`
- [x] 6.4 Для boolean заполнить select вариантами «Да»/«Нет» из i18n
- [x] 6.5 Инициализировать виджет при загрузке страницы (если feature_id уже выбран)
- [x] 6.6 Добавить тесты в `tests/js/masseditor.test.js` для переключения виджетов

## 7. i18n: Новые ключи локализации

- [x] 7.1 Добавить ключи в `locale/ru_RU/LC_MESSAGES/`: feature_type_boolean_yes, feature_type_boolean_no, feature_type_color_hint, feature_type_dimension_hint, feature_type_range_hint
- [x] 7.2 Добавить ключи в `locale/en_US/LC_MESSAGES/`: аналогичные
- [x] 7.3 Проверить что все ключи доступны через `shopMasseditorPluginI18nService::t()`

## 8. Финальная верификация

- [x] 8.1 Запустить `bash tests/run-php-tests.sh` — все тесты проходят
- [x] 8.2 Запустить `bash tests/run-js-tests.sh` — все тесты проходят
- [x] 8.3 Запустить `php -l` для всех изменённых PHP-файлов
- [ ] 8.4 Проверить в Docker-стенде: характеристики всех типов отображаются, значения сохраняются корректно
