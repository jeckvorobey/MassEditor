## Context

Поле видео находится в существующей backend-форме MassEditor и отправляется через общий POST/confirm-flow. До изменения клиент и сервер проверяли только абсолютный HTTP(S) URL, но `shopProduct::setData('video_url', ...)` внутри Shop-Script дополнительно вызывает `shopVideo::checkVideo()` и превращает неподдерживаемый источник в `null`. Из-за отсутствия проверки этого контракта MassEditor мог зафиксировать успешную операцию без сохранённого видео.

Evidence:

- официальный класс [`shopProduct`](https://developers.webasyst.ru/apps/shop-script/shopProduct/) документирует записываемое поле `video_url` и обязательный `save()`;
- локальный Shop-Script `/webasyst/wa-apps/shop/lib/classes/shopProduct.class.php` подтверждает вызов `shopVideo::checkVideo()` при записи `video_url`;
- локальный `/webasyst/wa-apps/shop/lib/classes/shopVideo.class.php` подтверждает Rutube, VK, YouTube и Vimeo;
- штатные `shopProdSaveVideoController` и `shopProductVideoSaveController` проверяют результат сохранения непустого URL.

Изменение проходит через `webasyst-loop`: архитектура и docs audit, OpenSpec, PHP/JS TDD, PHP security review, complexity review, secure review и строгая validation.

## Goals / Non-Goals

**Goals:**

- Не допускать ложного успеха для URL, который Shop-Script не умеет сохранить.
- Сохранять и очищать видео через документированный `shopProduct` с проверкой результата.
- До confirm modal показывать красное доступное состояние input и точный локализованный inline-текст.
- Показывать в placeholder поддерживаемые источники на языке `interface_language`.
- Сохранить серверную проверку как авторитетную независимо от JavaScript.

**Non-Goals:**

- Поддержка Yandex Video или других источников сверх `shopVideo::checkVideo()`.
- Загрузка видеофайлов, индивидуальные URL на товар, storefront-изменения.
- Новые таблицы, миграции, actions, hooks, зависимости или release-материалы.

## Decisions

1. Сервер вызывает `shopVideo::checkVideo()` до транзакции и передаёт нормализованный URL в `shopProduct`. Это повторяет реальный контракт core и не дублирует PHP-regex в плагине. Альтернатива — поддерживать собственный whitelist-regex — отклонена из-за риска расхождения с версией Shop-Script.
2. После `save()` сервис проверяет boolean-результат и ожидаемое состояние `video_url` для `set`/`clear`. При несовпадении выполняется общий rollback и успешный лог не создаётся. Это соответствует штатному контроллеру видео.
3. JavaScript содержит предварительную проверку тех же четырёх источников только для быстрого UI feedback. Серверная проверка остаётся обязательной и окончательной.
4. Inline-ошибка размещается в Smarty рядом с input, связывается через `aria-describedby`, переключает `aria-invalid` и использует plugin-prefixed CSS. Пользовательский URL выводится только через безопасные свойства input/textContent.
5. Placeholder получает отдельный ключ `video_url_placeholder` из `shopMasseditorPluginI18nService`; RU/EN значения живут в `.po`, `.mo` пересобираются проверенным `msgfmt`.
6. TDD разделён на PHP-регрессию установки/очистки/неподдерживаемого источника и JS-регрессию красного border, inline-текста, сброса состояния и локализованного placeholder.
7. Изменение не добавляет запросов к БД и обходов коллекций; риск N+1 отсутствует. Права, CSRF, confirm, batching и audit log остаются в существующем общем потоке.

## Risks / Trade-offs

- [Поддерживаемые источники изменятся в новой версии Shop-Script] → PHP всегда использует core `shopVideo::checkVideo()`; JS-regex проверяется docs audit и остаётся лишь предварительным барьером.
- [JS и сервер разойдутся] → сервер остаётся авторитетным; тесты покрывают все перечисленные сервисы и Yandex как отрицательный пример.
- [Новый текст окажется только на одном языке] → отдельные RU/EN assertions, проверка `.po` и компиляция обеих `.mo`.
- [Ошибка сохранения будет ошибочно залогирована как успех] → проверка результата находится внутри общей транзакции до `log_service->log()`.

## Migration Plan

Миграция данных не требуется. Доставка состоит из обновления PHP/JS/CSS/Smarty и gettext-каталогов. Откат выполняется возвратом этих файлов; схема и существующие значения `shop_product.video_url` не меняются.

## Open Questions

Нет.
