## Why

В ветке `codex/masseditor-stock-fallback` накоплены исправления и улучшения, которые не задокументированы как единый change. Нужно зафиксировать выполненную работу перед мержем в main и выпуском версии.

## What Changes

- **Исправлен fallback остатков для товаров без складского учёта**: при выборе склада и отсутствии складских остатков у товара, операция корректно обновляет `count` в SKU напрямую, а не падает с ошибкой.
- **Добавлен пункт «Массовый редактор» в расширенное меню Shop-Script** (`backend_extended_menu`): плагин теперь отображается в навигации бэкенда через хук `backendExtendedMenu` с иконкой `fa-edit`.
- **Докопирован Docker override для порта 8088**: локальный стенд не конфликтует с другими сервисами на 8080.
- **Обновлены релизные и store-материалы**: русификация названия («Массовый редактор»), добавлен английский update note, исправлены ссылки и описания.

## Capabilities

### New Capabilities
- `stock-fallback`: Корректная обработка массовой операции «Остатки» для товаров без складского учёта — fallback на `sku.count` при отсутствии записей в `shop_product_stocks`.
- `extended-menu`: Отображение плагина в расширенном меню бэкенда Shop-Script через `backend_extended_menu` хук.

### Modified Capabilities
- (нет существующих specs — это первые specs в проекте)

## Impact

- `shopMasseditorPluginMassOperationService.class.php` — логика выбора складского vs. обычного остатка
- `shopMasseditor.plugin.php` — новый метод `backendExtendedMenu`, рефакторинг `backendMenu`
- `plugin.php` — регистрация хука `backend_extended_menu`
- Тесты: `MassOperationServiceTest.php`, `PluginTest.php` — новые и обновлённые тест-кейсы
- Docker: `docker-compose.yml` + `docker-compose.override.yml` — порт 8088
- Документация: `release-1.1.0.md`, `store-description.md`, `store-publication.md`, `old.md`
