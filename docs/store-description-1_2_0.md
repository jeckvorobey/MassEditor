# Описание плагина «Массовый редактор» 1.2.0 для Webasyst Store

## RU title

`Mass Editor для Shop-Script`

## RU summary

Безопасное массовое редактирование каталога в backend Shop-Script: операции с товарами, фильтры, подтверждение, журнал и ограниченный откат последнего изменения.

## RU HTML description

```html
<p><strong>«Массовый редактор» помогает обновлять выбранные товары Shop-Script без ручного открытия каждой карточки.</strong></p>

<p>Отфильтруйте каталог, выберите товары, настройте нужную операцию и подтвердите изменение. Плагин проверит запрос на сервере, обработает товары ограниченными пакетами и добавит запись в журнал.</p>

<p><strong>Цены и остатки</strong></p>
<ul>
  <li>Установка или процентное изменение цены и compare price с настраиваемым округлением.</li>
  <li>Управление compare price при изменении основной цены.</li>
  <li>Установка, увеличение, уменьшение или бесконечный остаток с выбором склада.</li>
  <li>Фильтр товаров со складским учётом и без него, когда конкретный склад не выбран.</li>
</ul>

<p><strong>Состояние и содержимое товара</strong></p>
<ul>
  <li>Массовое изменение видимости товара и доступности артикулов.</li>
  <li>Замена, добавление в начало или конец описания.</li>
  <li>Добавление, удаление и замена тегов.</li>
  <li>Генерация URL из названия или шаблона с переменными <code>{name}</code>, <code>{id}</code> и <code>{current_url}</code>.</li>
</ul>

<p><strong>Характеристики, категории и видео</strong></p>
<ul>
  <li>Редактирование общих характеристик: текст, числа, логические значения, размерности, цвета, списки, переключатели и диапазоны.</li>
  <li>Замена набора, добавление, удаление выбранных значений и очистка множественных общих характеристик.</li>
  <li>Добавление товаров в категорию, удаление из категории и замена основной категории.</li>
  <li>Установка одной поддерживаемой Shop-Script видеоссылки всем выбранным товарам или очистка ссылки.</li>
</ul>

<p><strong>Выбор и фильтры</strong></p>
<ul>
  <li>Поиск по названию или артикулу, фильтры категории, статуса, доступности и склада.</li>
  <li>Выбор текущей страницы или всех товаров текущего фильтра.</li>
  <li>Сервер заново строит полную выборку и проверяет настроенный лимит операции.</li>
</ul>

<p><strong>Контроль массовых изменений</strong></p>
<ul>
  <li>Операции доступны только администраторам Shop-Script и требуют явного подтверждения.</li>
  <li>Сервер проверяет типы, значения, права на каждый товар и допустимый размер выборки.</li>
  <li>Окно выполнения блокирует повторную отправку и показывает безопасный результат.</li>
  <li>Журнал хранит тип операции и число обработанных товаров.</li>
  <li>Последнюю собственную успешную операцию можно отменить в течение трёх часов, если после неё не было другой операции и данные не менялись извне.</li>
</ul>

<p><strong>Как это работает</strong></p>
<ol>
  <li>Откройте «Массовый редактор» в backend Shop-Script.</li>
  <li>Настройте фильтры и выберите товары.</li>
  <li>Выберите нужную операцию и заполните параметры.</li>
  <li>Проверьте действие в окне подтверждения и запустите обработку.</li>
  <li>Закройте окно результата; при необходимости откройте журнал и отмените допустимую последнюю операцию.</li>
</ol>

<p><strong>Ограничения</strong></p>
<ul>
  <li>Поддерживаются общие характеристики товара; SKU- и дочерние характеристики не изменяются.</li>
  <li>Для списков используются существующие значения; создание новых справочных значений не поддерживается.</li>
  <li>Видео поддерживает Rutube, VK, YouTube и Vimeo; загрузка файлов и разные URL для каждого товара не поддерживаются.</li>
  <li>Индикатор выполнения не показывает точный процент.</li>
  <li>Откат относится только к последней собственной операции, действует три часа и блокируется при конфликте данных.</li>
</ul>

<blockquote>Плагин работает только в backend Shop-Script и не изменяет витрину, корзину, заказы или тему магазина. Интерфейс доступен на русском и английском языках.</blockquote>
```

## EN title

`Mass Editor for Shop-Script`

## EN summary

Safe bulk catalog editing in the Shop-Script backend with operations, filters, confirmation, an audit log, and constrained rollback of the latest change.

## EN HTML description

```html
<p><strong>Mass Editor updates selected Shop-Script products without opening every product card manually.</strong></p>

<p>Filter the catalog, select products, configure the required operation, and confirm the change. The plugin validates the request on the server, processes products in bounded batches, and writes an audit log entry.</p>

<p><strong>Prices and stock</strong></p>
<ul>
  <li>Set or adjust price and compare price by percentage with configurable rounding.</li>
  <li>Control compare price while changing the main price.</li>
  <li>Set, increase, decrease, or make stock infinite with optional warehouse selection.</li>
  <li>Filter products with or without warehouse stock accounting when no specific warehouse is selected.</li>
</ul>

<p><strong>Product state and content</strong></p>
<ul>
  <li>Bulk product visibility and SKU availability changes.</li>
  <li>Replace, prepend, or append descriptions.</li>
  <li>Add, remove, or replace tags.</li>
  <li>Generate URLs from product names or templates with <code>{name}</code>, <code>{id}</code>, and <code>{current_url}</code>.</li>
</ul>

<p><strong>Features, categories, and video</strong></p>
<ul>
  <li>Edit common features including text, numbers, booleans, dimensions, colors, selectable values, radio options, and ranges.</li>
  <li>Replace, add, remove selected values, or clear multiple-value common features.</li>
  <li>Add products to a category, remove them from a category, or replace the main category.</li>
  <li>Set one Shop-Script-supported video URL for all selected products or clear it.</li>
</ul>

<p><strong>Selection and filters</strong></p>
<ul>
  <li>Search by name or SKU and filter by category, status, availability, and warehouse.</li>
  <li>Select the current page or every product matching the current filter.</li>
  <li>The server rebuilds full-filter selections and enforces the configured operation limit.</li>
</ul>

<p><strong>Bulk operation controls</strong></p>
<ul>
  <li>Operations are restricted to Shop-Script administrators and require explicit confirmation.</li>
  <li>The server validates types, values, per-product rights, and selection size.</li>
  <li>The progress dialog prevents duplicate submission and shows a safe result.</li>
  <li>The log records the operation type and processed product count.</li>
  <li>The user's latest successful operation can be rolled back within three hours if no newer operation exists and its result was not changed externally.</li>
</ul>

<p><strong>How it works</strong></p>
<ol>
  <li>Open Mass Editor in the Shop-Script backend.</li>
  <li>Configure filters and select products.</li>
  <li>Choose the required operation and fill in its settings.</li>
  <li>Review the confirmation and start processing.</li>
  <li>Close the result dialog; when needed, open the log and roll back an eligible latest operation.</li>
</ol>

<p><strong>Limitations</strong></p>
<ul>
  <li>Common product features are supported; SKU-level and child features are not changed.</li>
  <li>Select operations use existing values; creating new reference values is not supported.</li>
  <li>Video supports Rutube, VK, YouTube, and Vimeo; uploads and per-product URLs are not supported.</li>
  <li>The progress indicator does not report an exact percentage.</li>
  <li>Rollback applies only to the user's latest operation, expires after three hours, and is blocked by data conflicts.</li>
</ul>

<blockquote>The plugin works only in the Shop-Script backend and does not modify the storefront, cart, orders, or store theme. The interface is available in Russian and English.</blockquote>
```

## Проверенные metadata

- Версия: `1.2.0`.
- Vendor: `1329551`.
- Тип: backend-only plugin для Shop-Script.
- Premium support: `yes` по `lib/config/shop_support.json`.
- Локали: `ru_RU`, `en_US`.
- Установленное обновление: два timestamp meta-update `1.2.0` для журнала и закрытых rollback-таблиц.
- Минимальные версии PHP/Webasyst/Shop-Script не заявляются: точные ограничения не заданы конфигурацией продукта.
