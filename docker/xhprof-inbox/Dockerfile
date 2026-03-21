FROM php:8.5-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    graphviz \
    supervisor \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/*

# Ensure php-fpm listens on TCP 9000 (not socket)
RUN sed -i 's/^listen = .*/listen = 9000/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^listen.allowed_clients.*//' /usr/local/etc/php-fpm.d/www.conf

RUN mkdir -p /var/log/supervisor /var/run/php

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY nginx.conf /etc/nginx/nginx.conf
COPY www/ /var/www/xhprof-inbox/

RUN mkdir -p /var/www/xhprof-inbox/storage \
    && chown -R www-data:www-data /var/www/xhprof-inbox/storage \
    && chmod -R 755 /var/www/xhprof-inbox

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
