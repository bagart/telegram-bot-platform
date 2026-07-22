# Stage 1: Build environment and Composer dependencies
FROM php:8.5-fpm AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    ca-certificates \
    unzip \
    libpq-dev \
    libonig-dev \
    libssl-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    libzip-dev \
    && update-ca-certificates \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    bcmath \
    soap \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*


WORKDIR /var/www

COPY . /var/www


# Install Composer
RUN rm -f /usr/bin/composer /usr/local/bin/composer \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php \
        --install-dir=/usr/local/bin \
        --filename=composer \
    && rm composer-setup.php


RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --prefer-dist



# Stage 2: Production environment
FROM php:8.5-fpm AS production


RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libfcgi-bin \
    procps \
    && update-ca-certificates \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*


RUN curl -fsSL \
    https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck \
    -o /usr/local/bin/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck


COPY ./docker/production/php-fpm/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh


COPY ./storage /var/www/storage-init


COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/bin/docker-php-ext-* /usr/local/bin/


RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"


COPY ./docker/common/php-fpm/conf.d/*.conf /usr/local/etc/php-fpm.d/


COPY --from=builder /var/www /var/www


WORKDIR /var/www


RUN chown -R www-data:www-data /var/www


USER www-data


ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

EXPOSE 9000

CMD ["php-fpm"]



# Stage 3: Development image
FROM production AS development


ARG XDEBUG_ENABLED=true
ARG XDEBUG_MODE=develop,coverage,debug,profile
ARG XDEBUG_HOST=host.docker.internal
ARG XDEBUG_IDE_KEY=DOCKER
ARG XDEBUG_LOG=/dev/stdout
ARG XDEBUG_LOG_LEVEL=0


USER root


RUN if [ "${XDEBUG_ENABLED}" = "true" ]; then \
        pecl install xdebug && \
        docker-php-ext-enable xdebug && \
        { \
            echo "xdebug.mode=${XDEBUG_MODE}"; \
            echo "xdebug.idekey=${XDEBUG_IDE_KEY}"; \
            echo "xdebug.log=${XDEBUG_LOG}"; \
            echo "xdebug.log_level=${XDEBUG_LOG_LEVEL}"; \
            echo "xdebug.client_host=${XDEBUG_HOST}"; \
            echo "xdebug.start_with_request=yes"; \
        } > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini; \
    fi


ARG UID=1000
ARG GID=1000


RUN if getent group ${GID}; then \
        group_name=$(getent group ${GID} | cut -d: -f1); \
        useradd -m -u ${UID} -g ${GID} -s /bin/bash www; \
    else \
        groupadd -g ${GID} www && \
        useradd -m -u ${UID} -g www -s /bin/bash www; \
        group_name=www; \
    fi


RUN sed -i "s/user = www-data/user = www/g" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/group = www-data/group = $group_name/g" /usr/local/etc/php-fpm.d/www.conf


COPY ./docker/development/php-fpm/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh


WORKDIR /var/www


USER www-data


ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

EXPOSE 9000

CMD ["php-fpm"]
