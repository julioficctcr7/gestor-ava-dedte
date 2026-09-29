# Dockerfile para Backend de Analítica CQRS (Gestor AVA - DEDTE)
FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    mariadb-client \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip

RUN docker-php-ext-install mysqli pdo pdo_mysql opcache

# Configuración de OPcache para máxima velocidad y latencia < 15ms
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

WORKDIR /var/www/backend
COPY . /var/www/backend

EXPOSE 9000
CMD ["php-fpm"]
