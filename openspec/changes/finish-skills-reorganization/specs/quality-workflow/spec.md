## ADDED Requirements

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

## MODIFIED Requirements

### Requirement: Проверки перед завершением
Перед завершением изменения агент MUST выполнить `bash tests/run-js-tests.sh`, `bash tests/run-php-tests.sh`, `openspec validate --strict --all`, `git diff --check` и релевантные проверки структуры skills, если change затрагивает skills. Если какая-либо проверка не может быть выполнена, причина MUST быть явно указана в итоговом отчёте.

#### Scenario: Все проверки успешны
- **WHEN** реализация завершена
- **THEN** JS-тесты, PHP-тесты, строгая OpenSpec-валидация и `git diff --check` завершаются успешно
- **AND** затронутые skills проходят concrete structural/security validation

#### Scenario: Проверка недоступна
- **WHEN** требуемая проверка не может быть выполнена из-за отсутствующей зависимости или среды
- **THEN** агент не помечает её как успешно пройденную
- **AND** указывает точную причину и оставшийся шаг

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
Агент MUST выбирать skills по фактическому этапу: глобальный `openspec` для change workflows, `webasyst-development` для архитектуры и реализации MassEditor, `webasyst-release` для публикации, а универсальные PHP/TDD/security/review skills — только когда они релевантны scope. Агент MUST NOT ссылаться на удалённый `webasyst-loop` или отсутствующие project-level skills.

#### Scenario: Выполняется docs-only изменение skills
- **WHEN** change не затрагивает runtime PHP/JS
- **THEN** агент применяет OpenSpec и проверки skills
- **AND** не создаёт искусственные runtime-тесты
- **AND** всё равно запускает существующие проектные gates, требуемые перед завершением

### Requirement: Явное завершение и доставка
После реализации `webasyst-development` MUST завершить все обязательные проверки и сообщить их результаты. Коммит, push, PR, merge, archive или иное внешнее действие MUST выполняться только по явному запросу пользователя.

#### Scenario: Реализация завершена без запроса на доставку
- **WHEN** код или документы готовы и проверки пройдены
- **THEN** агент возвращает отчёт и текущий Git status
- **AND** не создаёт коммит, не отправляет изменения и не архивирует OpenSpec change

## RENAMED Requirements

- FROM: `### Requirement: Оркестрация полного цикла через webasyst-loop`
- TO: `### Requirement: Оркестрация полного цикла через webasyst-development`
