## 1. Красные проверки релизного кандидата

- [x] 1.1 Подтвердить ненулевой рекурсивный diff текущего `dist/masseditor-1.2.0.tar.gz` относительно production-дерева и сохранить список отличающихся файлов.
- [x] 1.2 Скомпилировать RU/EN `.po` во временные `.mo` через `msgfmt --check` и подтвердить byte mismatch с текущими production `.mo`.
- [x] 1.3 Проверить текущую версию, числовой `vendor`, PREMIUM-декларацию, корень архива и доступность доверенного `msgfmt`.

## 2. Минимальная реализация

- [x] 2.1 Обновить `docs/release-1.2.0.md` фактическими возможностями массового видео и множественных общих характеристик, сохранив явные non-goals.
- [x] 2.2 Пересобрать `ru_RU` и `en_US` `shop_masseditor.mo` из текущих `.po` с gettext-проверкой.
- [x] 2.3 Создать временный `masseditor/` staging из production-дерева и собрать проверяемый `masseditor-1.2.0.tar.gz` кандидат.
- [x] 2.4 После успешных проверок заменить финальный `dist/masseditor-1.2.0.tar.gz` кандидатом.

## 3. Документационный и Store compliance аудит

- [x] 3.1 Выполнить `webasyst-plugin-docs-auditor`: сверить структуру, metadata, `.htaccess`, локализации и release-документ с официальной документацией.
- [x] 3.2 Выполнить `webasyst-store-compliance-reviewer`: проверить manifest, безопасные пути, отсутствие dev/debug-файлов, корневую директорию, PREMIUM и Webasyst 2 release-контракт.

## 4. Обязательный финальный review-loop

- [x] 4.1 Запустить scanner `complexity-optimizer`, вручную проверить findings текущего diff и зафиксировать отсутствие runtime/N+1-регрессий либо исправить подтвержденные замечания.
- [x] 4.2 Выполнить `secure-review-loop` для текущего diff, применить доступные security-проверки и явно зафиксировать недоступные специализированные scanners как residual risk.

## 5. Финальная валидация

- [x] 5.1 Запустить полные PHP/JS suites, PHP/JS syntax checks и gettext-проверки обеих локалей.
- [x] 5.2 Проверить `gzip -t`, tar manifest, единственный корень `masseditor/`, безопасные пути, обязательные `.htaccess`, metadata и отсутствие dev-файлов.
- [x] 5.3 Распаковать финальный архив и подтвердить нулевой рекурсивный diff с production-деревом.
- [x] 5.4 Запустить `git diff --check` и `openspec validate --strict --all`, вывести размер, число файлов и SHA-256 архива.

## 6. Повторная подготовка полного релиза после 1.1.0

- [x] 6.1 Зафиксировать evidence-диапазон `9a93643..HEAD`, текущую конфигурацию, локали, meta-updates, готовые скриншоты и расхождение draft-архива с production-кодом.
- [x] 6.2 Скопировать нейтральные templates в `docs/release-data/`, заполнить product/release/changelog/archive manifests и добиться успешного dry-run validator до публичных документов.
- [x] 6.3 Сначала изменить PHP-тест на целевую версию `1.2.0` и два timestamp meta-update в одном каталоге, подтвердить падение на текущем `1.2.1`.
- [x] 6.4 Вернуть `plugin.php` к `1.2.0`, перенести rollback meta-update в `lib/updates/1.2.0/` и подтвердить идемпотентность обоих скриптов.

## 7. Документы только текущей версии

- [x] 7.1 Обновить `docs/store-description-1_2_0.md`, `docs/release-1.2.0.md`, только блок `1.2.0` в RU/EN changelog и `docs/store-publication.md` из единого release dataset.
- [x] 7.2 Добавить versioned publication checklist и release report с evidence, ограничениями, ручными действиями и rollback-инструкцией.
- [x] 7.3 Проверить 10 готовых файлов в `docs/screens/1.2.0/` и подготовить согласованные RU/EN подписи без изменения изображений.
- [x] 7.4 Доказать, что отдельные документы и changelog-блоки `1.0.0`/`1.1.0` не изменены.

## 8. Локализация, проверки и архив

- [x] 8.1 Обновить gettext штатным Webasyst workflow, проверить/скомпилировать обе локали и повторно запустить release-data validator.
- [x] 8.2 Запустить PHP/JS suites, PHP/JS syntax, `complexity-optimizer`, затем `secure-review-loop` и исправить подтверждённые замечания.
- [x] 8.3 Собрать кандидат официальной командой `php wa.php compress shop/plugins/masseditor` без `-skip test` и заменить draft только после успешных gates.
- [x] 8.4 Проверить gzip/tar, единственный корень, безопасные пути, manifest, `.htaccess`, metadata, dev/secret exclusions; записать размер, file count и SHA-256.
- [x] 8.5 Выполнить `git diff --check`, `openspec validate --strict --all` и подтвердить полный task progress без Store upload.
