#!/usr/bin/env bash
# Cloud ("garp") environment setup script — paste into: environment settings → Setup script.
# Provisions MariaDB (MySQL-compatible, like XAMPP) for Auxtech Ops. Safe to run repeatedly.
set -euo pipefail

if ! command -v mariadbd >/dev/null 2>&1; then
  apt-get install -y -q mariadb-server || { apt-get update -q && apt-get install -y -q mariadb-server; }
fi

mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld || true
if ! mariadb -uroot -e 'SELECT 1' >/dev/null 2>&1; then
  (mysqld_safe --user=mysql >/dev/null 2>&1 &)
  for _ in $(seq 1 30); do mariadb -uroot -e 'SELECT 1' >/dev/null 2>&1 && break; sleep 1; done
fi

# XAMPP parity: root with empty password over TCP 127.0.0.1:3306.
mariadb -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('');
  CREATE DATABASE IF NOT EXISTS auxtech_ops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE DATABASE IF NOT EXISTS garp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  FLUSH PRIVILEGES;"
echo "MariaDB ready"
