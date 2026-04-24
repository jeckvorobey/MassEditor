#!/bin/sh
set -e

WA_DIR="/webasyst"

if [ ! -f "$WA_DIR/index.php" ]; then
    echo "==> Клонируем Webasyst Framework..."
    git clone --depth=1 https://github.com/webasyst/webasyst-framework.git /tmp/wa_src
    cp -r /tmp/wa_src/. "$WA_DIR/"
    rm -rf /tmp/wa_src
    echo "==> Framework установлен."
fi

if [ ! -f "$WA_DIR/wa-apps/shop/lib/shopHelper.class.php" ]; then
    echo "==> Клонируем Shop-Script..."
    git clone --depth=1 https://github.com/webasyst/shop-script.git /tmp/shop_src
    cp -r /tmp/shop_src/. "$WA_DIR/wa-apps/shop/"
    rm -rf /tmp/shop_src
    echo "==> Shop-Script установлен."
fi

# Копируем example-конфиги если ещё нет реальных
for f in "$WA_DIR/wa-config/"*.example; do
    real="${f%.example}"
    [ -f "$real" ] || cp "$f" "$real"
done

# Прописываем корректное подключение к БД для Docker
cat > "$WA_DIR/wa-config/db.php" << 'EOF'
<?php
return array(
    'default' => array(
        'host'   => 'db',
        'port'   => '3306',
        'user'   => 'webasyst',
        'password' => 'secret',
        'database' => 'webasyst',
        'type'   => 'mysqli',
    ),
);
EOF

# Включаем shop в apps.php
if ! grep -q "'shop'" "$WA_DIR/wa-config/apps.php" 2>/dev/null; then
    sed -i "s/);/    'shop' => true,\n);/" "$WA_DIR/wa-config/apps.php"
fi

# Регистрируем плагин masseditor
mkdir -p "$WA_DIR/wa-config/apps/shop"
if [ ! -f "$WA_DIR/wa-config/apps/shop/plugins.php" ]; then
    cat > "$WA_DIR/wa-config/apps/shop/plugins.php" << 'EOF'
<?php
return array(
    'masseditor' => true,
);
EOF
fi

chmod -R 777 "$WA_DIR/wa-data" "$WA_DIR/wa-config" "$WA_DIR/wa-cache" 2>/dev/null || true

echo "==> Запускаем PHP built-in server на :8080..."
cp /router.php "$WA_DIR/router.php"
exec php -S 0.0.0.0:8080 -t "$WA_DIR" "$WA_DIR/router.php"
