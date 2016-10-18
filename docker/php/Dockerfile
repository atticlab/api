FROM php:7.0-fpm

ENV PHPREDIS_VERSION php7

RUN apt-get update && apt-get install -y \
    git cron libpq-dev postgresql-client libxml2-dev \
    && docker-php-ext-install pgsql \
    && docker-php-ext-install pdo_pgsql \
    && docker-php-ext-install soap \
    && docker-php-ext-install pcntl \
    # Phalcon
    && git clone --depth=1 -b 3.0.x git://github.com/phalcon/cphalcon.git /usr/local/src/cphalcon \
    && cd /usr/local/src/cphalcon/build && ./install \
    && echo "extension=phalcon.so" > /usr/local/etc/php/conf.d/25-phalcon.ini

    # Redis
RUN curl -L -o /tmp/redis.tar.gz https://github.com/phpredis/phpredis/archive/$PHPREDIS_VERSION.tar.gz \
    && mkdir -p /usr/src/php/ext/ \
    && tar xfz /tmp/redis.tar.gz \
    && rm -r /tmp/redis.tar.gz \
    && mv phpredis-$PHPREDIS_VERSION /usr/src/php/ext/redis \
    && docker-php-ext-install redis

# Add crontab file in the cron directory
ADD config/crontab /crontab_tmp

# Add php.ini
ADD config/php.ini /usr/local/etc/php/php.ini

ADD ./entrypoint.sh /

CMD ["/entrypoint.sh"]