# Локальный стенд Webasyst + Shop-Script + MassEditor

## Быстрый старт

```bash
cd docker

# 1. Поднять контейнеры
docker compose up -d --build

# 2. Запустить скрипт первоначальной установки (единожды)
docker compose exec php sh /var/www/webasyst/wa-apps/shop/plugins/masseditor/../../../../../docker/setup.sh
# (или проще:)
docker compose exec php sh /app/setup.sh
```

> Проще: скрипт `setup.sh` смонтирован через volume, запускайте его напрямую из хоста:
> ```bash
> docker compose exec php sh -c "bash /var/www/webasyst/docker/setup.sh"
> ```

## Доступы

| Сервис    | URL / хост        |
|-----------|-------------------|
| Webasyst  | http://localhost:8080 |
| MySQL     | localhost:3306 (только внутри сети) |

БД: `webasyst` / `webasyst` / `secret`

## Структура bind mount

Плагин подключён напрямую из репозитория:
```
../wa-apps/shop/plugins/masseditor → /var/www/webasyst/wa-apps/shop/plugins/masseditor
```
Изменения в коде плагина сразу доступны без перезапуска контейнеров.

## Проверка PHP-синтаксиса

```bash
docker compose exec php php -l /var/www/webasyst/wa-apps/shop/plugins/masseditor/lib/actions/shopMasseditorPluginBackend.action.php
docker compose exec php php -l /var/www/webasyst/wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginProductSelectionService.class.php
docker compose exec php php -l /var/www/webasyst/wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginMassOperationService.class.php
docker compose exec php php -l /var/www/webasyst/wa-apps/shop/plugins/masseditor/lib/classes/shopMasseditorPluginLogService.class.php
```

## Остановка / очистка

```bash
docker compose down          # остановить (данные сохраняются)
docker compose down -v       # остановить + удалить volumes
```
