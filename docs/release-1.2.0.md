# Массовый редактор 1.2.0

Версия `1.2.0` объединяет все пользовательские изменения после `1.1.0`: новые операции и фильтры, более понятный процесс выполнения и ограниченный безопасный откат последней операции.

## Добавлено

- Фильтр типа складского учёта для операции «Остатки» без выбора конкретного склада: все товары, без складского учёта или со складским учётом.
- Режимы замены, добавления, удаления выбранных значений и полной очистки для множественных общих характеристик.
- Массовая установка или очистка видео. В режиме установки принимаются ссылки Rutube, VK, YouTube и Vimeo, поддерживаемые Shop-Script.
- Отдельное окно выполнения и результата массовой операции с защитой от повторной отправки.
- Безопасный откат последней успешной массовой операции текущего пользователя в течение трёх часов.

## Изменено

- После успешного применения форма операции и выбор товаров возвращаются к исходному состоянию; после ошибки введённые параметры сохраняются для исправления.
- Названия «Цены и остатки» / `Prices and stock` и «Редактирование характеристик» / `Feature editing` точнее соответствуют доступным операциям.
- Установленный плагин получает таблицу журнала и закрытые таблицы снимков через два повторно безопасных meta-update Webasyst версии `1.2.0`.

## Исправлено

- Неподдерживаемая видеоссылка больше не завершается ложным успехом: источник проверяется до записи, а результат сохранения дополнительно подтверждается сервером.
- Ошибка массовой операции не раскрывает технические детали в интерфейсе и не очищает заполненную форму.

## Безопасность

- Откат доступен только администратору Shop-Script, только для собственной глобально последней успешной операции и только после отдельного POST/CSRF-подтверждения.
- Перед восстановлением сервер повторно проверяет права на каждый товар и точное совпадение текущих данных с результатом исходной операции.
- Снимки содержат только затронутые whitelisted поля, не выводятся в журнал или JSON и восстанавливаются ограниченными батчами с аудитом.

## Ограничения версии

- Поддерживаются только общие характеристики товара; SKU- и дочерние характеристики не изменяются.
- Для списков и множественных характеристик используются существующие значения; создание новых справочных значений не входит в релиз.
- Для видео одна ссылка применяется ко всем выбранным товарам; загрузка видеофайлов и индивидуальные ссылки не поддерживаются.
- Индикатор выполнения неопределённый и не показывает точный процент.
- Откат недоступен после следующей успешной операции, через три часа, после внешнего изменения результата или повторного отката. Произвольные и более старые операции откатывать нельзя.

## Обновление установленного плагина

Webasyst автоматически выполняет два timestamp meta-update из `lib/updates/1.2.0/`. Они создают отсутствующие таблицы через `CREATE TABLE IF NOT EXISTS`, не удаляют существующие данные и безопасны при повторном запуске.

## Проверка

Автоматический релизный gate включает PHP- и JS-тесты, PHP/JS syntax, gettext, release-data validator, строгую OpenSpec-валидацию и проверку tar.gz-кандидата. Готовые RU/EN desktop/mobile скриншоты находятся в `docs/screens/1.2.0/`; загрузка архива и публикация в Webasyst Store остаются ручными действиями.

---

# Mass Editor 1.2.0

Version `1.2.0` combines every user-facing change since `1.1.0`: new operations and filters, clearer execution feedback, and a constrained safe rollback for the latest operation.

## Added

- A stock-accounting type filter for stock operations without a specific warehouse: all products, without warehouse stock accounting, or with warehouse stock accounting.
- Replace, add, remove-selected, and clear modes for multiple-value common product features.
- Bulk video URL setting and clearing. Set mode accepts Rutube, VK, YouTube, and Vimeo URLs supported by Shop-Script.
- A separate operation progress/result dialog with duplicate-submit protection.
- Safe rollback of the current user's latest successful bulk operation within three hours.

## Changed

- After success, the operation form and product selection return to their defaults; after an error, entered parameters remain available for correction.
- `Prices and stock` and `Feature editing` now describe the available operations precisely in both interface languages.
- Installed copies receive the log and private snapshot tables through two repeat-safe Webasyst `1.2.0` meta-updates.

## Fixed

- Unsupported video URLs no longer report false success: the source is checked before writing and the saved result is verified on the server.
- A failed bulk operation does not expose technical details in the interface or clear the entered form.

## Security

- Rollback is available only to a Shop-Script administrator, only for their own globally latest successful operation, and only after a separate POST/CSRF confirmation.
- Before restoration, the server rechecks rights for every product and requires current data to match the original operation result exactly.
- Snapshots contain only affected whitelisted fields, are not exposed in the log or JSON, and are restored in bounded batches with an audit entry.

## Release limitations

- Only common product features are supported; SKU-level and child features are not changed.
- Select and multiple-value operations use existing values; creating new reference values is outside this release.
- One video URL is applied to all selected products; uploads and per-product URLs are not supported.
- The progress indicator is indeterminate and does not report an exact percentage.
- Rollback is unavailable after another successful operation, after three hours, after an external result change, or after an earlier rollback. Arbitrary and older operations cannot be rolled back.

## Updating an installed copy

Webasyst automatically runs two timestamp meta-updates from `lib/updates/1.2.0/`. They create missing tables with `CREATE TABLE IF NOT EXISTS`, preserve existing data, and are safe to run repeatedly.

## Verification

The automated release gate covers PHP and JavaScript tests, PHP/JS syntax, gettext, the release-data validator, strict OpenSpec validation, and tar.gz candidate checks. Ready RU/EN desktop/mobile screenshots are stored in `docs/screens/1.2.0/`; Store upload and publication remain manual actions.
