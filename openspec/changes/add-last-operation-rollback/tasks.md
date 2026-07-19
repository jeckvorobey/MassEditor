## 1. TDD и схема данных

- [x] 1.1 Добавить failing PHP-тесты схемы `shop_masseditor_rollback`/`shop_masseditor_rollback_item`, идемпотентного метаобновления и версии плагина
- [x] 1.2 Реализовать `db.php`, метаобновление и версию, затем провести узкую проверку схемы и `php -l`
- [x] 1.3 Добавить failing PHP-тесты батчевого before/after-снимка всех whitelisted типов операций, лимитов JSON и отсутствия N+1
- [x] 1.4 Реализовать модель и сервис снимков с безопасными placeholders, whitelist, DB-блокировкой и закрытым хранением items

## 2. Применение и компенсирующее восстановление

- [x] 2.1 Добавить failing PHP-тесты интеграции `MassOperationService`: снимок до/после, срок 3 часа, удаление неполного аудита и восстановление `before_state` при ошибке
- [x] 2.2 Подключить rollback service к production constructors и реализовать минимальную компенсацию без изменения соседних операций
- [x] 2.3 Добавить failing PHP-тесты eligibility, текущего пользователя, последнего log ID, срока, повторного запроса, `after_state` conflict и прав каждого товара
- [x] 2.4 Реализовать восстановление `before_state`, компенсацию к `after_state`, пересчёт остатков и безопасный rollback audit

## 3. Controller, журнал и UI

- [x] 3.1 Добавить failing PHP-тесты отдельного POST JSON controller: admin rights, integer `log_id`, `confirm_rollback=1`, безопасные ошибки и response
- [x] 3.2 Реализовать тонкий `shopMasseditorPluginBackendRollbackController` и назначение `can_rollback`/`rollback_url` в backend action
- [x] 3.3 Добавить failing PHP/JS-тесты Smarty-формы с CSRF, кнопки только у допустимой записи, modal-подтверждения, loader, блокировки повтора и same-origin fetch
- [x] 3.4 Реализовать минимальные изменения `Backend.html`, dependency-free `masseditor.js` и переиспользовать существующие стили modal/loader без несвязанных CSS-изменений
- [x] 3.5 Добавить и проверить i18n-ключи и `.po` переводы `ru_RU`/`en_US`, включая кнопку, вопрос, loader, успех, конфликт, валидацию и aria-label

## 4. Проверки и ревью

- [x] 4.1 Запустить targeted PHP/JS-тесты после каждого TDD-шага, `php -l` для изменённых PHP-файлов и `git diff --check`
- [x] 4.2 Сверить diff с официальной документацией Webasyst о CSRF, плагинах, метаобновлениях, `waModel` и Shop-Script `shopProduct`
- [x] 4.3 Применить `complexity-optimizer`, устранить подтверждённые N+1/неограниченные коллекции через TDD и повторить проверку
- [x] 4.4 Применить `secure-review-loop`, устранить подтверждённые проблемы прав, CSRF, валидации, SQL, escaping, гонок и аудита через TDD и повторить проверку
- [x] 4.5 Запустить полные `bash tests/run-php-tests.sh`, `bash tests/run-js-tests.sh` и `openspec validate --strict --all`
