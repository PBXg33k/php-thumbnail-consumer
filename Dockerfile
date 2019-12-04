FROM pbxg33k/php-consumer-base AS base
MAINTAINER Oguzhan Uysal <development@oguzhanuysal.eu>

ENV XDEBUGVERSION="2.7.0RC2"
ENV MT_CONF_FILE="/var/www/config/mt.json"
ENV THUMB_DIR="/media/thumbs"

# install MT (media thumbnails)
RUN wget https://github.com/mutschler/mt/releases/download/1.0.8/mt-1.0.8-linux_amd64.tar.bz2 \
    && tar xvjf mt-1.0.8-linux_amd64.tar.bz2 \
    && mv mt-1.0.8-linux_amd64 /usr/local/bin/mt \
    && chmod +x /usr/local/bin/mt \
    && rm -f mt-1.0.8-linux_amd64.tar.bz2

RUN docker-php-ext-install pcntl

FROM base AS final
WORKDIR /var/www

COPY . /var/www
WORKDIR /var/www
RUN composer install --no-dev --optimize-autoloader --prefer-dist --no-scripts

# Cleanup
RUN rm -rf /tmp/* && chmod +x /var/www/start.sh

CMD ["/var/www/start.sh"]

EXPOSE 9000
