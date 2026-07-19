## ADDED Requirements

### Requirement: Доказательный release workflow
`webasyst-release` MUST анализировать целевой Webasyst-продукт, Git history/diff, конфигурацию версии, runtime-код, тесты, локали, meta-updates и существующие release-документы и MUST формировать публичные материалы только из подтверждённых фактов конкретного продукта. Skill MUST NOT содержать встроенный каталог функций MassEditor или другого целевого продукта.

#### Scenario: Подготовка нового релиза
- **WHEN** пользователь просит подготовить новую версию Webasyst-продукта
- **THEN** агент ДОЛЖЕН определить предыдущую опубликованную версию и собрать evidence для текущих возможностей и delta новой версии
- **AND** неподтверждённые возможности ДОЛЖНЫ остаться вне публичных документов и попасть в blockers/unconfirmed list

#### Scenario: Повторный запуск
- **WHEN** workflow повторно запускается для той же draft-версии и того же набора фактов
- **THEN** проектный release dataset и документы ДОЛЖНЫ обновиться идемпотентно без дублирования версии или смысловых пунктов

### Requirement: Универсальные release templates и validator
`webasyst-release` MUST поставлять нейтральные templates для product catalog, release manifest, Store description, release note, changelog, publication checklist и release report, а также JSON Schema, dependency-free semantic validator и tests release-инвариантов.

#### Scenario: Единообразное заполнение документов
- **WHEN** evidence конкретного продукта собрано
- **THEN** агент ДОЛЖЕН заполнить проектные данные и документы по bundled templates, сохраняя distinction между cumulative description, version-only release note и полным changelog

#### Scenario: Проверка готовности
- **WHEN** версия, локали, evidence, changelog, meta-update или archive manifest расходятся
- **THEN** validator ДОЛЖЕН завершиться ошибкой, перечислить точные blockers и MUST NOT выдать статус `READY`

## MODIFIED Requirements

### Requirement: Выбор профильных навыков по этапу и scope
Агент MUST выбирать skills по фактическому этапу: глобальный `openspec` для change workflows, `webasyst-development` для архитектуры и реализации MassEditor, `webasyst-release` для публикации, а универсальные PHP/TDD/security/review skills — только когда они релевантны scope. Агент MUST использовать только реально доступные skills и MUST NOT зависеть от отсутствующих project-level workflows.

#### Scenario: Выполняется docs-only изменение skills
- **WHEN** change не затрагивает runtime PHP/JS
- **THEN** агент применяет OpenSpec и проверки skills
- **AND** не создаёт искусственные runtime-тесты
- **AND** всё равно запускает существующие проектные gates, требуемые перед завершением
