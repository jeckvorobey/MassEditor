## ADDED Requirements

### REQ-EXTENDED-MENU-1: Отображение в расширенном меню бэкенда

Плагин регистрирует хук `backend_extended_menu` и добавляет пункт «Массовый редактор» в навигацию бэкенда Shop-Script.

**Acceptance criteria:**
- Хук `backend_extended_menu` зарегистрирован в `plugin.php`
- Метод `backendExtendedMenu(&$params)` добавляет элемент в `$params['menu']`
- Пункт содержит `name` (имя плагина), `icon` (fa-edit), `url` и `placement => 'body'`
- URL генерируется через `getBackendUrl()`
- Название локализуется через `$this->getName()`

### REQ-EXTENDED-MENU-2: Общий метод генерации URL

URL бэкенда генерируется через приватный метод `getBackendUrl()`, используемый как в `backendMenu()`, так и в `backendExtendedMenu()`.

**Acceptance criteria:**
- `getBackendUrl()` возвращает `wa()->getAppUrl('shop') . '?plugin=' . $this->getId()`
- `backendMenu()` использует `getBackendUrl()` вместо прямой конкатенации
- Оба метода экранируют HTML через `htmlspecialchars()`
