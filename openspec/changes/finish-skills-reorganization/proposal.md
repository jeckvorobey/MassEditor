## Why

Перенос skills в `.agents/skills/` завершил механическую миграцию, но оставил несколько операционных разрывов: `AGENTS.md` не отслеживается, стандартный validator не запускается без PyYAML, global `skill-reviewer` не имеет исполняемого validation contract, OpenSpec-контекст всё ещё ссылается на `webasyst-loop`, а пять универсальных OpenSpec workflows временно дублируются в проекте. Эти разрывы нужно закрыть до коммита, чтобы новая структура действительно стала источником истины.

## What Changes

- Начать отслеживать `AGENTS.md` и закрепить `.agents/skills/` как project skills root.
- Довести `webasyst-development` и `webasyst-release` до доказуемого покрытия уникальных правил из резервной копии с progressive disclosure.
- Сделать global `skill-reviewer` самодостаточным: конкретные bundled validator/security scripts и воспроизводимые команды.
- Перенести пять универсальных `openspec-*` workflows в source глобального OpenSpec plugin, адаптировать tool-agnostic prompts и удалить временные project-копии после проверки.
- Обновить repo-local OpenSpec context и `quality-workflow` на новые skill names и paths.
- Проверить discovery в свежем Codex process, frontmatter, ссылки, тесты и strict OpenSpec validation.
- Не создавать commit, не отправлять изменения в remote и не удалять резервную копию.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `quality-workflow`: project workflow должен использовать `.agents/skills/webasyst-development` и `.agents/skills/webasyst-release`, global OpenSpec plugin и воспроизводимую проверку skills без старых активных ссылок.

## Impact

- Project source: `.agents/skills/`, `AGENTS.md`, `.gitignore`, `openspec/config.yaml`, `openspec/specs/quality-workflow/spec.md` после последующего sync.
- OpenSpec change: proposal, design, delta spec и tasks для завершения реорганизации.
- Global user skills: `~/.agents/skills/skill-reviewer/`.
- Global OpenSpec plugin source: `/home/serg/plugins/openspec/`; cache редактировать напрямую нельзя.
- Runtime плагина MassEditor, PHP/JS бизнес-логика, БД, локализация и релизный архив не меняются.
