## Why

После первоначальной подготовки кандидата `1.2.0` в плагин вошли исправление проверки видеоссылок, явный прогресс массовой операции, сброс формы после успеха, уточнённые подписи и безопасный откат последней операции. По решению владельца продукта все пользовательские изменения после релиза `1.1.0` должны впервые поставляться в `1.2.0`, поэтому промежуточная версия `1.2.1` в `plugin.php` и каталоге meta-update противоречит целевому релизу, а документацию и архив необходимо пересобрать из текущего кода.

## What Changes

- Зафиксировать evidence-диапазон от коммита релиза `1.1.0` до текущего production-кода и собрать единый project-owned release dataset.
- Вернуть конфигурацию к целевой версии `1.2.0` и перенести rollback meta-update в цепочку `1.2.0`, сохранив timestamp и идемпотентность.
- Актуализировать только документы текущего релиза `1.2.0`: Store-описание, RU/EN changelog-блок `1.2.0`, release note, публикационный checklist/report и подписи готовых скриншотов. Документы прошлых версий не переписывать.
- Включить в `1.2.0` весь подтверждённый пользовательский scope после `1.1.0`: фильтр типа складского учёта, множественные общие характеристики, массовое видео с поддерживаемыми источниками, точные подписи, progress/result modal, сброс формы после успеха и ограниченный безопасный откат последней операции.
- Скомпилировать RU/EN `.mo` из текущих `.po` и собрать `dist/masseditor-1.2.0.tar.gz` штатной командой Webasyst `php wa.php compress shop/plugins/masseditor` без `-skip test`.
- Проверить gzip/tar, единственный корень `masseditor/`, безопасные пути, обязательные `.htaccess`, metadata, distribution manifest, отсутствие dev-файлов и секретов.
- Выполнить Store compliance, `complexity-optimizer`, `secure-review-loop`, полные релевантные тесты и строгую OpenSpec validation.
- Скриншоты не изменять: пользователь подтвердил, что они уже подготовлены; проверить только состав, формат и подготовить локализованные подписи.
- Не изменять PHP/JS-бизнес-логику, Store-цену, функциональный scope и внешнее состояние Webasyst Store; загрузка и публикация не входят в change.

Используемые навыки: global `openspec`, repo-local `webasyst-release`, `webasyst-development`, `complexity-optimizer` и `secure-review-loop`.

Официальные источники:

- https://developers.webasyst.com/docs/store/webasyst-store-requirements/
- https://developers.webasyst.com/docs/store/testing-before-publication/
- https://developers.webasyst.com/docs/store/check-list/

## Capabilities

### New Capabilities

Новые capability не создаются.

### Modified Capabilities

- `release-packaging`: релизная сборка должна использовать текущую версию `1.2.0`, актуальные пары `.po/.mo` и завершаться нулевым diff с production-деревом.
- `store-release-materials`: релизный документ `1.2.0` должен согласованно перечислять фильтр типа складского учёта, множественные общие характеристики и массовое видео.

## Impact

- Production metadata/meta-updates: `lib/config/plugin.php`, `lib/updates/1.2.0/`.
- Production-локализации: `wa-apps/shop/plugins/masseditor/locale/*/LC_MESSAGES/shop_masseditor.mo`.
- Project-owned release data и документы текущего релиза в `docs/`.
- Публикационный артефакт: `dist/masseditor-1.2.0.tar.gz`.
- OpenSpec: новый change с delta specs для `release-packaging` и `store-release-materials`.
- Runtime API, модели, actions, шаблоны, JavaScript, CSS и фактическая схема БД не меняются; корректируется только версия доставки уже реализованной схемы отката.
