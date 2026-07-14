## 1. Подготовка и TDD

- [x] 1.1 Сверить реализацию с документацией Webasyst по backend actions плагина, `waRequest` и безопасной работе через `waModel::query()`.
- [x] 1.2 Добавить PHP-тест нормализации `stock_id`: существующий склад сохраняется, неизвестный склад сбрасывается в `0`.
- [x] 1.3 Добавить PHP-тест `getPage()` для фильтра склада: возвращаются только товары со SKU/stock-записью выбранного склада без дублей.
- [x] 1.4 Добавить PHP-тест `getIdsByFilters()` для выбора всех товаров по фильтру склада.
- [x] 1.5 Добавить PHP-тест `getSearchSuggestions()` для применения `stock_id` вместе с остальными активными фильтрами.

## 2. Серверная реализация фильтра

- [x] 2.1 Расширить чтение GET-фильтров в `shopMasseditorPluginBackendAction` параметром `stock_id`.
- [x] 2.2 Расширить `shopMasseditorPluginProductSelectionService::normalizeFilters()` безопасной нормализацией `stock_id`.
- [x] 2.3 Добавить в `buildConditions()` условие склада через `EXISTS` по `shop_product_skus` и `shop_product_stocks` с placeholder `i:stock_id`.
- [x] 2.4 Обновить расчет `has_active_filters` и `can_select_filter`, чтобы выбранный склад считался активным фильтром.
- [x] 2.5 Убедиться, что фильтр склада применяется одинаково в `getPage()`, `getIdsByFilters()` и `getSearchSuggestions()`.

## 3. Backend UI и локализация

- [x] 3.1 Добавить select `Склад` в форму фильтров товаров, используя уже переданный `$stocks`.
- [x] 3.2 Обновить URL пагинации и переходов вкладок, чтобы они сохраняли `stock_id` вместе с `query/status/availability/category_id`.
- [x] 3.3 Обновить hidden payload выбора всех по фильтру, чтобы массовая операция получала текущий `stock_id`.
- [x] 3.4 Добавить i18n-ключи для label `Склад` и варианта `Все склады` в `ru_RU` и `en_US`.
- [x] 3.5 Если `masseditor.js` строит URL подсказок вручную, добавить `stock_id` в `buildSearchSuggestionsUrl()` и покрыть это JS-тестом.

## 4. Проверки

- [x] 4.1 Запустить `php -l` для всех измененных PHP-файлов.
- [x] 4.2 Запустить `bash tests/run-php-tests.sh`.
- [x] 4.3 Если менялся `masseditor.js`, запустить `node --check wa-apps/shop/plugins/masseditor/js/masseditor.js` и `bash tests/run-js-tests.sh`.
- [x] 4.4 Запустить `openspec validate --strict --all`.
