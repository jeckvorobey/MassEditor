# Описание плагина «Массовый редактор» 1.2.0 для Webasyst Store

Документ хранит готовое описание версии `1.2.0` для карточки Webasyst Store. HTML-блоки используют только разрешённые базовые теги, без CSS и JavaScript.

## RU title

`Mass Editor для Shop-Script`

## EN title

`Mass Editor for Shop-Script`

## RU summary

Безопасное массовое редактирование товаров в бекенде Shop-Script с русским и английским интерфейсом. Также поддерживаются складские остатки, категории и общие характеристики товаров. Дополнительно доступны множественные значения общих характеристик и массовое управление видео.

## EN summary

Safe bulk product editing in the Shop-Script backend with Russian and English interface support. Warehouse stock, categories, and common product features are also supported. Multiple-value common features and bulk video management are additionally available.

## RU HTML description

```html
<p><strong>Массовый редактор - помогает быстро обновлять выбранные товары в Shop-Script без ручного редактирования каждой карточки.</strong></p>

<p>Плагин добавляет в бекенд удобный экран для массовой работы с каталогом: найдите нужные товары, выберите их в таблице, настройте операцию и подтвердите применение изменений. Это полезно при обновлении цен, подготовке сезонных правок, управлении видимостью товаров и наведении порядка в описаниях, тегах и URL.</p>

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
  <li>Массовое редактирование общих характеристик с множественными значениями: заменить набор, добавить выбранные значения, удалить только выбранные значения или очистить характеристику.</li>
  <li>Массовое редактирование видео: установить одну HTTP(S)-ссылку для выбранных товаров или очистить текущую ссылку.</li>
  <li>Массовое управление категориями: добавить в категорию, удалить из категории или заменить основную категорию.</li>
  <li>Работа с описаниями: полная замена, добавление текста в начало или в конец.</li>
  <li>Работа с тегами: добавление, удаление или замена списка тегов.</li>
  <li>Генерация URL товаров из названия или по шаблону с переменными <code>{name}</code>, <code>{id}</code>, <code>{current_url}</code>.</li>
  <li>Фильтрация товаров по названию или артикулу, категории, статусу и доступности.</li>
  <li>Выбор всех найденных товаров по текущему фильтру с серверной проверкой лимита операции.</li>
  <li>Журнал выполненных операций с количеством обработанных товаров и описанием действия.</li>
  <li>Настройки лимита товаров за одну операцию, размера страницы, срока хранения журнала, формата даты, режима темы и языка интерфейса.</li>
  <li>Русский и английский интерфейс с режимом Auto: язык выбирается по текущей локали Webasyst, также доступно ручное переключение.</li>
</ul>

<p><strong>Ограничения редактирования характеристик</strong></p>
<ul>
  <li>Поддерживаются только общие характеристики товара, включая характеристики с множественными значениями.</li>
  <li>SKU-характеристики и дочерние характеристики не поддерживаются.</li>
  <li>Для списков, переключателей и множественных характеристик используются существующие значения; создание новых значений не входит в эту версию.</li>
</ul>

<p><strong>Ограничения видео</strong></p>
<ul>
  <li>Одна ссылка на видео применяется ко всем выбранным товарам; загрузка видеофайлов и разные ссылки для каждого товара не поддерживаются.</li>
</ul>

<p><strong>Безопасность массовых изменений</strong></p>
<ul>
  <li>Операции доступны только администраторам Shop-Script.</li>
  <li>Перед применением требуется явное подтверждение.</li>
  <li>Количество товаров за один запуск ограничивается настройкой плагина.</li>
  <li>При выборе всех товаров по фильтру сервер заново строит выборку и не доверяет клиентскому счетчику.</li>
  <li>Изменения выполняются пакетами и записываются в журнал.</li>
  <li>Количество переданных значений характеристики ограничивается и проверяется на сервере.</li>
  <li>Для множественной характеристики используются только существующие значения, принадлежащие выбранной характеристике.</li>
  <li>Изменяется только выбранная общая характеристика товара; характеристики артикулов и другие характеристики не затрагиваются.</li>
  <li>Видео принимается только как корректная HTTP(S)-ссылка допустимой длины.</li>
</ul>

<p><strong>Как это работает</strong></p>
<ol>
  <li>Откройте "Mass Editor" в бекенде Shop-Script.</li>
  <li>Отфильтруйте каталог и отметьте товары, которые нужно изменить.</li>
  <li>Выберите операцию: цена, compare price, видимость, доступность, описание, теги или URL.</li>
  <li>Заполните параметры операции и проверьте выбранное действие в окне подтверждения.</li>
  <li>Подтвердите применение. Плагин выполнит изменения и добавит запись в журнал.</li>
</ol>

<blockquote>Плагин работает в бекенде Shop-Script и не изменяет витрину, корзину, логику заказов, единицы измерения и оформление storefront. Фотографии, cross-selling, similar products и страницы товаров не входят в текущую версию. В режиме Auto язык интерфейса определяется по локали Webasyst.</blockquote>
```

## EN HTML description

```html
<p><strong>Mass Editor - helps update selected Shop-Script products without opening each product card manually.</strong></p>

<p>The plugin adds a dedicated backend screen for bulk catalog management: find the required products, select them in the table, configure an operation, and confirm the change. It is useful for price updates, seasonal catalog maintenance, visibility management, and cleanup of descriptions, tags, and product URLs.</p>

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
  <li>Bulk editing of multiple-value common product features: replace the set, add selected values, remove only selected values, or clear the feature.</li>
  <li>Bulk video editing: set one HTTP(S) video URL for selected products or clear their current video URL.</li>
  <li>Bulk category management: add to category, remove from category, or replace the main category.</li>
  <li>Description editing: replace, prepend, or append text.</li>
  <li>Tag editing: add, remove, or replace tags.</li>
  <li>Product URL generation from product names or by template with <code>{name}</code>, <code>{id}</code>, and <code>{current_url}</code> variables.</li>
  <li>Product filtering by name or SKU, category, status, and availability.</li>
  <li>Select all products matching the current filter with server-side operation limit validation.</li>
  <li>Operation log with processed product count and action description.</li>
  <li>Settings for operation limit, page size, log retention, date format, theme mode, and interface language.</li>
  <li>Russian and English interface support with locale-based default language and manual language selection.</li>
</ul>

<p><strong>Feature editing limitations</strong></p>
<ul>
  <li>Only common product features are supported, including multiple-value features.</li>
  <li>SKU features and child features are not supported.</li>
  <li>Select, radio, and multiple-value operations use existing values; creating new feature values is not included in this version.</li>
</ul>

<p><strong>Video limitations</strong></p>
<ul>
  <li>One video URL is applied to all selected products; video uploads and per-product URL lists are not supported.</li>
</ul>

<p><strong>Bulk editing safeguards</strong></p>
<ul>
  <li>Operations are available only to Shop-Script administrators.</li>
  <li>Explicit confirmation is required before applying changes.</li>
  <li>The number of products per run is limited by plugin settings.</li>
  <li>When all filtered products are selected, the server rebuilds the selection and does not trust the client-side total.</li>
  <li>Changes are processed in batches and recorded in the operation log.</li>
  <li>The submitted feature value count is limited and validated on the server.</li>
  <li>Multiple-value operations accept only existing values that belong to the selected feature.</li>
  <li>Only the selected common product feature is changed; SKU-level and unrelated features remain untouched.</li>
  <li>Video input must be a valid HTTP(S) URL within the supported length.</li>
</ul>

<p><strong>How it works</strong></p>
<ol>
  <li>Open "Mass Editor" in the Shop-Script backend.</li>
  <li>Filter the catalog and select the products you want to update.</li>
  <li>Choose an operation: price, compare price, visibility, availability, description, tags, or URL.</li>
  <li>Fill in the operation settings and review the action in the confirmation dialog.</li>
  <li>Confirm the change. The plugin applies the update and writes an entry to the log.</li>
</ol>

<blockquote>The plugin works in the Shop-Script backend and does not modify the storefront, cart, order logic, units of measurement, or storefront design. Product images, cross-selling, similar products, and product pages are not included in the current version. Before a manual language is saved, the interface language is selected from the Webasyst locale.</blockquote>
```

## Metadata notes

- Версия по `plugin.php`: `1.2.0`.
- Vendor: `1329551`.
- Premium support: `yes`.
- Интерфейс: `ru_RU` и `en_US`, режим Auto использует текущую локаль Webasyst.
- Обновление установленного плагина использует официальный механизм meta-updates Webasyst; отдельная пользовательская настройка не требуется.
- Точные минимальные версии PHP, Webasyst и Shop-Script в коде не заданы, поэтому в карточке Store их нельзя указывать без отдельной подтверждённой проверки.
- Перед публикацией требуется финально проверить совместимость с целевым Webasyst 2 / UI 2.0 стендом и обновить скриншоты новых операций.

## Рекомендуемые скриншоты

1. Общий экран раздела «Товары» с фильтрами, операциями и таблицей товаров.
2. Множественная характеристика с multi-select и режимами замены, добавления, удаления и очистки.
3. Операция видео с режимом установки HTTP(S)-ссылки.
4. Модальное подтверждение массовой операции.
5. Журнал с операциями `features` и `video`.
6. Настройки лимита операции, языка и темы.
