## Purpose

Зафиксировать базовую архитектуру backend-only плагина Shop-Script `masseditor`, чтобы будущие изменения сохраняли структуру Webasyst, naming и границы плагина.

## Requirements

### Requirement: Структура плагина Webasyst
Плагин MUST оставаться в директории `wa-apps/shop/plugins/masseditor/` и использовать структуру Webasyst/Shop-Script: `lib/actions/`, `lib/classes/`, `lib/models/`, `lib/config/`, `templates/`, `js/`, `css/`, `img/`, `locale/`.

#### Scenario: Добавление backend-логики
- **WHEN** добавляется новый backend action, controller, сервис или модель
- **THEN** файл MUST размещаться в соответствующей директории плагина и использовать префикс класса `shopMasseditorPlugin...`

#### Scenario: Расширение общей логики
- **WHEN** логика переиспользуется между action/controller или становится сложной
- **THEN** она MUST быть вынесена в `lib/classes/`, а action MUST оставаться тонким

### Requirement: Регистрация и меню плагина
Плагин MUST регистрироваться через `lib/config/plugin.php`, основной класс `shopMasseditorPlugin extends shopPlugin` и подтвержденные Shop-Script hooks `backend_menu` и `backend_extended_menu`.

#### Scenario: Ссылка в backend-меню
- **WHEN** плагин строит ссылку backend-экрана
- **THEN** ссылка MUST формироваться через `wa()->getAppUrl('shop')` и `?plugin=masseditor`, без hardcoded `/webasyst/`

#### Scenario: HTML меню
- **WHEN** hook возвращает HTML или параметры меню
- **THEN** видимые значения MUST быть локализованы и экранированы перед вставкой в HTML

### Requirement: Конфигурация, настройки и база данных
Плагин MUST хранить метаданные в `lib/config/plugin.php`, настройки в `lib/config/settings.php`, собственную схему БД в `lib/config/db.php`, установку и удаление в `install.php`/`uninstall.php`.

#### Scenario: Добавление настройки
- **WHEN** появляется новая настройка плагина
- **THEN** она MUST иметь безопасное значение по умолчанию, локализованные `title`/`description`, серверную нормализацию и тест на ожидаемое поведение

#### Scenario: Добавление собственной таблицы
- **WHEN** плагину нужна новая собственная таблица
- **THEN** имя таблицы MUST начинаться с `shop_masseditor_`, схема MUST быть описана в `db.php`, а обновление уже установленного плагина MUST учитывать meta/update-скрипты

### Requirement: Backend-only границы
Плагин MUST менять только backend-поведение массового редактирования товаров и не MUST изменять storefront, корзину, заказы, темы дизайна или core-файлы Webasyst/Shop-Script без отдельного OpenSpec change и явного scope.

#### Scenario: Запрос вне backend-only области
- **WHEN** новая задача затрагивает storefront, заказы, тему магазина или core-файлы
- **THEN** изменение MUST быть вынесено в отдельное предложение с явным подтверждением scope

### Requirement: Штатные meta-updates Webasyst
Обновление установленного плагина MUST использовать только официальный механизм meta-updates Webasyst из `lib/updates/` и MUST NOT создавать собственную таблицу последовательности миграций.

#### Scenario: Имя и расположение update-файла
- **WHEN** релиз `1.2.0` требует доведения схемы установленного плагина
- **THEN** PHP-файл MUST находиться в `lib/updates/` или её поддиректории и MUST иметь имя в виде UNIX timestamp позднее предыдущего релиза

#### Scenario: Повторное выполнение
- **WHEN** один и тот же meta-update запускается повторно
- **THEN** он MUST завершиться без ошибки, потери данных и повторного destructive-изменения

#### Scenario: Новая установка
- **WHEN** плагин `1.2.0` устанавливается впервые
- **THEN** полная актуальная схема собственной таблицы MUST создаваться из `lib/config/db.php`, а meta-update MUST оставаться безопасным

#### Scenario: Последовательность обновлений
- **WHEN** Webasyst запускает обновление плагина
- **THEN** последовательность MUST определяться штатным `wa_app_settings.update_time`, без `shop_masseditor_migrations`

### Requirement: Идентичность релиза 1.2.0
Плагин MUST публиковаться с ID `masseditor` и версией `1.2.0`; отменённый идентификатор MUST отсутствовать в текущих файлах релиза.

#### Scenario: Проверка дерева релиза
- **WHEN** выполняется поиск по отслеживаемым файлам плагина, Docker bootstrap и релизным материалам
- **THEN** отменённый идентификатор MUST отсутствовать

#### Scenario: Отсутствие legacy-миграции
- **WHEN** выполняется обновление до `1.2.0`
- **THEN** meta-update MUST NOT искать, переносить или удалять данные отменённого ID, поскольку эта версия не устанавливалась пользователями
