## ADDED Requirements

### REQ-STOCK-TYPE-FILTER-1: Фильтр по типу учёта остатков

При массовой операции «Остатки» без выбора склада (`stock_id = 0`) пользователь может выбрать фильтр по типу учёта: «Все товары», «Без складского учёта», «Со складским учётом».

**Acceptance criteria:**
- Фильтр отображается только при `stock_id = 0`
- Значение по умолчанию: «Все товары» (backward compatible)
- Выбор сохраняется в payload операции как `stock_type_filter`

### REQ-STOCK-TYPE-FILTER-2: Применение фильтра на backend

Перед применением операции «Остатки» система фильтрует товары по `stock_type_filter`.

**Acceptance criteria:**
- `all` — применяется ко всем товарам (текущее поведение)
- `without_warehouse` — только к товарам, у которых `usesWarehouseStockAccounting() === false`
- `with_warehouse` — только к товарам, у которых `usesWarehouseStockAccounting() === true`
- Пропущенные товары записываются в лог операции с указанием причины
