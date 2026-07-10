## Why

Текущий `dist/masseditor-1.1.0.tar.gz` собран до последних изменений релиза и уже не совпадает с production-деревом `wa-apps/shop/plugins/masseditor/`: различаются PHP, JavaScript, CSS, шаблон, локализации и метаданные плагина. Нужен новый проверенный архив версии `1.1.0`, который содержит актуальный код и соответствует формату публикации Webasyst Store.

## What Changes

- Пересобрать `dist/masseditor-1.1.0.tar.gz` из текущего production-дерева плагина после прохождения релевантных PHP/JS-проверок.
- Включить в архив ровно одну корневую директорию `masseditor/` и только runtime-файлы из `wa-apps/shop/plugins/masseditor/`; не включать `docs/`, `tests/`, `openspec/`, Docker, IDE-файлы, Git-метаданные и другие материалы репозитория.
- Перед упаковкой синхронизировать бинарные `.mo` с актуальными `.po` и проверить наличие обязательных `.htaccess` со строкой `Deny from all` в защищаемых директориях.
- Проверить публикационные метаданные: ID `masseditor`, трехчастную версию `1.1.0`, числовой `vendor` и декларацию поддержки PREMIUM-возможностей Shop-Script.
- После сборки проверить целостность gzip/tar, состав архива, отсутствие лишних или пустых директорий, отсутствие отладочных и временных файлов, совпадение распакованного содержимого с production-деревом и зафиксировать SHA-256 итогового файла.
- Границы задачи: runtime-поведение, API, UI, схема БД, версия плагина, документация Store и скриншоты не изменяются. Архив не публикуется во внешний кабинет в рамках этого change.

## Capabilities

### New Capabilities

- `release-packaging`: правила формирования и проверки публикационного `tar.gz` архива Shop-Script плагина `masseditor` из актуального production-дерева.

### Modified Capabilities

Нет.

## Impact

- Релизный артефакт: `dist/masseditor-1.1.0.tar.gz` будет заменен актуальной сборкой той же версии.
- Production-источник: `wa-apps/shop/plugins/masseditor/` используется как единственный источник файлов архива; его runtime-файлы в рамках упаковки не изменяются, кроме пересборки `.mo` при подтвержденном расхождении с `.po`.
- Проверки: PHP syntax, `node --check`, полные PHP/JS-тесты проекта, проверка локализаций, проверка manifest и распакованного архива, `git diff --check`, `openspec validate --strict --all`.
- Пользовательский эффект: в Webasyst Store можно передать архив `1.1.0`, содержащий все фактически завершенные изменения текущего релиза без служебных файлов проекта.
- Официальные источники для реализации:
  - https://developers.webasyst.com/docs/store/webasyst-store-requirements/
  - https://developers.webasyst.com/docs/store/testing-before-publication/
  - https://developers.webasyst.com/docs/store/check-list/
  - https://developers.webasyst.com/docs/store/product-update/
