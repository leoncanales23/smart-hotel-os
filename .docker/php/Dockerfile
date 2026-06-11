# ============================================================
#  SmartHotelOS — PHP 8.2 FPM Docker Image
#  Grupo Smart de Administración
# ============================================================

ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-fpm-alpine

LABEL maintainer="Smart Hotel Group Dev Team"
LABEL org.opencontainers.image.title="SmartHotelOS PHP"
LABEL org.opencontainers.image.version="1.0.0"

# ── Dependencias del sistema ──────────────────────────────────
RUN apk add --no-cache \
    # Build tools
    $PHPIZE_DEPS \
    # Libs PHP
    libpng-dev libjpeg-turbo-dev libwebp-dev \
    libxml2-dev oniguruma-dev icu-dev \
    libzip-dev freetype-dev \
    # Redis
    hiredis-dev \
    # Misc
    curl-dev openssl-dev \
    # Runtime
    libpng libjpeg-turbo libwebp icu-libs libzip \
    # Utilidades
    git curl unzip bash tzdata

# ── Zona horaria ──────────────────────────────────────────────
RUN cp /usr/share/zoneinfo/America/Santiago /etc/localtime && \
    echo "America/Santiago" > /etc/timezone

# ── Extensiones PHP ───────────────────────────────────────────
RUN docker-php-ext-configure gd \
        --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        intl \
        zip \
        mbstring \
        opcache \
        bcmath \
        soap \
        xml \
        curl \
        pcntl \
        sockets

# ── Redis extension via PECL ──────────────────────────────────
RUN pecl install redis && \
    docker-php-ext-enable redis && \
    rm -rf /tmp/pear

# ── Xdebug (solo en desarrollo) ───────────────────────────────
ARG INSTALL_XDEBUG=false
RUN if [ "$INSTALL_XDEBUG" = "true" ]; then \
        pecl install xdebug && \
        docker-php-ext-enable xdebug && \
        echo "xdebug.mode=coverage,debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
        echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
        echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini; \
    fi

# ── OPcache config ────────────────────────────────────────────
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.enable_cli=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/opcache.ini

# ── Composer ─────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

# ── Crear usuario no-root ─────────────────────────────────────
RUN addgroup -g 1000 smart && \
    adduser -u 1000 -G smart -h /home/smart -D smart

# ── Directorio de trabajo ─────────────────────────────────────
WORKDIR /var/www/html

# ── Copiar config PHP ─────────────────────────────────────────
COPY php.ini /usr/local/etc/php/php.ini
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

# ── PHP-FPM como usuario smart ────────────────────────────────
RUN sed -i 's/user = www-data/user = smart/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/group = www-data/group = smart/' /usr/local/etc/php-fpm.d/www.conf && \
    chown -R smart:smart /var/www/html

USER smart

EXPOSE 9000
CMD ["php-fpm"]
