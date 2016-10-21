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
    && echo "extension=phalcon.so" > /usr/local/etc/php/conf.d/25-phalcon.ini \
    # install ed25519
    && git clone git://github.com/wedancedalot/php-ed25519-ext.git \
    && cd php-ed25519-ext && phpize && ./configure && make && make install \
    && echo "extension=ed25519.so" > /usr/local/etc/php/conf.d/30-ed25519.ini

    # Memcached
    RUN apt-get update && apt-get install -y \
            libmemcached11 \
            libmemcachedutil2 \
            libmemcached-dev \
            libz-dev \
        && cd /root \
        && git clone -b $PHPREDIS_VERSION https://github.com/php-memcached-dev/php-memcached \
        && cd php-memcached \
        && phpize \
        && ./configure \
        && make \
        && make install \
        && cd .. \
        && rm -rf  php-memcached \
        && echo extension=memcached.so >> /usr/local/etc/php/conf.d/memcached.ini \
        && apt-get remove -y build-essential libmemcached-dev libz-dev \
        && apt-get remove -y \
            libmemcached-dev \
            libz-dev \
        && apt-get autoremove -y \
        && rm -rf /var/lib/apt/lists/* \
        && apt-get clean

    # Redis
RUN curl -L -o /tmp/redis.tar.gz https://github.com/phpredis/phpredis/archive/$PHPREDIS_VERSION.tar.gz \
    && mkdir -p /usr/src/php/ext/ \
    && tar xfz /tmp/redis.tar.gz \
    && rm -r /tmp/redis.tar.gz \
    && mv phpredis-$PHPREDIS_VERSION /usr/src/php/ext/redis \
    && docker-php-ext-install redis \

    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add crontab file in the cron directory
ADD config/crontab /crontab_tmp

# Add php.ini
ADD config/php.ini /usr/local/etc/php/php.ini

ADD ./entrypoint.sh /

ENV PATH $PATH:/root/.composer/vendor/bin

CMD ["/entrypoint.sh"]