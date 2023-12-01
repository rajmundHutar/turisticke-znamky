FROM php:8.2-cli

# Move our PHP file into the container
# COPY ../../www/index.php /usr/src/www/index.php

# Make things easier if you shell in
WORKDIR /usr/src

# Our PHP will be running on port 80
EXPOSE 80

RUN apt-get -y update \
&& apt-get install -y libicu-dev \
&& docker-php-ext-configure intl \
&& docker-php-ext-install intl pdo_mysql

# Set up a web server
CMD ["php", "-S", "0.0.0.0:80", "-t", "www/"]
