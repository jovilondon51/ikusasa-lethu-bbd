FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mysqli zip dom \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . /var/www/html/
COPY docker/apache.conf /etc/apache2/conf-available/ikusasa.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/ikusasa.ini
COPY docker/start.sh /usr/local/bin/ikusasa-start
RUN a2enconf ikusasa && chmod +x /usr/local/bin/ikusasa-start \
    && mkdir -p /var/data/ikusasa && chown www-data:www-data /var/data/ikusasa \
    && chmod 700 /var/data/ikusasa
ENV UPLOAD_ROOT=/var/data/ikusasa
EXPOSE 8080
CMD ["/usr/local/bin/ikusasa-start"]
