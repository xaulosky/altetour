#!/bin/bash
# Instalación / actualización de altetour.cl en el VPS. Idempotente.
# Las claves se generan aquí y solo quedan en config.local.php (no se imprimen).
set -euo pipefail
BASE=/var/www/altetour
PKG=/tmp/altetour-deploy.tgz
POOL=/etc/php/8.4/fpm/pool.d/altetour.conf

# 1. Usuario de sistema para PHP de este sitio.
id altetour >/dev/null 2>&1 || useradd --system --no-create-home --shell /usr/sbin/nologin altetour

# 2. Archivos. public_html se reemplaza completo; altetour-app conserva config.local.php y storage.
mkdir -p "$BASE"
TMP=$(mktemp -d)
tar -xzf "$PKG" -C "$TMP"
rm -rf "$BASE/public_html.new"
mv "$TMP/public_html" "$BASE/public_html.new"
[ -d "$BASE/public_html" ] && mv "$BASE/public_html" "$BASE/public_html.old"
mv "$BASE/public_html.new" "$BASE/public_html"
rm -rf "$BASE/public_html.old"
mkdir -p "$BASE/altetour-app"
for d in src bin config; do
  mkdir -p "$BASE/altetour-app/$d"
  # Copia todo lo nuevo sin borrar config.local.php.
  cp -a "$TMP/altetour-app/$d/." "$BASE/altetour-app/$d/"
done
mkdir -p "$BASE/altetour-app/storage"
rm -rf "$TMP"
chown -R root:root "$BASE/public_html" "$BASE/altetour-app/src" "$BASE/altetour-app/bin"
find "$BASE/altetour-app/config" -type f ! -name config.local.php -exec chown root:root {} +
find "$BASE/public_html" -type d -exec chmod 755 {} +; find "$BASE/public_html" -type f -exec chmod 644 {} +
chown root:altetour "$BASE/altetour-app" "$BASE/altetour-app/config"; chmod 750 "$BASE/altetour-app" "$BASE/altetour-app/config"
chmod -R a+rX,go-w "$BASE/altetour-app/src" "$BASE/altetour-app/bin"
chown -R altetour:altetour "$BASE/altetour-app/storage"; chmod 700 "$BASE/altetour-app/storage"
chmod 755 "$BASE"

# 3. Base de datos y configuración (solo la primera vez).
CFG="$BASE/altetour-app/config/config.local.php"
if [ ! -f "$CFG" ]; then
  DBPASS=$(openssl rand -hex 24); APPKEY=$(openssl rand -hex 32)
  mysql <<SQL
CREATE DATABASE IF NOT EXISTS altetour CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'altetour'@'localhost' IDENTIFIED BY '$DBPASS';
ALTER USER 'altetour'@'localhost' IDENTIFIED BY '$DBPASS';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX ON altetour.* TO 'altetour'@'localhost';
FLUSH PRIVILEGES;
SQL
  umask 027
  cat > "$CFG" <<PHP
<?php
declare(strict_types=1);
// Generado en el servidor. No versionar ni copiar fuera del VPS.
return [
    'environment' => 'production',
    'app_key' => '$APPKEY',
    'storage_driver' => 'mysql',
    'mysql' => [
        'host' => 'localhost', 'port' => 3306,
        'database' => 'altetour', 'username' => 'altetour',
        'password' => '$DBPASS',
    ],
];
PHP
  unset DBPASS APPKEY
  echo "config.local.php creado"
fi
chown root:altetour "$CFG"; chmod 640 "$CFG"

# 4. Esquema y datos iniciales (no reemplaza lo existente). Se ejecuta como el usuario del sitio.
su -s /bin/bash altetour -c "php8.4 $BASE/altetour-app/bin/install.php"

# 5. Pool de PHP-FPM propio.
cat > "$POOL" <<'CONF'
[altetour]
user = altetour
group = altetour
listen = /run/php/php8.4-fpm-altetour.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = ondemand
pm.max_children = 6
pm.process_idle_timeout = 30s
pm.max_requests = 500
php_admin_value[upload_max_filesize] = 6M
php_admin_value[post_max_size] = 64M
php_admin_value[max_file_uploads] = 20
php_admin_value[expose_php] = Off
php_admin_value[open_basedir] = /var/www/altetour/:/tmp/
php_admin_flag[display_errors] = off
CONF
php-fpm8.4 -t
systemctl reload php8.4-fpm
echo "pool listo"
