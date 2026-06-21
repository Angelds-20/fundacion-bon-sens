FROM php:8.2-apache

# Install required system packages and PHP extensions
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo_sqlite sqlite3 pdo_pgsql pgsql curl zip gd intl

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Configure working directory
WORKDIR /var/www/html

# Copy all application files
COPY . /var/www/html/

# Create data directory and set proper permissions for apache (www-data)
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html

# Expose port 80 (default Apache port)
EXPOSE 80

# Configure default environment variables
ENV DB_PATH=/var/www/html/data/bonsens.db
ENV BASE_URL=http://localhost

# Setup entrypoint script
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
