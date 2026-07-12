FROM php:8.2-apache

# Install system dependencies for zip and other extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_mysql mysqli zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all application files
COPY . /var/www/html/

# Create upload directories with proper permissions
RUN mkdir -p /var/www/html/uploads/content \
    /var/www/html/uploads/projects \
    /var/www/html/uploads/profiles \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

# Configure Apache to allow .htaccess
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/docker-config.conf \
    && a2enconf docker-config

EXPOSE 80