# Описание плагина «Массовый редактор» для Webasyst Store

Документ хранит черновик описания плагина для карточки Webasyst Store. HTML-блоки ниже используют только базовые теги, разрешенные редактором магазина, без CSS.

## RU title

`Массовый редактор для Shop-Script`

## EN title

`Mass Editor for Shop-Script`

## RU summary

Безопасное массовое редактирование каталога в бекенде Shop-Script: цены, складские остатки, категории, характеристики, описания, теги и URL.

## EN summary

Safe Shop-Script backend catalog bulk editing: prices, warehouse stock, categories, product features, descriptions, tags, and URLs.

## RU HTML description

```html
<p><strong>Массовый редактор помогает быстро обновлять каталог Shop-Script без ручного редактирования каждой карточки товара.</strong></p>

<p>Плагин добавляет в бекенд удобный экран для массовой работы с товарами: найдите нужные позиции фильтром, выберите отдельные товары или все найденные товары по текущему фильтру, настройте операцию и подтвердите применение изменений. Это инструмент для регулярного управления каталогом: цены, складские остатки, категории, характеристики, описания, теги и URL в одном безопасном потоке.</p>

<p><strong>Возможности</strong></p>
<ul>
  <li>Массовое изменение цены и compare price: установка значения или изменение на процент.</li>
  <li>Округление цен до 1, 10 или 100 с выбором направления округления.</li>
  <li>Управление compare price при изменении основной цены: оставить без изменений, записать старую цену, очистить или рассчитать по коэффициенту.</li>
  <li>Массовое изменение остатков: установить, увеличить, уменьшить или сделать остаток бесконечным; при складском учете можно выбрать конкретный склад.</li>
  <li>Фильтрация товаров по складу с отображением остатка именно выбранного склада; в режиме «Все склады» сохраняется общая детализация.</li>
  <li>Массовое изменение видимости товаров: опубликован, скрыт или неопубликован.</li>
  <li>Массовое изменение доступности артикулов: доступен или недоступен.</li>
  <li>Редактирование общих характеристик: строки, текст, числа, логические значения, размерности, цвета, списки, переключатели и диапазоны.</li>
  <li>Массовое управление категориями: добавить в категорию, удалить из категории или заменить основную категорию.</li>
  <li>Работа с описаниями: полная замена, добавление текста в начало или в конец.</li>
  <li>Работа с тегами: добавление, удаление или замена списка тегов.</li>
  <li>Генерация URL товаров из названия или по шаблону с переменными <code>{name}</code>, <code>{id}</code>, <code>{current_url}</code>.</li>
  <li>Фильтрация товаров по названию или артикулу, категории, статусу, доступности и складу.</li>
  <li>Выбор всех найденных товаров по текущему фильтру с серверной проверкой лимита операции.</li>
  <li>Выбор всех товаров текущей страницы на мобильных устройствах.</li>
  <li>Пагинация и сохранение выбранных товаров при переходе между страницами списка.</li>
  <li>Журнал выполненных операций с количеством обработанных товаров и описанием действия.</li>
  <li>Настройки лимита товаров за одну операцию, размера страницы, срока хранения журнала, формата даты, режима темы и языка интерфейса.</li>
  <li>Русский и английский интерфейс с режимом Auto: язык выбирается по текущей локали Webasyst, также доступно ручное переключение.</li>
</ul>

<p><strong>Ограничения редактирования характеристик</strong></p>
<ul>
  <li>Поддерживаются только общие характеристики товара.</li>
  <li>SKU-характеристики, дочерние и множественные характеристики не поддерживаются.</li>
  <li>Для списков и переключателей используются существующие значения; создание новых значений не входит в эту версию.</li>
</ul>

<p><strong>Как это работает</strong></p>
<ol>
  <li>Откройте "Массовый редактор" в бекенде Shop-Script.</li>
  <li>Отфильтруйте каталог и отметьте товары или выберите все найденные товары по текущему фильтру.</li>
  <li>Выберите операцию: цена, compare price, остатки, видимость, доступность, описание, теги, URL, категории или характеристики.</li>
  <li>Заполните параметры операции и проверьте выбранное действие в окне подтверждения.</li>
  <li>Подтвердите применение. Плагин выполнит изменения и добавит запись в журнал.</li>
</ol>

<p><strong>Безопасность массовых изменений</strong></p>
<ul>
  <li>Операции доступны только администраторам Shop-Script.</li>
  <li>Перед применением требуется явное подтверждение.</li>
  <li>Количество товаров за один запуск ограничивается настройкой плагина.</li>
  <li>При выборе всех товаров по фильтру сервер заново строит выборку и не доверяет клиентскому счетчику.</li>
  <li>Изменения выполняются пакетами и записываются в журнал.</li>
</ul>

<blockquote>Плагин работает в бекенде Shop-Script и не изменяет витрину, корзину, логику заказов, единицы измерения и оформление storefront. Фотографии, видео, cross-selling, similar products и страницы товаров не входят в текущую версию. В режиме Auto язык интерфейса определяется по локали Webasyst.</blockquote>
```

## EN HTML description

```html
<p><strong>Mass Editor - helps update a Shop-Script catalog without opening each product card manually.</strong></p>

<p>The plugin adds a dedicated backend screen for bulk catalog management: filter products, select individual items or all products matching the current filter, configure an operation, and confirm the change. This is a tool for regular catalog management: prices, inventory levels, categories, features, descriptions, tags, and URLs in one secure stream.</p>

<p><strong>Features</strong></p>
<ul>
  <li>Bulk price and compare price editing: set a fixed value or change by percentage.</li>
  <li>Price rounding to 1, 10, or 100 with selectable rounding direction.</li>
  <li>Compare price control when changing the main price: keep unchanged, save the old price, clear it, or calculate by coefficient.</li>
  <li>Bulk stock editing: set, increase, decrease, or make stock infinite; choose a specific warehouse when warehouse stock accounting is used.</li>
  <li>Product filtering by warehouse with the selected warehouse stock shown in the product list; the all-warehouses mode keeps the full breakdown.</li>
  <li>Bulk product visibility changes: published, hidden, or unpublished.</li>
  <li>Bulk SKU availability changes: available or unavailable.</li>
  <li>Common product feature editing for strings, text, numbers, boolean values, dimensions, colors, selectable values, radio options, and ranges.</li>
  <li>Bulk category management: add to category, remove from category, or replace the main category.</li>
  <li>Description editing: replace, prepend, or append text.</li>
  <li>Tag editing: add, remove, or replace tags.</li>
  <li>Product URL generation from product names or by template with <code>{name}</code>, <code>{id}</code>, and <code>{current_url}</code> variables.</li>
  <li>Product filtering by name or SKU, category, status, availability, and warehouse.</li>
  <li>Select all products matching the current filter with server-side operation limit validation.</li>
  <li>Select all products on the current page on mobile devices.</li>
  <li>Pagination with product selection preserved across pages.</li>
  <li>Operation log with processed product count and action description.</li>
  <li>Settings for operation limit, page size, log retention, date format, theme mode, and interface language.</li>
  <li>Russian and English interface support with locale-based default language and manual language selection.</li>
</ul>

<p><strong>Feature editing limitations</strong></p>
<ul>
  <li>Only common product features are supported.</li>
  <li>SKU features, child features, and multiple-value features are not supported.</li>
  <li>Select and radio operations use existing values; creating new feature values is not included in this version.</li>
</ul>

<p><strong>How it works</strong></p>
<ol>
  <li>Open "Mass Editor" in the Shop-Script backend.</li>
  <li>Filter the catalog and select products or select all products matching the current filter.</li>
  <li>Choose an operation: price, compare price, stock, visibility, availability, description, tags, URL, categories, or product features.</li>
  <li>Fill in the operation settings and review the action in the confirmation dialog.</li>
  <li>Confirm the change. The plugin applies the update and writes an entry to the log.</li>
</ol>

<p><strong>Bulk editing safeguards</strong></p>
<ul>
  <li>Operations are available only to Shop-Script administrators.</li>
  <li>Explicit confirmation is required before applying changes.</li>
  <li>The number of products per run is limited by plugin settings.</li>
  <li>When all filtered products are selected, the server rebuilds the selection and does not trust the client-side total.</li>
  <li>Changes are processed in batches and recorded in the operation log.</li>
</ul>

<blockquote>The plugin works in the Shop-Script backend and does not modify the storefront, cart, order logic, units of measurement, or storefront design. Product images, videos, cross-selling, similar products, and product pages are not included in the current version. Before a manual language is saved, the interface language is selected from the Webasyst locale.</blockquote>
```

## Metadata notes

- Версия по `plugin.php`: `1.1.0`.
- Vendor указан: `1329551`.
- Рекомендуемая цена после релиза: `$69`.
- Premium support declared: `yes`.
- Интерфейс плагина поддерживает `ru_RU` и `en_US`; режим `Auto` опирается на текущую локаль Webasyst.
- Поддерживаются общие характеристики товара типов string/text/number, boolean, dimension, color, select/radio и range; SKU-, дочерние и множественные характеристики не поддерживаются.
- Точные минимальные требования PHP/Webasyst/Shop-Script в коде не заданы отдельным `requirements.php`, поэтому в карточке магазина их лучше не придумывать.
- Перед публикацией отдельно проверить фактическую совместимость с Webasyst 2 / UI 2.0 на целевом стенде.

## Рекомендуемые скриншоты

1. Общий экран раздела `Товары` с фильтрами, библиотекой операций и таблицей товаров.
2. Выбор всех найденных товаров по текущему фильтру и модальное подтверждение.
3. Операция остатков с обычным остатком или выбранным складом и режимом изменения.
4. Операция редактирования характеристик с примерами разных типов полей.
5. Операция категорий с режимом замены основной категории.
6. Журнал с записями `stock`, `features` и `categories`.
7. Мобильный экран с выбором всех товаров текущей страницы.
8. Настройки с лимитом операции, языком интерфейса и темой.
