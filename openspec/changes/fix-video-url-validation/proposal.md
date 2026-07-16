## Why

MassEditor принимал любой HTTP(S) URL как допустимую видеоссылку, хотя Shop-Script сохраняет только ссылки Rutube, VK, YouTube и Vimeo. В результате неподдерживаемая ссылка могла завершить массовую операцию как успешную, но не появиться у товара; UI также не объяснял допустимые источники до подтверждения.

## What Changes

- Проверять видеоссылку штатным `shopVideo::checkVideo()` до начала транзакции и не создавать успешный журнал для неподдерживаемого источника.
- После `shopProduct::save()` проверять, что установка или очистка видео действительно завершилась ожидаемым состоянием.
- Для неподдерживаемого URL показывать красную рамку input и локализованный inline-текст под полем до открытия confirm modal.
- Убирать inline-ошибку после изменения URL и при переходе в режим `clear`.
- Добавить в placeholder поля локализованную подсказку о поддержке Rutube, VK, YouTube и Vimeo.
- Добавить RU/EN gettext-строки и TDD-регрессии для серверного и клиентского поведения.
- Не добавлять поддержку Yandex Video, загрузку видеофайлов, отдельные ссылки для разных товаров или изменение storefront.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `mass-operations`: операция `video` должна отклонять источники, которые не поддерживает Shop-Script, и подтверждать фактический результат установки/очистки.
- `backend-ui-i18n`: форма видео должна заранее перечислять поддерживаемые сервисы и показывать доступную локализованную inline-ошибку с красным состоянием поля.

## Impact

- Runtime: `shopMasseditorPluginMassOperationService`, `masseditor.js`, `masseditor.css`, backend Smarty template.
- Локализация: `shopMasseditorPluginI18nService`, RU/EN `.po` и скомпилированные `.mo`.
- Тесты: PHP service tests, JS DOM harness и UI tests.
- Данные и схема БД не меняются; используются существующие `shop_product.video_url`, общий confirm-flow и `shop_masseditor_log`.
- Документационная база: официальный `shopProduct` и локально подтверждённые `shopVideo::checkVideo()` и штатные контроллеры сохранения видео Shop-Script.
- Цикл навыков: `webasyst-loop` → `webasyst-shop-script-plugin-architect` → `webasyst-plugin-docs-auditor` → `openspec-propose`/`openspec-apply-change` → `php-tdd-developer` и `php-security-reviewer` → `complexity-optimizer` → `secure-review-loop`.
