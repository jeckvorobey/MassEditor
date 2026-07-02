## Approach

### Research Results

**Как Shop-Script обрабатывает `stock_id = 0`:**
- При `stock_id = 0` MassEditor обновляет `sku.count` напрямую через `shopProduct->save()`.
- Для товаров без складского учёта это корректно — `count` в `shop_product_skus` является единственным источником данных.
- Для товаров со складским учётом обновление `sku.count` напрямую вызывает рассинхронизацию с `shop_product_stocks`. Shop-Script ожидает, что остатки хранятся в `shop_product_stocks`, а `sku.count` — агрегированное значение.

**`usesWarehouseStockAccounting()` поведение:**
- Метод проверяет, есть ли у SKU данные `stock` (записи из `shop_product_stocks`).
- Если хотя бы один SKU имеет `stock` — товар считается имеющим складской учёт.
- Для товаров без складского учёта `stock` массив пуст или отсутствует.

**Edge cases:**
- Товары со смешанными SKU (один со складским учётом, другой без) — `usesWarehouseStockAccounting()` определяет по первому SKU с данными.
- `NULL` в `shop_product_stocks.count` означает бесконечный остаток — не путать с 0.

### Реализация

**Фильтр в UI**: при `stock_id = 0` показывается select с вариантами:
- Все товары (по умолчанию, backward compatible)
- Только без складского учёта
- Только со складским учётом

Select скрыт, когда выбран конкретный склад (`stock_id > 0`).

**Backend-логика**:
1. `normalizeRequest()` принимает и валидирует `stock_type_filter` (whitelist: `all`, `without_warehouse`, `with_warehouse`).
2. `assertStockRequestMatchesProductAccounting()` пропускает проверку при `stock_type_filter !== 'all'`, т.к. фильтр сам управляет отбором.
3. `filterProductsByStockType()` — новый метод, фильтрует products array по `usesWarehouseStockAccounting()`.
4. Пропущенные товары считаются и возвращаются в `result['skipped']`.
5. Если после фильтрации 0 товаров — операция завершается успешно без транзакции.

**Результат**: `formatResultMessage()` добавляет информацию о пропущенных товарах к сообщению.

## Verification

- PHP-тесты покрывают 3 сценария фильтрации: without_warehouse, with_warehouse, all
- Существующий тест `testApplyStockOperationWithoutWarehouseRejectsProductsWithWarehouseStocks` по-прежнему проходит (backward compatible при `stock_type_filter=all`)
- `php -l` пройден для изменённых файлов
- JS-тесты проходят (20/20)
