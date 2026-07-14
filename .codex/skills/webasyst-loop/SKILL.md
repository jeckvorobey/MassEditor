---
name: webasyst-loop
description: 'This skill should be used for the complete OpenSpec-driven development cycle of Webasyst and Shop-Script apps, plugins, widgets, and themes, from discovery and architecture through TDD implementation, review, release readiness, and completion, while routing each stage to the appropriate skills and requiring complexity-optimizer plus secure-review-loop before completion.'
---

# Webasyst Loop

## Назначение

Оркестрировать полный цикл разработки Webasyst/Shop-Script через OpenSpec. Выбирать профильные навыки по этапу и scope, сохранять TDD, подтверждать Webasyst API документацией и не считать работу завершённой без проверки сложности и безопасности.

Не заменять дочерние навыки пересказом. Перед действием загружать полный `SKILL.md` каждого выбранного навыка и выполнять его обязательный workflow.

## Обязательный контракт

Выполнять цикл для новой функции, исправления, нового приложения, плагина, виджета, темы, релиза или публикационной подготовки:

```text
Исследование -> архитектура -> OpenSpec-план -> TDD-реализация
-> документационный аудит -> complexity-optimizer -> secure-review-loop
-> тесты и OpenSpec validation -> отчёт -> явно запрошенная доставка
```

Перед началом прочитать `references/skill_routing.md` и выбрать минимальный достаточный набор навыков. Объявить пользователю выбранные навыки и порядок их применения.

Сохранять буквальный scope пользователя. Не добавлять storefront, настройки, зависимости, миграции, release-материалы или соседние функции без необходимости текущего изменения.

## Этап 1. Зафиксировать scope и исходное состояние

1. Определить тип продукта, app ID, plugin ID, рабочий корень и затронутые git-репозитории.
2. Проверить `AGENTS.md`, `openspec/config.yaml`, активные changes, git status и доступные команды тестов.
3. Зафиксировать пользовательский эффект, included scope, non-goals, данные и границы доверия.
4. Для новой коммерческой идеи или MVP применить `legacy-gap-product-builder`.
5. Для неясного поведения применить `openspec-explore`; не переходить к коду при выборе, который существенно меняет продукт.

## Этап 2. Подтвердить архитектуру и документацию

1. Применить `webasyst-shop-script-plugin-architect` до изменения hooks, actions, settings, config, install/uninstall, моделей, БД, templates, JavaScript или CSS.
2. Применить `webasyst-plugin-docs-auditor` и проверить каждый используемый Webasyst/Shop-Script механизм по официальной документации либо локальному рабочему аналогу.
3. Пометить неподтверждённый hook, API, base class, route, settings format или model method как `требуется проверка в документации` и остановить зависимую реализацию.
4. Зафиксировать права, CSRF, валидацию, экранирование, SQL safety, batch loading, отсутствие N+1, install/uninstall и i18n-контракт там, где это применимо.

## Этап 3. Создать или актуализировать OpenSpec change

1. Выбрать OpenSpec skill текущего действия: explore, propose, apply, sync или archive.
2. При создании change применить `openspec-propose` и получить пути артефактов через OpenSpec CLI.
3. В proposal указать пользовательский эффект, scope, non-goals, capability specs, официальные источники и выбранные навыки.
4. В design зафиксировать архитектуру, evidence Webasyst API, TDD-стратегию, безопасность, сложность, отсутствие N+1 и условия release-проверки.
5. В tasks сначала добавить тесты и проверки, затем реализацию, затем документационный аудит, `complexity-optimizer`, `secure-review-loop`, полные релевантные тесты и `openspec validate --strict --all`.
6. Не начинать реализацию, пока OpenSpec не сообщает apply-ready, кроме явно разрешённого исследовательского прототипа без production-изменений.

## Этап 4. Реализовать по TDD

1. Применить `openspec-apply-change` и прочитать все `contextFiles`, возвращённые OpenSpec CLI.
2. Для PHP применить `php-tdd-developer`; для другого стека применить доступный профильный TDD/testing skill и команды проекта.
3. Для каждого поведения сформулировать Given/When/Then и сначала добавить или изменить тест.
4. Подтвердить ожидаемое падение теста. Если тест сразу зелёный, проверить, воспроизводит ли он новое поведение или регрессию.
5. Реализовать минимальный код, достаточный для прохождения теста. Сохранять thin actions, сервисный слой, модели, i18n и принятые соглашения продукта.
6. Для PHP/backend mutation дополнительно применить `php-security-reviewer`; проверять права, CSRF, server-side validation, output escaping, safe SQL, лимиты, preview, confirmation и audit log.
7. Не допускать N+1: загружать связанные данные батчами и проверять tenant, permission, filtering, pagination и sorting constraints.
8. Выполнить узкий тест и syntax/lint check, затем отметить соответствующую OpenSpec-задачу выполненной.
9. Продолжать до `all_done` либо конкретного blocker. При выявлении design gap актуализировать OpenSpec-артефакты до продолжения.

## Этап 5. Завершить реализацию

1. Повторно применить `webasyst-plugin-docs-auditor` к изменённому diff и непосредственно связанным runtime-файлам.
2. Проверить локализацию всех новых видимых текстов, install/uninstall, миграции и ручные сценарии, если они затронуты.
3. Для релиза, архива или публикации применить `webasyst-store-compliance-reviewer`.
4. Для changelog применить `webasyst-changelog-writer`; для Store-описания применить `webasyst-plugin-description-writer`.
5. Не запускать release-подготовку для обычного функционального change, если она не входит в scope.

## Этап 6. Выполнить обязательный финальный review-loop

Не считать реализацию завершённой до выполнения обоих навыков в указанном порядке:

1. Применить `complexity-optimizer` к текущему git diff и непосредственно связанным runtime-файлам.
   - Запустить его scanner.
   - Проверить каждый релевантный hotspot вручную.
   - Исправлять только доказанные N+1, повторные линейные поиски, вложенные обходы, render churn и другие регрессии.
2. Применить `secure-review-loop` к тому же diff.
   - Выполнить все доступные обязательные фазы дочернего навыка.
   - Не заявлять полное security coverage без требуемых ledger/evidence.
3. Для каждого подтверждённого finding вернуться к этапу TDD, добавить или усилить тест, выполнить минимальное исправление и повторить оба review-навыка.
4. После чистого review запустить узкие, затем полные релевантные tests/lint/typecheck/build, миграционные проверки и `openspec validate --strict --all`.
5. Если обязательный skill, scanner, test environment или evidence недоступны, перечислить непроверенные зоны и residual risk. Не заявлять полное завершение финальной проверки.

Для документационного или skill-only diff всё равно применить оба review-навыка. Зафиксировать отсутствие runtime-hotspots, N+1 и исполняемых attack paths, не имитируя неприменимые code checks.

## Этап 7. Сформировать результат и выполнить только разрешённую доставку

В финальном отчёте указать:

- change и итоговый scope;
- применённые навыки;
- изменённые файлы;
- тесты, validation и ручные проверки;
- findings и исправления;
- непроверенные зоны и residual risk;
- состояние OpenSpec tasks.

Выполнять `openspec-sync-specs`, `openspec-archive-change`, commit, push, PR, merge, packaging или публикацию только по явному запросу пользователя или если это прямо входит в исходный scope. Не считать слово «заверши разработку» разрешением на внешнюю доставку.

## Stop Conditions

Остановить зависимую часть работы и сообщить конкретный blocker, если:

- выбор пользователя существенно меняет scope или публичный контракт;
- Webasyst API либо hook не подтверждены;
- TDD-тест невозможно сформулировать без неизвестного ожидаемого поведения;
- миграция или destructive operation не имеет безопасного rollback/confirmation;
- обязательная финальная проверка недоступна и её нельзя честно заменить;
- обнаружены пользовательские изменения, с которыми нельзя безопасно объединить текущий patch.

Не скрывать незакрытые findings и не отмечать OpenSpec-задачу выполненной без evidence.
