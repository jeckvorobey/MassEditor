# Описание Mass Editor для Webasyst Store

Документ хранит черновик описания плагина для карточки Webasyst Store. HTML-блоки ниже используют только базовые теги, разрешенные редактором магазина, без CSS.

## RU title

`Mass Editor для Shop-Script`

## EN title

`Mass Editor for Shop-Script`

## RU summary

Безопасное массовое редактирование цен, видимости, доступности, описаний, тегов и URL товаров в бекенде Shop-Script.

## EN summary

Safe bulk editing for product prices, visibility, availability, descriptions, tags, and URLs in the Shop-Script backend.

## RU HTML description

```html
<p><strong>Mass Editor помогает быстро обновлять выбранные товары в Shop-Script без ручного редактирования каждой карточки.</strong></p>

<p>Плагин добавляет в бекенд удобный экран для массовой работы с каталогом: найдите нужные товары, выберите их в таблице, настройте операцию и подтвердите применение изменений. Это полезно при обновлении цен, подготовке сезонных правок, управлении видимостью товаров и наведении порядка в описаниях, тегах и URL.</p>

<p><strong>Возможности</strong></p>
<ul>
  <li>Массовое изменение цены и compare price: установка значения или изменение на процент.</li>
  <li>Округление цен до 1, 10 или 100 с выбором направления округления.</li>
  <li>Управление compare price при изменении основной цены: оставить без изменений, записать старую цену, очистить или рассчитать по коэффициенту.</li>
  <li>Массовое изменение видимости товаров: опубликован, скрыт или неопубликован.</li>
  <li>Массовое изменение доступности артикулов: доступен или недоступен.</li>
  <li>Работа с описаниями: полная замена, добавление текста в начало или в конец.</li>
  <li>Работа с тегами: добавление, удаление или замена списка тегов.</li>
  <li>Генерация URL товаров из названия или по шаблону с переменными <code>{name}</code>, <code>{id}</code>, <code>{current_url}</code>.</li>
  <li>Фильтрация товаров по названию или артикулу, категории, статусу и доступности.</li>
  <li>Пагинация и сохранение выбранных товаров при переходе между страницами списка.</li>
  <li>Журнал выполненных операций с количеством обработанных товаров и описанием действия.</li>
  <li>Настройки лимита товаров за одну операцию, размера страницы, срока хранения журнала, формата даты и режима темы.</li>
</ul>

<p><strong>Как это работает</strong></p>
<ol>
  <li>Откройте Mass Editor в бекенде Shop-Script.</li>
  <li>Отфильтруйте каталог и отметьте товары, которые нужно изменить.</li>
  <li>Выберите операцию: цена, compare price, видимость, доступность, описание, теги или URL.</li>
  <li>Заполните параметры операции и проверьте выбранное действие в окне подтверждения.</li>
  <li>Подтвердите применение. Плагин выполнит изменения и добавит запись в журнал.</li>
</ol>

<p><strong>Безопасность массовых изменений</strong></p>
<ul>
  <li>Операции доступны только администраторам Shop-Script.</li>
  <li>Перед применением требуется явное подтверждение.</li>
  <li>Количество товаров за один запуск ограничивается настройкой плагина.</li>
  <li>Изменения выполняются пакетами и записываются в журнал.</li>
</ul>

<blockquote>Плагин работает в бекенде Shop-Script и не изменяет витрину, корзину, логику заказов, единицы измерения и оформление storefront. Массовое редактирование характеристик, фотографий, видео, cross-selling, similar products и страниц товаров в текущей версии не выполняется.</blockquote>
```

## EN HTML description

```html
<p><strong>Mass Editor helps update selected Shop-Script products without opening each product card manually.</strong></p>

<p>The plugin adds a dedicated backend screen for bulk catalog management: find the required products, select them in the table, configure an operation, and confirm the change. It is useful for price updates, seasonal catalog maintenance, visibility management, and cleanup of descriptions, tags, and product URLs.</p>

<p><strong>Features</strong></p>
<ul>
  <li>Bulk price and compare price editing: set a fixed value or change by percentage.</li>
  <li>Price rounding to 1, 10, or 100 with selectable rounding direction.</li>
  <li>Compare price control when changing the main price: keep unchanged, save the old price, clear it, or calculate by coefficient.</li>
  <li>Bulk product visibility changes: published, hidden, or unpublished.</li>
  <li>Bulk SKU availability changes: available or unavailable.</li>
  <li>Description editing: replace, prepend, or append text.</li>
  <li>Tag editing: add, remove, or replace tags.</li>
  <li>Product URL generation from product names or by template with <code>{name}</code>, <code>{id}</code>, and <code>{current_url}</code> variables.</li>
  <li>Product filtering by name or SKU, category, status, and availability.</li>
  <li>Pagination with product selection preserved across pages.</li>
  <li>Operation log with processed product count and action description.</li>
  <li>Settings for operation limit, page size, log retention, date format, and theme mode.</li>
</ul>

<p><strong>How it works</strong></p>
<ol>
  <li>Open Mass Editor in the Shop-Script backend.</li>
  <li>Filter the catalog and select the products you want to update.</li>
  <li>Choose an operation: price, compare price, visibility, availability, description, tags, or URL.</li>
  <li>Fill in the operation settings and review the action in the confirmation dialog.</li>
  <li>Confirm the change. The plugin applies the update and writes an entry to the log.</li>
</ol>

<p><strong>Bulk editing safeguards</strong></p>
<ul>
  <li>Operations are available only to Shop-Script administrators.</li>
  <li>Explicit confirmation is required before applying changes.</li>
  <li>The number of products per run is limited by plugin settings.</li>
  <li>Changes are processed in batches and recorded in the operation log.</li>
</ul>

<blockquote>The plugin works in the Shop-Script backend and does not modify the storefront, cart, order logic, units of measurement, or storefront design. Bulk editing of features, product images, videos, cross-selling, similar products, and product pages is not performed in the current version.</blockquote>
```

## Metadata notes

- Версия по `plugin.php`: `1.0.0`.
- Vendor указан: `1329551`.
- Premium support declared: `yes`.
- Точные минимальные требования PHP/Webasyst/Shop-Script в коде не заданы отдельным `requirements.php`, поэтому в карточке магазина их лучше не придумывать.
- Перед публикацией отдельно проверить фактическую совместимость с Webasyst 2 / UI 2.0 на целевом стенде.
