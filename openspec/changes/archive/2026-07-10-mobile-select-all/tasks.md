## 1. Тесты

- [x] 1.1 Написать JS-тест: мобильный чекбокс `select-all-page-mobile` вызывает ту же логику что и десктопный `select-all` — отмечает все `product-checkbox` на странице и сохраняет ID в `localStorage`
- [x] 1.2 Написать JS-тест: снятие мобильного чекбокса снимает все отметки товаров и очищает `localStorage`
- [x] 1.3 Написать JS-тест: indeterminate-состояние мобильного чекбокса при частичном выборе товаров

## 2. HTML-шаблон

- [x] 2.1 Добавить в `.masseditor-table-card__actions` (Backend.html:332) чекбокс `<input type="checkbox" data-role="select-all-page-mobile">` с `<label>`, использующим i18n-ключ `select_all_page`
- [x] 2.2 Проверить что элемент корректно экранирует атрибуты через Smarty `escape`

## 3. CSS-стили

- [x] 3.1 Добавить правило скрытия мобильного чекбокса на десктопе: `.masseditor-select-all-page-mobile` с `display: none` по умолчанию
- [x] 3.2 Добавить правило отображения в блоке `@media (max-width: 1024px)`: мобильный чекбокс видим, стилизован под mobile card-based дизайн (размер шрифта, отступы, высота control)
- [x] 3.3 Проверить что существующие стили `.masseditor-table-card__actions` не ломают расположение нового элемента

## 4. JavaScript

- [x] 4.1 Добавить получение элемента `selectAllPageMobile = document.querySelector('[data-role="select-all-page-mobile"]')` в блок объявления переменных (masseditor.js:~127)
- [x] 4.2 Добавить обработчик `change` для `selectAllPageMobile`, идентичный обработчику `selectAll` (masseditor.js:997-1006)
- [x] 4.3 Обновить `updateSelectionState()` (masseditor.js:~554) — синхронизировать состояние `selectAllPageMobile` с `selectAll` (checked, indeterminate)
- [x] 4.4 Проверить что `syncCheckboxesFromSelection()` корректно работает с мобильным чекбоксом

## 5. i18n

- [x] 5.1 Проверить наличие ключа `select_all_page` в `ru_RU` и `en_US` словарях i18nService
- [x] 5.2 Проверить отображение лейбла мобильного чекбокса на обоих языках через `interface_language`

## 6. Верификация

- [x] 6.1 Запустить JS-тесты `bash tests/run-js-tests.sh` — все пройдены
- [x] 6.2 Запустить `php -l` для изменённых PHP-файлов (если затронуты)
- [ ] 6.3 Проверить визуально в Docker-стенде на mobile-width (≤1024px): чекбокс виден, работает, indeterminate корректен
- [ ] 6.4 Проверить визуально в Docker-стенде на desktop (>1024px): мобильный чекбокс скрыт, десктопный работает как раньше
