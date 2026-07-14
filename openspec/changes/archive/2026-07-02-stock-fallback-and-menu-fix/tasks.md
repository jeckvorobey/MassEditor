## Tasks

- [x] Добавить `usesWarehouseStockAccounting()` в `MassOperationService`
- [x] Изменить условие выбора складской операции в `applyProductStockOperation()`
- [x] Обновить тест `testApplyStockOperationRejectsDecreaseFromMissingWarehouseStockBelowZero` → fallback-сценарий
- [x] Добавить тест `testApplyStockOperationWithWarehouseSelectionFallsBackToSkuCountForProductsWithoutWarehouseAccounting`
- [x] Добавить хук `backend_extended_menu` в `plugin.php`
- [x] Реализовать `backendExtendedMenu()` и `getBackendUrl()` в `shopMasseditor.plugin.php`
- [x] Добавить тест `testPluginConfigRegistersBackendMenuHooks`
- [x] Добавить тест для `backendExtendedMenu` в `PluginTest`
- [x] Изменить порт Docker на 8088, создать `docker-compose.override.yml`
- [x] Обновить документацию: русификация названия, английский update note
- [x] Запустить все тесты — пройдены
- [x] Сделать `php -l` для изменённых PHP-файлов
