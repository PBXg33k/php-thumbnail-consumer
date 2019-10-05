FROM php:7.3-fpm-alpine AS build
MAINTAINER Oguzhan Uysal <development@oguzhanuysal.eu>

ENV XDEBUGVERSION="2.7.0RC2"
ENV MT_CONF_FILE="/var/www/app/config/mt.json"
ENV THUMB_DIR="/media/thumbs"

# install PHP extensions & composer
RUN apk add --no-cache --update --virtual build-dependencies alpine-sdk automake autoconf \
    && apk add --no-cache --update git \
	&& php -r "readfile('https://getcomposer.org/installer');" | php -- --install-dir=/usr/local/bin --filename=composer \
	&& chmod +sx /usr/local/bin/composer \
	&& apk add --no-cache --update rabbitmq-c rabbitmq-c-dev \
    && git clone --depth=1 https://github.com/pdezwart/php-amqp.git /tmp/php-amqp \
    && cd /tmp/php-amqp \
    && phpize && ./configure && make && make install \
    && cd ../ && rm -rf /tmp/php-amqp && apk del build-dependencies \
    && docker-php-ext-enable amqp

# install MT (media thumbnails)
RUN wget https://github.com/mutschler/mt/releases/download/1.0.8/mt-1.0.8-linux_amd64.tar.bz2 \
    && tar xvjf mt-1.0.8-linux_amd64.tar.bz2 \
    && mv mt-1.0.8-linux_amd64 /usr/local/bin/mt \
    && chmod +x /usr/local/bin/mt \
    && rm -f mt-1.0.8-linux_amd64.tar.bz2

FROM build AS final
WORKDIR /var/www

COPY . /var/www
WORKDIR /var/www
RUN composer install --no-dev --optimize-autoloader --prefer-dist --no-scripts

# Cleanup
RUN rm -rf /tmp/* && chmod +x /var/www/start.sh

CMD ["/var/www/start.sh"]

EXPOSE 9000
