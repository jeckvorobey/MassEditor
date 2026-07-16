## Why

Текущий `dist/masseditor-1.2.0.tar.gz` не совпадает с production-деревом плагина, а бинарные каталоги переводов `.mo` отстают от исходных `.po`. Перед передачей архива в Webasyst Store нужно получить воспроизводимый кандидат с актуальным кодом и согласованными материалами релиза `1.2.0`.

## What Changes

- Зафиксировать красные проверки: существующий архив отличается от `wa-apps/shop/plugins/masseditor/`, а текущие RU/EN `.mo` не воспроизводятся из соответствующих `.po`.
- Актуализировать `docs/release-1.2.0.md`, добавив уже реализованные массовое видео и режимы множественных общих характеристик без расширения заявленного scope.
- Скомпилировать RU/EN `.mo` проверенным gettext-совместимым `msgfmt` из текущих `.po`.
- Собрать `dist/masseditor-1.2.0.tar.gz` только из production-дерева с единственной корневой директорией `masseditor/`.
- Проверить gzip/tar, безопасные пути, обязательные `.htaccess`, metadata, отсутствие dev-файлов и нулевой рекурсивный diff после распаковки.
- Выполнить Store compliance, `complexity-optimizer`, `secure-review-loop`, полные релевантные тесты и строгую OpenSpec validation.
- Скриншоты не изменять: пользователь подтвердил, что они уже подготовлены.
- Не изменять PHP/JS-бизнес-логику, Store-цену, функциональный scope и внешнее состояние Webasyst Store; загрузка и публикация не входят в change.

Используемые навыки: `webasyst-loop`, `openspec-propose`, `openspec-apply-change`, `webasyst-store-compliance-reviewer`, `complexity-optimizer`, `secure-review-loop`.

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

- Production-локализации: `wa-apps/shop/plugins/masseditor/locale/*/LC_MESSAGES/shop_masseditor.mo`.
- Релизный документ: `docs/release-1.2.0.md`.
- Публикационный артефакт: `dist/masseditor-1.2.0.tar.gz`.
- OpenSpec: новый change с delta specs для `release-packaging` и `store-release-materials`.
- Runtime API, модели, actions, шаблоны, JavaScript, CSS и схема БД не меняются.
