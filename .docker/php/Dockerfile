FROM php:8.5-cli

# Move our PHP file into the container
# COPY ../../www/index.php /usr/src/www/index.php

# Make things easier if you shell in
WORKDIR /usr/src

# Our PHP will be running on port 8080 (unprivileged, so the container can run as the host user)
EXPOSE 8080

RUN apt-get -y update \
&& apt-get install -y libicu-dev libssl-dev git unzip libpng-dev libwebp-dev libjpeg62-turbo-dev \
&& docker-php-ext-configure intl \
&& docker-php-ext-configure ftp --with-ftp-ssl \
&& docker-php-ext-configure gd --with-webp --with-jpeg \
&& docker-php-ext-install intl pdo_mysql ftp gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set up a web server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "www/"]
