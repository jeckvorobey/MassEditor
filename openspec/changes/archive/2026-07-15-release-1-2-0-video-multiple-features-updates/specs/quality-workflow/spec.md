## MODIFIED Requirements

### Requirement: Сверка с документацией
Перед изменениями механизмов Webasyst/Shop-Script MUST выполняться сверка с официальной документацией Webasyst/Shop-Script как единственным источником истины для архитектурного решения.

#### Scenario: Новый hook, action, settings или DB behavior
- **WHEN** изменение использует hook, backend route/action, settings.php, db.php, install/uninstall, waPlugin/waModel/waRequest API или Shop-Script model API
- **THEN** решение MUST быть подтверждено официальной документацией Webasyst/Shop-Script; неподтвержденная деталь MUST быть исключена из реализации либо помечена как `требуется проверка в документации`

## ADDED Requirements

### Requirement: Проверка релиза 1.2.0
Релиз MUST пройти TDD-проверки новых операций, повторного meta-update и реальный Webasyst bootstrap до сборки Store-архива.

#### Scenario: PHP TDD
- **WHEN** реализуются видео, множественные характеристики или meta-update
- **THEN** сначала MUST появиться падающие Given/When/Then тесты прав, нормализации, точечной записи, rollback и идемпотентности, затем минимальная реализация

#### Scenario: JS TDD
- **WHEN** меняются форма операции, multi-select или confirm modal
- **THEN** сначала MUST появиться падающие JS-тесты переключения, disabled-полей, валидации и локализации, затем реализация

#### Scenario: Реальный bootstrap
- **WHEN** fake-model тесты завершены успешно
- **THEN** сценарии записи видео и `replace/add/remove/clear` MUST быть проверены через реальный Webasyst/Shop-Script bootstrap с подтверждением сохранности SKU и других характеристик

#### Scenario: Финальный gate
- **WHEN** код релиза готов к упаковке
- **THEN** MUST успешно завершиться `php -l`, PHP/JS тесты, `openspec validate --strict --all`, поиск отменённого ID и сравнение Store-архива с production-деревом плагина
