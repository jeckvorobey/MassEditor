# Mass Editor 1.2.0 — отчёт о подготовке релиза

## Статус

`READY FOR MANUAL STORE STEPS`: документация и локальный архив подготовлены; загрузка, цена, реальное обновление двух установок и публикация остаются ручными.

## Evidence range

- Предыдущий релиз: `1.1.0`, commit `9a93643`.
- Целевая версия: `1.2.0`.
- Диапазон анализа: `9a93643..HEAD` плюс текущие release-файлы.
- Product facts: `docs/release-data/product.json`.
- Release facts: `docs/release-data/release-1.2.0.json`.

## Обновлённые документы

- `docs/store-description-1_2_0.md` — актуальная кумулятивная RU/EN Store-карточка без отдельного блока «Новое в версии».
- `docs/release-1.2.0.md` — RU/EN delta после `1.1.0` и ограничения.
- `docs/CHANGELOG.md`, `docs/CHANGELOG.en.md` — обновлён только существующий блок `1.2.0`.
- `docs/store-publication.md` — актуальный scope, запреты и ручные действия.
- `docs/screens/1.2.0/README.md` — проверенный список файлов и локализованные подписи.
- `docs/release-1.2.0-publication-checklist.md` — gates и ручные пункты.

## Автоматические проверки

- Release validator: `READY`, blockers `0`.
- PHP: `146/146`, `782 assertions`.
- JavaScript: `40/40`.
- PHP/JS syntax: passed.
- Webasyst locale: `281` строк в каждой локали, updated `281`, new `0`.
- Gettext: RU/EN `msgfmt --check` и `msgunfmt` passed.
- Meta-updates: `4` целевых теста, `28 assertions`; оба timestamp-скрипта повторно безопасны.
- Complexity review: подтверждённых новых hotspots/N+1 нет; эвристические срабатывания относятся к ограниченным UI/whitelist/batch loops.
- Security review: framework CSRF, admin/per-product rights, typed SQL placeholders, input limits, escaping and safe error envelopes verified; Gitleaks clean.
- OpenSpec strict validation: `18 passed, 0 failed`; `git diff --check`: passed.

## Архив

- Путь: `dist/masseditor-1.2.0.tar.gz`.
- Сборка: официальный `php wa.php compress shop/plugins/masseditor`, `skip: none`.
- Размер: `99073` байта.
- Записей: `44` — `43` production-файла и штатный `masseditor/.files.md5`.
- SHA-256: `f89db4fe2e7245bdb4e31ea3221beac42fddcd0cc6d375a9ee691447a6dfd19c`.
- Gates: gzip, tar, один корень `masseditor/`, безопасные пути/типы, обязательные `.htaccess`, metadata, full MD5, distribution manifest, production diff и secret scan passed.
- Наблюдение: packager Webasyst завершился с exit `0`, но системный `waArchiveTar` вывел PHP 8.1 warnings при создании заголовков. Целостность результата независимо подтверждена всеми candidate gates.

## Остаточные риски и ручные действия

- Реальное обновление настроенной и почти пустой установки `1.1.0` не автоматизировано текущим стендом; его нужно выполнить перед Store upload.
- Скриншоты проверены как готовый комплект, но полный просмотр тестовых данных и порядок в кабинете остаются ручными.
- Цена `$69`, загрузка и публикация не менялись автоматически.
- Специализированный `codex-security` scanner в окружении недоступен; использованы профильный PHP review, framework evidence, тесты, archive scan и Gitleaks.

## Rollback

До внешней публикации восстановление выполняется возвратом предыдущего draft-архива из Git и откатом текущих release-файлов обычным Git workflow. После публикации архив не перезаписывать: готовить следующую трехчастную версию и отдельный meta-update при изменении схемы.
