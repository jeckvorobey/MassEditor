## Context

`webasyst-release` сейчас состоит из короткого `SKILL.md` и трёх references. Он не хранит reusable templates, не определяет промежуточный release dataset и не проверяет смысловые инварианты между конфигурацией продукта, Store description, release note, changelog, локалями и архивом. Аудит MassEditor подтвердил системные симптомы: `release-1.1.1.md` отсутствует в обоих changelog, публичные документы содержат Docker/refactor-детали, «Как это работает» перечисляет устаревший набор операций, а packaging contract требует нулевой diff со всем source tree.

Официальная документация Webasyst проверена 2026-07-19. Она подтверждает трёхчастную версию и числовой vendor, единственный корневой каталог tar.gz, штатный `php wa.php compress <slug>`, обновление gettext, условия meta-updates и их повторную безопасность, а также необходимость тестирования обновления с предыдущей версии.

## Goals / Non-Goals

**Goals:**

- Превратить существующий skill в универсальный analyzer-driven workflow для любого Webasyst app/plugin/widget/theme.
- Формировать продуктовые факты из целевого репозитория, а не хранить внутри skill функции MassEditor.
- Дать единообразные templates и общий release dataset, из которого обновляются публичные документы.
- Автоматически проверять переносимые release-инварианты без новых runtime-зависимостей.
- Разделять Store description, version delta, полный changelog, engineering evidence и ручную публикацию.

**Non-Goals:**

- Не генерировать функции по имени UI-элемента или скриншоту.
- Не исправлять текущие release-документы MassEditor этим change.
- Не менять runtime-код, версию продукта и meta-updates.
- Не собирать/перезаписывать архив и не публиковать продукт.
- Не создавать реальные backend-скриншоты.

## Decisions

### 1. Skill хранит workflow и templates, данные принадлежат целевому проекту

В `assets/templates/` будут нейтральные JSON/Markdown-шаблоны. При release-задаче агент копирует и заполняет их в выбранные проектом пути (по умолчанию `docs/release-data/`), анализируя config, Git history/diff, код, тесты, локали, meta-updates и прошлые документы. Альтернатива — встроить MassEditor-каталог в skill — отклонена как непереносимая и быстро устаревающая.

### 2. Два SSOT-документа

`product.json` описывает текущее подтверждённое состояние продукта и возможности с `since_version`; `releases/<version>.json` содержит только delta версии, evidence, data/localization/archive state. Это устраняет ручное дублирование и позволяет идемпотентно перегенерировать документы.

### 3. JSON Schema и semantic validator на standard library

Schema задаёт переносимую форму, а `scripts/validate_release.py` проверяет межфайловые инварианты, которые неудобно выразить JSON Schema: версии, локали, evidence, changelog gaps, placeholders, публичные/internal facts, meta-update, archive root/forbidden paths и published overwrite. Python выбран из-за доступности стандартной библиотеки; PyYAML и другие runtime-зависимости не добавляются.

### 4. Analyzer остаётся агентным workflow

Автоматический сканер не может надёжно доказать пользовательский эффект произвольного Webasyst-кода. `SKILL.md` задаёт конкретный evidence-order и требует классифицировать каждый факт как confirmed, manual или unconfirmed. Validator блокирует публичные claims без evidence, но не изобретает их.

### 5. Упаковка через официальный CLI

Основной путь — `php wa.php compress <slug>` без `-skip test`. Результат ищется по фактическому выводу/файловой системе и проверяется относительно generated distribution manifest, а не всего source tree. Опубликованный архив нельзя перезаписать без отдельного разрешения.

### 6. Progressive disclosure

`SKILL.md` остаётся компактным и маршрутизирует к references: evidence/model, documentation, Store compliance, localization/meta-updates, packaging. Подробные templates живут в assets и не загружаются без необходимости.

## Risks / Trade-offs

- [Функцию ошибочно признали подтверждённой] → требовать минимум один конкретный code/test/manual evidence и отдельно хранить unconfirmed facts.
- [История Git не отражает опубликованные версии] → сопоставлять tags, release notes, changelog и explicit publication status; при конфликте останавливать READY.
- [JSON Schema не покрывает semantic parity] → выполнять отдельный validator и tests.
- [Штатный Webasyst CLI недоступен] → завершать packaging как BLOCKED; fallback разрешать только явно и проверять теми же gates.
- [Текущие пользовательские изменения пересекаются со skill] → менять только `.agents/skills/webasyst-release`, новый OpenSpec change, `AGENTS.md` и целевые строки specs; не трогать screenshots и release-документы.

## Migration Plan

1. Добавить templates/schema/tests и падающие validator tests.
2. Реализовать validator до прохождения tests.
3. Переписать `SKILL.md` и references вокруг analyzer-driven workflow.
4. Обновить `AGENTS.md` и OpenSpec contracts, удалить устаревшее имя.
5. Выполнить skill validation, security scan, static review и strict OpenSpec validation.
6. Оставить migration текущих MassEditor release-документов отдельной будущей release-задачей.

Rollback: удалить новые assets/scripts/tests/references и вернуть изменённые skill/spec/rule-файлы по обычному Git diff; данные продукта и runtime не затрагиваются.

## Open Questions

Нет. Пути проектного release dataset остаются конфигурируемыми, а нейтральные defaults описываются в reference.
