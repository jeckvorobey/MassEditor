## Context

MassEditor — backend-only плагин Shop-Script для массового редактирования товаров. Операция `features` позволяет массово устанавливать значения характеристик товаров. Текущая реализация фильтрует характеристики по типу: whitelist `{varchar, text, double, int}` в `isFeatureTypeEditable()` (Backend.action.php:269) и `featureValueTableSuffix()` (MassOperationService.class.php:1035). Характеристики с типами `boolean`, `dimension.*`, `color`, `select`, `radio`, `range` не отображаются и не редактируются.

В Shop-Script все значения характеристик хранятся в трёх таблицах:
- `shop_feature_values_varchar` — строковые значения
- `shop_feature_values_text` — текстовые значения
- `shop_feature_values_double` — числовые значения

Тип характеристики определяет: (1) таблицу хранения, (2) валидацию, (3) UI-виджет, (4) наличие предопределённых значений.

## Goals / Non-Goals

**Goals:**
- Показывать все типы характеристик Shop-Script в выпадающем списке MassEditor (кроме `multiple=1` и дочерних `parent_id > 0`)
- Автоматически подбирать UI-виджет в зависимости от типа характеристики
- Корректно валидировать и сохранять значения для каждого типа
- Для selectable-характеристик показывать доступные значения из БД

**Non-Goals:**
- Поддержка SKU-характеристиков (остаются отфильтрованными)
- Поддержка множественных значений (`multiple=1`)
- Кастомные виджеты (цветовой пикер, слайдер диапазона) — текстовое поле с подсказкой формата
- Загрузка значений selectable-характеристик через AJAX — предзагрузка в шаблоне

## Decisions

### 1. Маппинг типов на таблицы хранения

**Решение:** Единая конфигурация типа возвращает таблицу, тип валидации и UI-виджет.

```php
// MassOperationService::featureTypeConfig()
$configs = [
    'varchar'   => ['table' => 'varchar', 'validate' => 'string',   'ui' => 'text'],
    'text'      => ['table' => 'text',    'validate' => 'string',   'ui' => 'textarea'],
    'double'    => ['table' => 'double',  'validate' => 'numeric',  'ui' => 'number'],
    'int'       => ['table' => 'double',  'validate' => 'numeric',  'ui' => 'number'],
    'boolean'   => ['table' => 'varchar', 'validate' => 'boolean',  'ui' => 'select'],
    'color'     => ['table' => 'varchar', 'validate' => 'string',   'ui' => 'text'],
    'dimension' => ['table' => 'double',  'validate' => 'numeric',  'ui' => 'number'],
    'range'     => ['table' => 'double',  'validate' => 'numeric',  'ui' => 'number'],
    'select'    => ['table' => 'varchar', 'validate' => 'selectable', 'ui' => 'select'],
    'radio'     => ['table' => 'varchar', 'validate' => 'selectable', 'ui' => 'select'],
];
```

**Альтернатива:** Хардкод маппинга в каждом методе (`featureValueTableSuffix`, `normalizeFeatureValue`). Отклонено — дублирование логики.

**Обоснование:** Shop-Script хранит `boolean` как "1"/"0" в varchar-таблице, `dimension.*` как число в double-таблице, `color` как hex-строку в varchar-таблице. Это стандартная схема Shop-Script, подтверждённая структурой таблиц `shop_feature_values_*`.

### 2. UI-виджеты в шаблоне

**Решение:** В шаблоне `Backend.html` рендерить 4 виджета (текстовое поле, числовое поле, textarea, select), все скрыты по умолчанию. JS переключает видимость при выборе характеристики на основе `data-ui` атрибута на `<option>`.

Виджеты:
- `text` — `<input type="text">` (varchar, color)
- `number` — `<input type="number" step="any">` (double, int, dimension, range)
- `textarea` — `<textarea>` (text)
- `select` — `<select>` с предзагруженными значениями (boolean, select, radio)

**Альтернатива:** AJAX-загрузка значений при выборе характеристики. Отклонено — лишний запрос, значения selectable-характеристик редко превышают 20 штук.

### 3. Предзагрузка значений selectable-характеристик

**Решение:** В `decorateFeatures()` для каждой selectable-характеристики загружать доступные значения из соответствующей таблицы `shop_feature_values_*` и передавать в шаблон как JSON-объект `feature_values_map`.

```php
// Backend.action.php
$feature_values_map = [];
foreach ($features as $feature) {
    if (!empty($feature['selectable'])) {
        $suffix = $operation_service->featureValueTableSuffix($feature['type']);
        $values = $selection_service->getFeatureValues($feature['id'], $suffix);
        $feature_values_map[$feature['id']] = $values;
    }
}
```

### 4. Валидация по типу

**Решение:** `normalizeFeatureValue()` расширяется до полиморфной валидации:

| Тип | Валидация |
|-----|-----------|
| `varchar`, `text`, `color` | `trim()`, непустое значение |
| `double`, `int`, `dimension.*`, `range` | `str_replace(',', '.')`, `is_numeric()`, cast to `(float)` |
| `boolean` | Значение должно быть `"1"` или `"0"` |
| `select`, `radio` | Значение должно существовать в `shop_feature_values_*` для данной характеристики |

**Альтернатива:** Валидация только на клиенте. Отклонено — серверная валидация обязательна по требованиям безопасности.

### 5. Новые i18n-ключи

**Решение:** Добавить ключи в `ru_RU` и `en_US`:
- `feature_type_boolean_yes` / `feature_type_boolean_no` — для boolean select
- `feature_type_color_hint` — подсказка формата цвета
- `feature_type_dimension_hint` — подсказка единиц измерения
- `feature_type_range_hint` — подсказка формата диапазона

## Risks / Trade-offs

**[Risk]: Неизвестные типы характеристик в будущих версиях Shop-Script**
→ Mitigation: Fallback на varchar-таблицу и текстовое поле для неизвестных типов. Логирование предупреждения.

**[Risk]: Большие списки значений для selectable-характеристик (сотни значений)**
→ Mitigation: Для первой итерации — предзагрузка. Если станет проблемой — перейти на AJAX.

**[Risk]: Некорректный маппинг типа на таблицу хранения**
→ Mitigation: Проверить все типы Shop-Script через прямой запрос к `shop_feature` и `shop_feature_values_*` на тестовых данных. Добавить тесты для каждого типа.

**[Trade-off]: Текстовое поле вместо кастомного виджета для color/range**
→ Упрощение реализации. Пользователь вводит hex-цвет вручную. Кастомный виджет можно добавить в будущем релизе.
