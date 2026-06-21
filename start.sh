#!/bin/bash
# Fundación Bon Sens - Inicio rápido
# ===================================
# Primera vez:  bash start.sh --seed
# Siguientes:   bash start.sh

DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR" || exit 1

SQLITE_EXT="/tmp/php-sqlite-ext/usr/lib/php/modules"
PHP_EXT_OPTS="-d extension=${SQLITE_EXT}/sqlite3.so -d extension=${SQLITE_EXT}/pdo_sqlite.so"

if [ ! -f "${SQLITE_EXT}/sqlite3.so" ]; then
    echo "Descargando extensión SQLite para PHP..."
    cd /tmp
    curl -sL -o php-sqlite.pkg.tar.zst "https://fastly.mirror.pkgbuild.com/extra/os/x86_64/php-sqlite-8.5.7-1-x86_64.pkg.tar.zst"
    mkdir -p php-sqlite-ext
    cd php-sqlite-ext
    tar --zstd -xf /tmp/php-sqlite.pkg.tar.zst 2>/dev/null || {
        zstd -d /tmp/php-sqlite.pkg.tar.zst -o /tmp/php-sqlite.tar && tar xf /tmp/php-sqlite.tar
    }
    cd "$DIR"
    echo "✓ Extensiones SQLite descargadas"
fi

if [ "$1" = "--seed" ] || [ "$1" = "--reset" ]; then
    echo "🌱 Sembrando datos de ejemplo..."
    rm -f data/bonsens.db
    php $PHP_EXT_OPTS backend/seed.php
fi

echo ""
echo "============================================"
echo "  Fundación Bon Sens"
echo "  Servidor: http://localhost:3000"
echo "  Admin:    http://localhost:3000/admin/"
echo "  API:      http://localhost:3000/api/health"
echo "============================================"
echo ""

php $PHP_EXT_OPTS -S localhost:3000 router.php
