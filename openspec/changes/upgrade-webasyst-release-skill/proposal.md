## Why

Текущий `webasyst-release` задаёт только общий release-checklist и не обеспечивает единый доказательный источник для Store description, release note, changelog, локализаций и архива. Из-за этого документация MassEditor уже расходится по версиям и содержанию, а release-проверки опираются на ручные списки и сравнение архива со всем source tree вместо ожидаемого distribution manifest.

## What Changes

- Доработать существующий project skill `webasyst-release`, не создавая параллельный workflow.
- Сделать workflow универсальным: анализировать target-product, Git history/diff, код, тесты, локали, meta-updates и предыдущие документы, а не хранить внутри skill функции MassEditor.
- Добавить нейтральные шаблоны каталога возможностей, release manifest, Store description, release note, changelog, publication checklist и итогового report для единообразного заполнения.
- Добавить JSON Schema и dependency-free validator для version consistency, evidence, локалей, changelog completeness, published-archive protection и других release-инвариантов.
- Добавить TDD-тесты validator и dry-run на fixture-данных, включая дополнительную локаль, пропущенную patch-версию, неполный перевод, недоказанную функцию и опасный archive manifest.
- Обновить release references по официальной документации Webasyst, проверенной 2026-07-19: Store requirements, product updates, self-check list, console tools, meta-updates и plugin platform.
- Удалить из актуального `quality-workflow` упоминание старого имени `webasyst-loop`; требовать только реально доступные skills без перечисления удалённого имени.
- Уточнить `AGENTS.md`: release/Store-задачи используют `webasyst-release`, а публичные документы формируются из проверяемых фактов конкретного продукта.
- Не менять runtime-код MassEditor, не переписывать текущие release-документы в рамках доработки skill, не собирать релизный архив, не создавать скриншоты и не публиковать продукт.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `quality-workflow`: универсальный analyzer-driven release workflow, обязательные шаблоны/валидация и удаление устаревшего имени skill.
- `store-release-materials`: Store description, release note и changelog формируются из общего доказательного release dataset для всех обнаруженных локалей.
- `release-packaging`: архив создаётся штатным Webasyst CLI и сверяется с ожидаемым distribution manifest с защитой опубликованных артефактов.

## Impact

- `.agents/skills/webasyst-release/`: `SKILL.md`, references, assets/templates, scripts и tests.
- `AGENTS.md` и main/delta OpenSpec specs рабочего процесса, Store-материалов и упаковки.
- Новая runtime-зависимость не добавляется: validator использует Python standard library; JSON Schema хранится как переносимый контракт.
- Официальные источники: `developers.webasyst.com/docs/store/webasyst-store-requirements/`, `product-update/`, `check-list/`, `features/console-tools/`, `cookbook/meta-updates/`, `cookbook/plugins/`.
