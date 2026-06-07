#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/cobrawa"
DB_NAME="cobrawa"
DB_USER="cobrawa"
DB_PASS="CobraWA@123"
PHP_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n1 || true)"

if [[ -z "$PHP_SOCK" ]]; then
  echo "PHP-FPM socket não encontrado. Instale php-fpm antes de continuar."
  exit 1
fi

apt update
apt install nginx mariadb-server php php-fpm php-mysql php-curl php-mbstring php-xml php-zip php-gd git unzip -y

mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"

cat > /etc/nginx/sites-available/cobrawa <<NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    root ${APP_DIR};
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCK};
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/cobrawa /etc/nginx/sites-enabled/cobrawa
nginx -t
systemctl restart nginx

echo "Instalação base concluída. Acesse: http://SEU_IP/install/"
echo "Banco: ${DB_NAME} | Usuário: ${DB_USER} | Senha: ${DB_PASS}"
echo "WAHA local recomendado: http://127.0.0.1:3000"
