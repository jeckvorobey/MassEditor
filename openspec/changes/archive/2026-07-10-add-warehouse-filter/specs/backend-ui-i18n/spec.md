## MODIFIED Requirements

### Requirement: Backend UI вкладки
Backend-экран MUST оставаться единым экраном плагина с вкладками `products`, `log`, `settings` и URL `?plugin=masseditor`.

#### Scenario: Неизвестная вкладка
- **WHEN** request содержит неизвестный `view`
- **THEN** backend action MUST открыть вкладку `products`

#### Scenario: Сохранение состояния
- **WHEN** пользователь переходит между вкладками или страницами
- **THEN** ссылки MUST сохранять текущие фильтры, включая `query`, `status`, `availability`, `category_id`, `stock_id`, страницу товаров и страницу журнала там, где это применимо

### Requirement: Локализация интерфейса
Видимый backend-текст MUST идти через `shopMasseditorPluginI18nService`, `.po`/`.mo` каталоги и JS i18n dictionary, без новых хардкодных русских строк в JS fallback.

#### Scenario: Выбор языка
- **WHEN** настройка `interface_language` задана как `ru_RU` или `en_US`
- **THEN** backend UI и JS-тексты MUST использовать выбранный язык независимо от локали Webasyst

#### Scenario: Настройка языка не задана
- **WHEN** `interface_language` пустая
- **THEN** язык MUST определяться по `wa()->getLocale()`: английские локали открывают `en_US`, остальные `ru_RU`

#### Scenario: JS fallback
- **WHEN** `window.masseditorI18n` не содержит ключа
- **THEN** fallback-строка в `masseditor.js` MUST оставаться английской, а русские строки MUST приходить из i18n dictionary

#### Scenario: Фильтр склада
- **WHEN** backend UI показывает форму фильтра товаров
- **THEN** label `Склад` и вариант `Все склады` MUST приходить из i18n-слоя плагина и MUST иметь переводы `ru_RU` и `en_US`
- **AND** выбранный `stock_id` MUST восстанавливаться после submit, пагинации и возврата на вкладку товаров
