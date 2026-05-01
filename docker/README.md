# Локальный Docker-стенд Webasyst + Shop-Script + Mass Editor

Этот каталог поднимает локальный стенд для ручной проверки плагина `masseditor`.
Репозиторий остается plugin-only: сам плагин монтируется в установленный внутри
volume Webasyst по пути:

```text
../wa-apps/shop/plugins/masseditor -> /webasyst/wa-apps/shop/plugins/masseditor
```

## Быстрый старт

```bash
cd docker
docker compose up -d --build
```

При первом запуске PHP-контейнер автоматически:

- клонирует Webasyst Framework в volume `/webasyst`;
- клонирует Shop-Script в `/webasyst/wa-apps/shop`;
- создает `/webasyst/wa-config/db.php` с доступом к MariaDB;
- включает приложение `shop` в `/webasyst/wa-config/apps.php`;
- регистрирует плагин в `/webasyst/wa-config/apps/shop/plugins.php`;
- запускает PHP built-in server на порту `8080`.

Отдельно запускать `setup.sh` для обычного сценария не нужно.

## Доступы

| Сервис | Адрес |
|--------|-------|
| Webasyst | http://localhost:8080 |
| Backend | http://localhost:8080/webasyst/ |
| Mass Editor после входа | http://localhost:8080/webasyst/shop/?plugin=masseditor&action=backend |
| MariaDB внутри compose-сети | `db:3306` |

Данные БД для мастера установки или ручной проверки:

```text
host: db
database: webasyst
user: webasyst
password: secret
root password: rootsecret
```

Если в shell настроен HTTP proxy, проверяйте локальный стенд так:

```bash
curl --noproxy '*' -I http://localhost:8080/
```

Без `--noproxy` запрос к `localhost` может уйти через внешний proxy и вернуть
ложный `502 Bad Gateway`.

## Проверка стенда

```bash
docker compose ps
docker compose logs --tail=120 php
curl --noproxy '*' -sS -o /tmp/masseditor_home.html -w '%{http_code} %{content_type}\n' http://localhost:8080/
```

Ожидаемый минимум:

- контейнеры `wa-db` и `wa-php` запущены;
- главная страница возвращает `200 text/html` или редирект на backend/login;
- `/webasyst/wa-apps/shop/plugins/masseditor` существует внутри PHP-контейнера.

Проверить bind mount плагина:

```bash
docker compose exec -T php sh -c 'test -d /webasyst/wa-apps/shop/plugins/masseditor && echo plugin-mounted'
```

Проверить таблицу логов:

```bash
docker compose exec -T db mariadb -uwebasyst -psecret webasyst -e "SHOW TABLES LIKE 'shop_masseditor_log'; DESCRIBE shop_masseditor_log;"
```

## Проверка PHP-синтаксиса

```bash
docker compose exec -T php php -l /webasyst/wa-apps/shop/plugins/masseditor/lib/actions/shopMasseditorPluginBackend.action.php
docker compose exec -T php php -l /webasyst/wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginProductSelectionService.class.php
docker compose exec -T php php -l /webasyst/wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginMassOperationService.class.php
docker compose exec -T php php -l /webasyst/wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginLogService.class.php
```

## Что важно про текущий Docker

- Compose использует `php:8.1-cli-alpine` и PHP built-in server. Это нормально для локальной разработки, но не production-схема.
- `docker/nginx.conf` сейчас не подключен в `docker-compose.yml`. Его нужно либо удалить, либо добавить отдельный `nginx` + `php-fpm` сервис, если понадобится стенд ближе к боевому хостингу.
- Webasyst и Shop-Script берутся из GitHub при первом запуске. Для воспроизводимых проверок лучше закрепить конкретные версии или теги.
- Данные Webasyst и MariaDB лежат в named volumes `webasyst` и `dbdata`.
- Плагин подключен bind mount-ом, поэтому изменения PHP/HTML/JS/CSS в репозитории видны в контейнере без rebuild.

## Остановка и очистка

```bash
docker compose down
docker compose down -v
```

`docker compose down -v` удалит установленный Webasyst, Shop-Script и базу данных.
