#!/bin/sh
set -e

# Path to SQLite database
DB_FILE="/var/www/html/data/bonsens.db"

# Ensure data directory exists and is owned by apache
mkdir -p /var/www/html/data
chown -R www-data:www-data /var/www/html/data
chmod 775 /var/www/html/data

# Only auto-seed if we are using SQLite and the database file doesn't exist
if [ -z "$DATABASE_URL" ]; then
    if [ ! -f "$DB_FILE" ]; then
        echo "🌱 SQLite database not found. Creating and seeding..."
        php /var/www/html/backend/seed.php
        if [ -f "$DB_FILE" ]; then
            chown www-data:www-data "$DB_FILE"
            chmod 664 "$DB_FILE"
        fi
    fi
fi

# Execute the main container command
exec "$@"
