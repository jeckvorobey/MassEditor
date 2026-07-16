## 1. Серверная TDD-регрессия видео

- [x] 1.1 Добавить PHP-тесты установки, очистки и отклонения Yandex URL до транзакции; подтвердить падение нового отрицательного сценария до реализации.
- [x] 1.2 Реализовать проверку через `shopVideo::checkVideo()` и постусловие `shopProduct::save()` без изменения общего confirm/log-потока.
- [x] 1.3 Запустить целевые PHP-тесты и `php -l` для изменённых PHP-файлов.

## 2. Клиентская TDD-валидация

- [x] 2.1 Добавить JS-тест неподдерживаемого URL: закрытый modal, красная рамка, `aria-invalid`, inline-текст и сброс ошибки; подтвердить падение до реализации.
- [x] 2.2 Реализовать предварительный whitelist Rutube/VK/YouTube/Vimeo, доступный inline-error и plugin-prefixed CSS.
- [x] 2.3 Проверить поддерживаемые URL, режим `clear`, JS syntax и целевые JS-тесты.

## 3. Локализованная подсказка input

- [x] 3.1 Добавить падающие RU/EN тесты i18n-ключа `video_url_placeholder` и его использования в Smarty placeholder.
- [x] 3.2 Добавить RU/EN gettext-строки «Ссылка на видео с Rutube, VK, YouTube или Vimeo» / «Rutube, VK, YouTube, or Vimeo video URL» и вывести ключ в input.
- [x] 3.3 Скомпилировать обе `.mo` через проверенный `msgfmt --check` и запустить целевые PHP/JS-тесты.

## 4. Документационная и безопасностная проверка

- [x] 4.1 Применить `webasyst-plugin-docs-auditor` к `shopProduct`, `shopVideo`, Smarty/JS/i18n и зафиксировать evidence без неподтверждённых API.
- [x] 4.2 Применить `php-security-reviewer` к mass mutation: права, CSRF, server-side validation, confirm, rollback и audit log.
- [x] 4.3 Применить `complexity-optimizer` к текущему diff и связанным runtime-файлам; проверить отсутствие N+1 и render churn.
- [x] 4.4 Применить `secure-review-loop` после complexity review и исправить подтверждённые замечания через TDD.

## 5. Финальная валидация

- [x] 5.1 Запустить полные PHP/JS suites, PHP/JS syntax, gettext checks и `git diff --check`.
- [x] 5.2 Запустить `openspec validate fix-video-url-validation --strict` и `openspec validate --strict --all`.
