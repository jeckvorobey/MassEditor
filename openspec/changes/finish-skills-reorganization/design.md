## Context

Реорганизация перенесла проектные skills из `.codex/skills/` в `.agents/skills/`, но оставила несколько разрывов: `AGENTS.md` игнорируется Git, пять универсальных OpenSpec-workflows всё ещё лежат в проекте, глобальный `skill-reviewer` ссылается на абстрактные команды, а часть конфигурации и спецификаций всё ещё называет удалённый `webasyst-loop`.

Изменение затрагивает три уровня discovery:

- проектные правила MassEditor в `.agents/skills/`;
- пользовательские универсальные skills в `~/.agents/skills/`;
- исходник глобального OpenSpec plugin в `/home/serg/plugins/openspec/`.

Резервная копия исходной `.codex/skills/` уже сохранена вне discovery-путей и должна остаться доступной для отката.

## Goals / Non-Goals

**Goals:**

- сделать `.agents/skills/` единственным проектным discovery-путём и отслеживать `AGENTS.md` в Git;
- сохранить уникальные правила MassEditor/Webasyst через короткие `SKILL.md` и тематические `references/`;
- сделать глобальный `skill-reviewer` самодостаточным и воспроизводимо проверяемым;
- перенести универсальное поведение пяти `openspec-*` skills в глобальный OpenSpec plugin без привязки к недоступным именам tools;
- подтвердить discovery в новом процессе Codex и пройти строгую OpenSpec-валидацию.

**Non-Goals:**

- менять runtime-код плагина MassEditor;
- удалять резервную копию;
- создавать коммит, отправлять изменения или архивировать этот change;
- менять поведение OpenSpec CLI.

## Decisions

### 1. Разделить источники истины по области действия

Проектные Webasyst/MassEditor skills остаются в `.agents/skills/`. Универсальные пользовательские skills остаются в `~/.agents/skills/`. Универсальные OpenSpec workflows становятся references единственного глобального skill `openspec` в исходнике plugin. Генерируемые `.security-scan-passed` внутри project skills не являются исходниками и игнорируются Git.

Это устраняет конкурирующие копии и сохраняет progressive disclosure. Альтернатива — оставить пять project-level OpenSpec skills — отклонена, потому что они не содержат правил MassEditor и будут расходиться с plugin.

### 2. Сохранить Webasyst-правила через явную карту покрытия

`webasyst-development` отвечает за архитектуру, evidence по официальной документации, TDD, безопасность, сложность и commercial MVP. `webasyst-release` отвечает за Store compliance, RU/EN description, changelog и packaging. Редкие правила из резервной копии переносятся в узкие references, а не раздувают entrypoint.

### 3. Поставлять валидаторы вместе с `skill-reviewer`

Актуальный `quick_validate.py` берётся из системного skill-creator, а `security_scan.py` — из сохранённого проектного skill-creator; оба поставляются в `~/.agents/skills/skill-reviewer/scripts/`. `SKILL.md` использует конкретные относительные команды. PyYAML запускается через `uv run --with pyyaml`, поэтому отсутствие системного модуля не маскируется упрощённой проверкой.

### 4. Сделать OpenSpec workflows tool-agnostic

Глобальный `openspec/SKILL.md` остаётся коротким router, а apply/archive/explore/propose/sync описываются в отдельных references. Инструкции требуют использовать доступный канал вопросов, планирования и делегирования, но не называют устаревшие `AskUserQuestion`, `TodoWrite` или `Task`.

Исходник plugin меняется в `/home/serg/plugins/openspec/`, после чего обновляется cachebuster и выполняется штатная переустановка plugin. Кэш напрямую не редактируется.

### 5. Проверять discovery отдельным процессом

Проверка файловой структуры недостаточна: итоговый gate должен запустить новый процесс Codex из корня MassEditor и подтвердить наличие `webasyst-development` и `webasyst-release` и отсутствие временных project-level `openspec-*` skills.

## Risks / Trade-offs

- Переустановка plugin обновит локальный глобальный discovery. Риск ограничен сохранением исходника в Git-репозитории plugin и запретом прямого редактирования кэша.
- Перенос временных project skills может скрыть несовпадение поведения. Риск снижается построчным сравнением и отдельными workflow references.
- `uv run --with pyyaml` требует установленного `uv`. Перед закреплением команды проверяется её фактический запуск; при отсутствии `uv` нужен явный документированный fallback, а не ложный success.
- Новый процесс Codex может увидеть старый plugin cache. Поэтому discovery проверяется только после штатного обновления cachebuster и reinstall.

## Migration Plan

1. Зафиксировать исходные разрывы read-only проверками.
2. Убрать `AGENTS.md` из ignore и обновить OpenSpec context/rules.
3. Сопоставить резервные Webasyst skills с двумя целевыми skills и дополнить references.
4. Сделать глобальный `skill-reviewer` самодостаточным и прогнать его scripts.
5. Расширить исходник OpenSpec plugin пятью workflow references, обновить cachebuster и переустановить plugin.
6. Переместить временные project-level `openspec-*` в существующую резервную область.
7. Прогнать discovery, structural/security validation, `git diff --check`, OpenSpec и релевантные проектные тесты.

Rollback: вернуть project skills из резервной копии и восстановить предыдущую ревизию `/home/serg/plugins/openspec/`; кэш plugin заново создать штатной установкой.

## Open Questions

Нет блокирующих вопросов. Архивация и удаление резервной копии намеренно остаются за пределами change.
