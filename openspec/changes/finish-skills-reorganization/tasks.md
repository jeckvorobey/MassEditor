## 1. Зафиксировать проверки и источники

- [x] 1.1 Зафиксировать red-state: `AGENTS.md` игнорируется, OpenSpec ссылается на `webasyst-loop`, project-level `openspec-*` дублируют plugin, а полный validator не запускается без YAML-зависимости.
- [x] 1.2 Сопоставить правила резервных Webasyst skills с `webasyst-development` и `webasyst-release` и перечислить пробелы progressive disclosure.

## 2. Исправить project discovery и правила

- [x] 2.1 Убрать `AGENTS.md` из `.gitignore` и подтвердить, что файл виден в `git status`.
- [x] 2.2 Обновить `openspec/config.yaml`, заменив удалённые skill names актуальными `webasyst-development`, `webasyst-release` и глобальным OpenSpec workflow.
- [x] 2.3 Дополнить references двух Webasyst skills уникальными правилами архитектуры, documentation evidence, TDD/security/complexity, commercial MVP, Store compliance, RU/EN copy, changelog и packaging без раздувания `SKILL.md`.

## 3. Сделать глобальные skills самодостаточными

- [x] 3.1 Добавить в `~/.agents/skills/skill-reviewer/scripts/` актуальный bundled `quick_validate.py` из системного skill-creator и сохранённый `security_scan.py` из резервной копии.
- [x] 3.2 Заменить placeholders в глобальном `skill-reviewer/SKILL.md` конкретными относительными командами и проверить их запуск с доступной YAML-зависимостью.

## 4. Консолидировать OpenSpec workflows

- [x] 4.1 Добавить в исходник глобального OpenSpec plugin progressive-disclosure references для propose, apply, explore, sync и archive, сохранив полезное поведение project skills и убрав привязку к устаревшим tool names.
- [x] 4.2 Обновить router глобального `openspec/SKILL.md`, проверить plugin structure, обновить cachebuster и переустановить plugin штатной командой без прямого изменения cache.
- [x] 4.3 Переместить пять временных `.agents/skills/openspec-*` в резервную область вне discovery и подтвердить отсутствие активных project-level копий.

## 5. Провести обязательный аудит

- [x] 5.1 Выполнить documentation-evidence аудит новых Webasyst references и подтвердить отсутствие старых operational-ссылок в `AGENTS.md`, `.gitignore`, `openspec/config.yaml` и active project skills.
- [x] 5.2 Применить `complexity-optimizer` к текущему diff и связанным исполняемым scripts, исправить подтверждённые findings и повторить проверку.
- [x] 5.3 Применить `secure-review-loop` к текущему diff и связанным исполняемым scripts, исправить подтверждённые findings и повторить проверку.

## 6. Выполнить итоговую проверку

- [x] 6.1 Проверить новым процессом Codex discovery `webasyst-development`, `webasyst-release` и глобального `openspec`, а также отсутствие временных project-level `openspec-*`.
- [x] 6.2 Запустить structural/security validation всех новых и перенесённых skills, `git diff --check` и поиск старых активных ссылок.
- [x] 6.3 Запустить `bash tests/run-js-tests.sh` и `bash tests/run-php-tests.sh`.
- [x] 6.4 Запустить `openspec validate --strict --all`, сверить task checkboxes с фактическими изменениями и показать итоговые `git status` и diff summary без commit, push, sync или archive.

## 7. Исправить замечания review

- [x] 7.1 Исправить Docker URL в `AGENTS.md` на опубликованный host-порт `8088` и сверить его с compose-конфигурацией.
- [x] 7.2 Добавить ignore-правило для `.agents/skills/**/.security-scan-passed` и проверить его через `git check-ignore`.
- [x] 7.3 Повторить `git diff --check` и `openspec validate --strict --all` после review-fixes.
