# Описание Mass Editor для Webasyst Store

Документ хранит черновик описания плагина для карточки Webasyst Store. HTML-блоки ниже используют только базовые теги, разрешенные редактором магазина, без CSS.

## RU title

`Mass Editor для Shop-Script`

## EN title

`Mass Editor for Shop-Script`

## RU summary

Безопасное массовое редактирование каталога в бекенде Shop-Script: цены, остатки, категории, базовые характеристики, описания, теги и URL.

## EN summary

Safe Shop-Script backend catalog bulk editing: prices, stock, categories, basic features, descriptions, tags, and URLs.

## RU HTML description

```html
<p><strong>Mass Editor - помогает быстро обновлять каталог Shop-Script без ручного редактирования каждой карточки товара.</strong></p>

<p>Плагин добавляет в бекенд удобный экран для массовой работы с товарами: найдите нужные позиции фильтром, выберите отдельные товары или все найденные товары по текущему фильтру, настройте операцию и подтвердите применение изменений. Это уровень инструмента для регулярного управления каталогом за $69: цены, остатки, категории, базовые характеристики, описания, теги и URL в одном безопасном потоке.</p>

<p><strong>Возможности</strong></p>
<ul>
  <li>Массовое изменение цены и compare price: установка значения или изменение на процент.</li>
  <li>Округление цен до 1, 10 или 100 с выбором направления округления.</li>
  <li>Управление compare price при изменении основной цены: оставить без изменений, записать старую цену, очистить или рассчитать по коэффициенту.</li>
  <li>Массовое изменение остатков: установить, увеличить, уменьшить или сделать остаток бесконечным; при складском учете можно выбрать склад.</li>
  <li>Массовое изменение видимости товаров: опубликован, скрыт или неопубликован.</li>
  <li>Массовое изменение доступности артикулов: доступен или недоступен.</li>
  <li>Базовое редактирование характеристик: установить существующее значение или очистить характеристику.</li>
  <li>Массовое управление категориями: добавить в категорию, удалить из категории или заменить основную категорию.</li>
  <li>Работа с описаниями: полная замена, добавление текста в начало или в конец.</li>
  <li>Работа с тегами: добавление, удаление или замена списка тегов.</li>
  <li>Генерация URL товаров из названия или по шаблону с переменными <code>{name}</code>, <code>{id}</code>, <code>{current_url}</code>.</li>
  <li>Фильтрация товаров по названию или артикулу, категории, статусу и доступности.</li>
  <li>Выбор всех найденных товаров по текущему фильтру с серверной проверкой лимита операции.</li>
  <li>Пагинация и сохранение выбранных товаров при переходе между страницами списка.</li>
  <li>Журнал выполненных операций с количеством обработанных товаров и описанием действия.</li>
  <li>Настройки лимита товаров за одну операцию, размера страницы, срока хранения журнала, формата даты, режима темы и языка интерфейса.</li>
  <li>Русский и английский интерфейс с режимом Auto: язык выбирается по текущей локали Webasyst, также доступно ручное переключение.</li>
</ul>

<p><strong>Ограничения базового редактирования характеристик</strong></p>
<ul>
  <li>Поддерживаются только общие характеристики товара.</li>
  <li>Поддерживаются безопасные базовые типы: строка, текст, число и одиночное существующее значение из списка.</li>
  <li>SKU-характеристики, множественные значения, цвета, диапазоны, размерности и создание новых значений не входят в эту версию.</li>
</ul>

<p><strong>Как это работает</strong></p>
<ol>
  <li>Откройте "Mass Editor" в бекенде Shop-Script.</li>
  <li>Отфильтруйте каталог и отметьте товары или выберите все найденные товары по текущему фильтру.</li>
  <li>Выберите операцию: цена, compare price, остатки, видимость, доступность, описание, теги, URL, категории или базовые характеристики.</li>
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

<blockquote>Плагин работает в бекенде Shop-Script и не изменяет витрину, корзину, логику заказов, единицы измерения и оформление storefront. Фотографии, видео, cross-selling, similar products, страницы товаров и расширенные характеристики не входят в текущую версию. В режиме Auto язык интерфейса определяется по локали Webasyst.</blockquote>
```

## EN HTML description

```html
<p><strong>Mass Editor - helps update a Shop-Script catalog without opening each product card manually.</strong></p>

<p>The plugin adds a dedicated backend screen for bulk catalog management: filter products, select individual items or all products matching the current filter, configure an operation, and confirm the change. This $69-level release covers everyday catalog operations in one guarded flow: prices, stock, categories, basic features, descriptions, tags, and URLs.</p>

<p><strong>Features</strong></p>
<ul>
  <li>Bulk price and compare price editing: set a fixed value or change by percentage.</li>
  <li>Price rounding to 1, 10, or 100 with selectable rounding direction.</li>
  <li>Compare price control when changing the main price: keep unchanged, save the old price, clear it, or calculate by coefficient.</li>
  <li>Bulk stock editing: set, increase, decrease, or make stock infinite; choose a warehouse when warehouse stock accounting is used.</li>
  <li>Bulk product visibility changes: published, hidden, or unpublished.</li>
  <li>Bulk SKU availability changes: available or unavailable.</li>
  <li>Basic feature editing: set an existing value or clear a feature.</li>
  <li>Bulk category management: add to category, remove from category, or replace the main category.</li>
  <li>Description editing: replace, prepend, or append text.</li>
  <li>Tag editing: add, remove, or replace tags.</li>
  <li>Product URL generation from product names or by template with <code>{name}</code>, <code>{id}</code>, and <code>{current_url}</code> variables.</li>
  <li>Product filtering by name or SKU, category, status, and availability.</li>
  <li>Select all products matching the current filter with server-side operation limit validation.</li>
  <li>Pagination with product selection preserved across pages.</li>
  <li>Operation log with processed product count and action description.</li>
  <li>Settings for operation limit, page size, log retention, date format, theme mode, and interface language.</li>
  <li>Russian and English interface support with locale-based default language and manual language selection.</li>
</ul>

<p><strong>Basic feature editing limitations</strong></p>
<ul>
  <li>Only common product features are supported.</li>
  <li>Supported safe basic types: string, text, number, and a single existing selectable value.</li>
  <li>SKU features, multiple values, colors, ranges, dimensions, and creation of new values are not included in this version.</li>
</ul>

<p><strong>How it works</strong></p>
<ol>
  <li>Open "Mass Editor" in the Shop-Script backend.</li>
  <li>Filter the catalog and select products or select all products matching the current filter.</li>
  <li>Choose an operation: price, compare price, stock, visibility, availability, description, tags, URL, categories, or basic features.</li>
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

<blockquote>The plugin works in the Shop-Script backend and does not modify the storefront, cart, order logic, units of measurement, or storefront design. Product images, videos, cross-selling, similar products, product pages, and advanced feature editing are not included in the current version. Before a manual language is saved, the interface language is selected from the Webasyst locale.</blockquote>
```

## Metadata notes

- Версия по `plugin.php`: `1.1.0`.
- Vendor указан: `1329551`.
- Рекомендуемая цена после релиза: `$69`.
- Premium support declared: `yes`.
- Интерфейс плагина поддерживает `ru_RU` и `en_US`; режим `Auto` опирается на текущую локаль Webasyst.
- Точные минимальные требования PHP/Webasyst/Shop-Script в коде не заданы отдельным `requirements.php`, поэтому в карточке магазина их лучше не придумывать.
- Перед публикацией отдельно проверить фактическую совместимость с Webasyst 2 / UI 2.0 на целевом стенде.

## Рекомендуемые скриншоты

1. Общий экран раздела `Товары` с фильтрами, библиотекой операций и таблицей товаров.
2. Выбор всех найденных товаров по текущему фильтру и модальное подтверждение.
3. Операция остатков с обычным остатком или выбранным складом и режимом изменения.
4. Операция базового редактирования характеристик с выбранной характеристикой.
5. Операция категорий с режимом замены основной категории.
6. Журнал с записями `stock`, `features` и `categories`.
7. Настройки с лимитом операции, языком интерфейса и темой.
