#!/usr/bin/env bash
set -e

service redis-server start || true
service mariadb start || true

mariadb -u root << 'EOF'
CREATE DATABASE IF NOT EXISTS moderation;
CREATE DATABASE IF NOT EXISTS logger;
CREATE DATABASE IF NOT EXISTS cosmetic;
CREATE DATABASE IF NOT EXISTS ranks;

CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF

echo "Databases:"
mariadb -u root -e "SHOW DATABASES;"
echo "Redis Ping:"
redis-cli ping
