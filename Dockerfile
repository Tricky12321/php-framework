FROM php:8.0-fpm
RUN apt update && apt install -y \
    nginx \
    git \
    lsof \
    openssl \
    libssl-dev \
    psmisc \
    zip \
    libzip-dev \
    procps

RUN cd ~/



# Setup PHP extensions needed to run Datacollector
RUN pecl install mongodb
RUN pecl install xdebug
RUN docker-php-ext-enable mongodb
RUN docker-php-ext-install mysqli
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install pdo
RUN docker-php-ext-install zip


# Open telemetry
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

RUN pecl install grpc
RUN install-php-extensions zlib
RUN install-php-extensions ffi
RUN install-php-extensions mbstring
RUN install-php-extensions protobuf




# Increase the memory limit
RUN echo "memory_limit=8G" > /usr/local/etc/php/conf.d/docker-php-ext-memory.ini
RUN echo "output_buffering = off" > /usr/local/etc/php/conf.d/docker-php-ext-output-buffering.ini



#RUN mkdir /srv/web
#ADD src /srv/web/src
#ADD composer.json /srv/web/composer.json
#ADD composer.lock /srv/web/composer.lock
#WORKDIR /srv/web
#RUN composer install


# Setup nginx config
RUN rm /etc/nginx/nginx.conf
RUN rm /etc/nginx/sites-enabled/default

ADD conf/nginx.conf /etc/nginx/nginx.conf
ADD conf/nginx-site.conf /etc/nginx/sites-enabled
RUN mkdir /usr/share/nginx/logs
RUN touch /usr/share/nginx/logs/error.log

# PHP FPM
RUN mkdir /run/php-fpm
RUN mkdir /etc/php-fpm
ADD conf/php-fpm.conf /etc/php-fpm/php-fpm.conf



# Cleanup
RUN rm -rf /var/lib/apt/lists/*
RUN mkdir /xdebug
# Set Xdebug settings
RUN echo "zend_extension=xdebug;\n\
[xdebug] \n\
xdebug.client_host=host.docker.internal;\n\
xdebug.client_port=9009;\n\
xdebug.mode = debug;\n\
xdebug.output_dir=\"/srv/web/xdebug\";" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN mkdir /etc/nginx/ssl
WORKDIR /etc/nginx/ssl
ADD certs/* /etc/nginx/ssl/
WORKDIR /srv/web
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN updatedb
# EXPOSE ports
EXPOSE 80
EXPOSE 443
EXPOSE 9009
# Forward request logs to Docker log collector
CMD /usr/local/sbin/php-fpm -y /etc/php-fpm/php-fpm.conf && /usr/sbin/nginx && tail -f /var/log/nginx/error.log