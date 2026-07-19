## 1. Контракт и TDD

- [x] 1.1 Добавить fixture-данные и падающие tests semantic validator для valid release, пропущенной patch-версии, повторной версии, дополнительной локали, неполного перевода, version mismatch, meta-update, unsupported claim, unsafe archive и published overwrite
- [x] 1.2 Добавить нейтральные JSON/Markdown templates и JSON Schema без MassEditor-specific фактов
- [x] 1.3 Реализовать dependency-free `validate_release.py` и dry-run report до прохождения tests

## 2. Доработка webasyst-release

- [x] 2.1 Переписать `SKILL.md` как универсальный analyzer-driven workflow с progressive disclosure и явными boundaries
- [x] 2.2 Обновить references для evidence/data model, документации, локалей/meta-updates, Store compliance/copy и официального packaging workflow
- [x] 2.3 Зафиксировать официальные Webasyst-источники и дату проверки 2026-07-19 без копирования документации

## 3. Правила проекта

- [x] 3.1 Удалить устаревшее имя `webasyst-loop` из актуального main `quality-workflow` и заменить зависимость правилом доступных skills
- [x] 3.2 Обновить `AGENTS.md` для обязательного `webasyst-release` и evidence-derived release documents
- [x] 3.3 Обновить затронутые main specs `quality-workflow`, `store-release-materials` и `release-packaging` в соответствии с delta
- [x] 3.4 Исправить `.gitignore`, чтобы новые файлы `.agents/skills/` отслеживались, а security marker оставался ignored

## 4. Проверки

- [x] 4.1 Выполнить tests validator, dry-run на нейтральной fixture и `git diff --check`
- [x] 4.2 Выполнить structural/security self-review через `skill-reviewer` и manual evaluation checklist
- [x] 4.3 Повторить documentation-evidence audit по официальным источникам Webasyst
- [x] 4.4 Выполнить `complexity-optimizer`, затем `secure-review-loop`, исправить подтверждённые замечания
- [x] 4.5 Выполнить `openspec validate --strict --all` и подтвердить полный task progress без archive/commit/push
