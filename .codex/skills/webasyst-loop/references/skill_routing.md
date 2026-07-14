# Маршрутизация навыков Webasyst Loop

Выбирать только навыки, относящиеся к текущему этапу и scope. Если пользователь явно называет навык, считать его обязательным дополнением.

| Сигнал задачи | Навык | Когда применять | Обязательное evidence |
| --- | --- | --- | --- |
| Неясная идея или требования | `openspec-explore` | До фиксации change | Варианты, границы и открытые вопросы |
| Новый коммерческий продукт или MVP | `legacy-gap-product-builder` | До архитектуры | Одна боль, included scope, non-goals, проверяемый релиз |
| Планирование или runtime-изменение Shop-Script-плагина | `webasyst-shop-script-plugin-architect` | До кода | Given/When/Then, файлы, integration points, DB и security |
| Любой новый или изменённый Webasyst API/hook/action/settings/config | `webasyst-plugin-docs-auditor` | До кода и после реализации | Официальный URL или локальный рабочий аналог для каждого механизма |
| Создание change | `openspec-propose` | После исследования | proposal, design, delta specs и tasks в apply-ready состоянии |
| Реализация change | `openspec-apply-change` | После apply-ready | Прочитанные context files и актуальные task checkboxes |
| PHP-поведение | `php-tdd-developer` | Во время реализации | Given/When/Then, failing test, минимальная реализация, `php -l`, PHPUnit |
| PHP/backend mutation или массовая операция | `php-security-reviewer` | При реализации и review | Authz, CSRF, validation, escaping, safe SQL, preview, confirmation, audit log |
| Финальная сложность и N+1 | `complexity-optimizer` | Всегда перед завершением | Scanner, ручная проверка hotspots, исправления и повторные тесты |
| Финальная безопасность diff | `secure-review-loop` | Всегда после complexity review | Выполненные доступные фазы, findings, исправления, residual risk |
| Релиз, архив или Store publication | `webasyst-store-compliance-reviewer` | Перед выпуском | Findings по структуре, runtime, SQL, install/uninstall и packaging |
| Changelog или update note | `webasyst-changelog-writer` | Только при изменении release notes | Синхронные RU/EN факты из реального diff |
| Store-описание | `webasyst-plugin-description-writer` | Только для Store-материалов | Фактические RU/EN тексты без выдуманных возможностей |
| Sync delta specs | `openspec-sync-specs` | По запросу на sync | Обновлённые main specs и строгая validation |
| Archive завершённого change | `openspec-archive-change` | По явному запросу | `all_done`, чистая validation и архивный результат |
| Commit и push | `git-commit` | Только по явному запросу | Проверенный scope, один тематический commit и push result |

## Минимальные наборы

### Функция Shop-Script-плагина

`webasyst-loop` + OpenSpec skill текущего действия + `webasyst-shop-script-plugin-architect` + `webasyst-plugin-docs-auditor` + TDD skill стека + security skill стека + `complexity-optimizer` + `secure-review-loop`.

### Новый коммерческий плагин или приложение

Добавить `legacy-gap-product-builder`. При подготовке релиза добавить Store compliance и только необходимые release-writing skills.

### Документационное или workflow-изменение

Применить OpenSpec skill текущего действия, релевантный docs/skill validator, затем обязательные `complexity-optimizer` и `secure-review-loop`. В отчёте явно указать, что runtime-code checks неприменимы, если исполняемый код не менялся.

## Правило недоступного навыка

Если обязательного навыка нет в текущей сессии или его required tool недоступен, перечислить недостающую проверку, выполнить только честно применимый fallback и не заявлять полное покрытие. Не заменять специализированный audit общим чтением diff без оговорки.
