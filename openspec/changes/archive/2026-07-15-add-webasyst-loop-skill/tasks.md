## 1. Подготовка и проверяемый контракт

- [x] 1.1 Зафиксировать acceptance-проверки для frontmatter, маршрутизации этапов, обязательных `complexity-optimizer` и `secure-review-loop`, OpenSpec-интеграции и границ delivery.
- [x] 1.2 Инициализировать repo-local skill `.codex/skills/webasyst-loop/` штатным скриптом `skill-creator`, удалить неиспользуемые шаблонные ресурсы и точечно разрешить отслеживание skill в Git.

## 2. Реализация навыка и OpenSpec-интеграции

- [x] 2.1 Описать в `webasyst-loop/SKILL.md` полный цикл, матрицу выбора профильных навыков, stop conditions и evidence для перехода между этапами.
- [x] 2.2 Обновить `openspec/config.yaml`: обязать OpenSpec загружать `webasyst-loop`, фиксировать выбранные skills в артефактах и выполнять финальные review-gates.
- [x] 2.3 Проверить, что навык сохраняет scope и не разрешает archive, commit, push, PR, merge или публикацию без запроса пользователя.

## 3. Валидация и завершающий review-loop

- [x] 3.1 Выполнить `quick_validate.py` и `security_scan.py --verbose` для нового skill, исправить подтверждённые замечания и повторить проверки.
- [x] 3.2 Выполнить `complexity-optimizer` для текущего diff и вручную проверить релевантные hotspots/риски N+1; для документационного изменения зафиксировать отсутствие runtime-hotspots.
- [x] 3.3 Выполнить `secure-review-loop` для текущего diff, исправить подтверждённые замечания и повторить доступные проверки.
- [ ] 3.4 Выполнить `openspec validate --strict --all`, проверить статус change и сформировать финальный отчёт с residual risk.

> Blocker: `add-webasyst-loop-skill` проходит strict validation, но общий `--all` падает на ранее существующем `stock-edit-without-warehouse-filter` из-за непарсящегося delta requirement. В текущей сессии также недоступны обязательные `codex-security:security-diff-scan` фазы из `secure-review-loop`; выполнены доступные проверки diff, skill security scan и проверка секретов, поэтому полное security coverage не заявляется.
