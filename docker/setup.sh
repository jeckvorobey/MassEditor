#!/usr/bin/env bash
# Выставляет права после ручной распаковки Webasyst в volume
WA_DIR="/var/www/webasyst"

echo "==> Проверяем наличие Webasyst..."
if [ ! -f "$WA_DIR/wa.php" ]; then
    echo "ОШИБКА: $WA_DIR/wa.php не найден."
    echo "Скачайте Webasyst вручную с https://www.webasyst.ru/download/"
    echo "и распакуйте в контейнер:"
    echo "  docker cp webasyst.zip <php-container>:/tmp/webasyst.zip"
    echo "  docker compose exec php sh -c 'unzip -q /tmp/webasyst.zip -d /tmp/wa && cp -r /tmp/wa/*/. $WA_DIR/'"
    exit 1
fi
echo "    Webasyst найден."

echo "==> Выставляем права..."
chown -R www-data:www-data "$WA_DIR" 2>/dev/null || chown -R nobody:nobody "$WA_DIR"
chmod -R 755 "$WA_DIR"
chmod -R 777 "$WA_DIR/wa-data" "$WA_DIR/wa-config" "$WA_DIR/wa-cache" 2>/dev/null || true

echo ""
echo "=========================================================="
echo "  Откройте http://localhost:8080 для установки Webasyst."
echo "  Данные БД:"
echo "    Host    : db"
echo "    Database: webasyst"
echo "    User    : webasyst"
echo "    Password: secret"
echo "=========================================================="
echo ""
echo "  После установки Webasyst + Shop-Script:"
echo "  1. Зайдите в Shop-Script → Настройки → Плагины."
echo "  2. Установите плагин 'Mass Editor' (он уже в /wa-apps/shop/plugins/masseditor)."
echo "=========================================================="
