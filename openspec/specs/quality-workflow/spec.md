## Purpose

Зафиксировать рабочий процесс разработки MassEditor: OpenSpec на русском, TDD, сверка с документацией Webasyst/Shop-Script, проверки и ограничения безопасности.

## Requirements

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

### Requirement: Источник истины project skills
Проект MUST хранить активные MassEditor/Webasyst skills в `.agents/skills/` и MUST NOT сохранять активные копии в `.codex/skills/`. `AGENTS.md` MUST быть доступен Git для воспроизводимого применения репозиторных правил.

#### Scenario: Новый процесс обнаруживает project skills
- **WHEN** новый процесс Codex запускается из корня MassEditor
- **THEN** он обнаруживает `webasyst-development` и `webasyst-release` из `.agents/skills/`
- **AND** не обнаруживает удалённые или временные project-level дубликаты

### Requirement: Воспроизводимая проверка skills
Skill для review MUST поставлять конкретные локальные команды проверки frontmatter, структуры и безопасности. Отсутствующая зависимость валидатора MUST приводить к явной ошибке или к документированному установочному запуску и MUST NOT считаться полной успешной проверкой через упрощённый fallback.

#### Scenario: Проверка нового skill
- **WHEN** разработчик запускает команды из глобального `skill-reviewer`
- **THEN** bundled structural validator проверяет `SKILL.md` с доступной YAML-зависимостью
- **AND** bundled security scanner выполняет заявленную проверку

### Requirement: Универсальные OpenSpec workflows принадлежат plugin
Глобальный OpenSpec plugin MUST содержать progressive-disclosure инструкции для propose, apply, explore, sync и archive. Эти инструкции MUST быть независимы от конкретных недоступных имён tools, а проект MassEditor MUST NOT хранить их активные универсальные копии после миграции.

#### Scenario: Выбор OpenSpec workflow
- **WHEN** пользователь просит предложить, применить, исследовать, синхронизировать или архивировать change
- **THEN** глобальный skill `openspec` направляет агента к соответствующему reference
- **AND** workflow использует доступные в текущей среде механизмы вопросов, планирования и исполнения

### Requirement: OpenSpec на русском
Все OpenSpec proposals, specs, designs, tasks и отчеты по изменениям MUST вестись на русском языке.

#### Scenario: Новое изменение
- **WHEN** создается новый OpenSpec change
- **THEN** proposal MUST описывать пользовательский эффект, scope, non-goals, затронутые capability specs и ссылки на релевантную документацию Webasyst/Shop-Script

### Requirement: Сверка с документацией
Перед изменениями механизмов Webasyst/Shop-Script MUST выполняться сверка с официальной документацией Webasyst/Shop-Script как единственным источником истины для архитектурного решения.

#### Scenario: Новый hook, action, settings или DB behavior
- **WHEN** изменение использует hook, backend route/action, settings.php, db.php, install/uninstall, waPlugin/waModel/waRequest API или Shop-Script model API
- **THEN** решение MUST быть подтверждено официальной документацией Webasyst/Shop-Script; неподтвержденная деталь MUST быть исключена из реализации либо помечена как `требуется проверка в документации`

### Requirement: TDD перед реализацией
Кодовые изменения MUST начинаться с теста ожидаемого поведения, затем выполняется минимальная реализация и релевантные проверки.

#### Scenario: PHP-изменение
- **WHEN** меняется PHP-сервис, action, модель, настройки или config
- **THEN** сначала MUST быть добавлен или обновлен тест в `tests/php/*Test.php`, затем реализация, затем `php -l` для измененных PHP-файлов и `bash tests/run-php-tests.sh`

#### Scenario: JS-изменение
- **WHEN** меняется `js/masseditor.js` или frontend-поведение backend UI
- **THEN** сначала MUST быть добавлен или обновлен тест в `tests/js/*.test.js`, затем реализация, затем `node --check wa-apps/shop/plugins/masseditor/js/masseditor.js` и `bash tests/run-js-tests.sh`

### Requirement: Безопасность backend-операций
Mutating paths MUST проверять права, CSRF, входные данные, whitelist значений и не раскрывать технические ошибки пользователю.

#### Scenario: Ошибка выполнения
- **WHEN** непредвиденное исключение возникает в backend action
- **THEN** UI MUST показать локализованную generic error, а технические детали MUST быть записаны в `shop/plugins/masseditor.log`

#### Scenario: SQL и связанные данные
- **WHEN** код строит SQL по пользовательским фильтрам или сортировкам
- **THEN** SQL MUST использовать параметры/плейсхолдеры, приведение типов и whitelist, а связанные данные MUST загружаться батчами без N+1

### Requirement: Проверки перед завершением
Перед завершением изменения агент MUST выполнить `bash tests/run-js-tests.sh`, `bash tests/run-php-tests.sh`, `openspec validate --strict --all`, `git diff --check` и релевантные проверки структуры skills, если change затрагивает skills. Если какая-либо проверка не может быть выполнена, причина MUST быть явно указана в итоговом отчёте.

#### Scenario: Только документация/OpenSpec
- **WHEN** изменение затрагивает только документацию или OpenSpec
- **THEN** MUST быть выполнена OpenSpec-валидация, а тесты кода можно не запускать, если runtime-код не менялся

#### Scenario: Все проверки успешны
- **WHEN** реализация завершена
- **THEN** JS-тесты, PHP-тесты, строгая OpenSpec-валидация и `git diff --check` завершаются успешно
- **AND** затронутые skills проходят concrete structural/security validation

#### Scenario: Проверка недоступна
- **WHEN** требуемая проверка не может быть выполнена из-за отсутствующей зависимости или среды
- **THEN** агент не помечает её как успешно пройденную
- **AND** указывает точную причину и оставшийся шаг

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

### Requirement: Оркестрация полного цикла через webasyst-development
Для изменений Webasyst/Shop-Script агент MUST использовать проектный `webasyst-development` как основной skill разработки и MUST загружать только релевантные references по progressive disclosure. Для release-задач агент MUST дополнительно использовать `webasyst-release`.

#### Scenario: Задача изменяет код плагина
- **WHEN** пользователь просит реализовать или исправить поведение MassEditor
- **THEN** агент применяет `webasyst-development`
- **AND** использует OpenSpec, documentation evidence, TDD, security и complexity правила в соответствии со scope

#### Scenario: Задача готовит публикацию
- **WHEN** scope включает Store materials, changelog или release archive
- **THEN** агент дополнительно применяет `webasyst-release`
- **AND** загружает только необходимые release references

### Requirement: Выбор профильных навыков по этапу и scope
Агент MUST выбирать skills по фактическому этапу: глобальный `openspec` для change workflows, `webasyst-development` для архитектуры и реализации MassEditor, `webasyst-release` для публикации, а универсальные PHP/TDD/security/review skills — только когда они релевантны scope. Агент MUST использовать только реально доступные skills и MUST NOT зависеть от отсутствующих project-level workflows.

#### Scenario: Выполняется docs-only изменение skills
- **WHEN** change не затрагивает runtime PHP/JS
- **THEN** агент применяет OpenSpec и проверки skills
- **AND** не создаёт искусственные runtime-тесты
- **AND** всё равно запускает существующие проектные gates, требуемые перед завершением

### Requirement: TDD-реализация и сохранение границ
Кодовые задачи MUST выполняться тестами сначала, минимальной реализацией после воспроизводимого failing test и повторной проверкой после refactor; `webasyst-development` MUST сохранять заданный scope и MUST NOT разрешать недокументированные Webasyst API, N+1, небезопасные mutation paths или автоматическое расширение продукта.

#### Scenario: Реализация PHP или JavaScript поведения
- **WHEN** OpenSpec-задача изменяет runtime-поведение
- **THEN** агент MUST сначала добавить или изменить релевантный тест, подтвердить ожидаемое падение, реализовать минимальное исправление, выполнить syntax check и узкие тесты, затем обновить checkbox задачи

#### Scenario: Неподтверждённый Webasyst-механизм
- **WHEN** hook, API, base class, route, settings format или модельный метод не подтверждены официальной документацией либо локальным кодом
- **THEN** агент MUST остановить зависимую реализацию и пометить механизм как `требуется проверка в документации`, не подменяя evidence предположением

### Requirement: Обязательный финальный review-loop
Перед признанием реализации завершённой агент MUST отдельно применить `complexity-optimizer`, затем `secure-review-loop` к текущему diff и непосредственно связанным runtime-файлам, исправить все подтверждённые замечания и повторять проверки до отсутствия подтверждённых findings либо до явного blocker.

#### Scenario: Финальная проверка без замечаний
- **WHEN** реализация и OpenSpec-задачи завершены
- **THEN** агент MUST выполнить complexity scan с ручной проверкой релевантных hotspots, полный доступный secure review текущего diff, узкие и полные релевантные тесты, syntax/lint/build проверки и `openspec validate --strict --all`

#### Scenario: Финальная проверка обнаружила замечание
- **WHEN** `complexity-optimizer` или `secure-review-loop` подтверждает регрессию сложности, N+1 или проблему безопасности
- **THEN** агент MUST вернуть finding в TDD-цикл, исправить его в текущем scope и повторить оба финальных review-навыка и релевантные проверки

#### Scenario: Обязательная проверка недоступна
- **WHEN** обязательный review-skill, scanner, тестовая среда или необходимый evidence недоступны
- **THEN** агент MUST перечислить непроверенные области и residual risk и MUST NOT заявлять полное покрытие или завершение финальной проверки

### Requirement: Явное завершение и доставка
После реализации `webasyst-development` MUST завершить все обязательные проверки и сообщить их результаты. Коммит, push, PR, merge, archive или иное внешнее действие MUST выполняться только по явному запросу пользователя.

#### Scenario: Реализация завершена без запроса на доставку
- **WHEN** код или документы готовы и проверки пройдены
- **THEN** агент возвращает отчёт и текущий Git status
- **AND** не создаёт коммит, не отправляет изменения и не архивирует OpenSpec change
