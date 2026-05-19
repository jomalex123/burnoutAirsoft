FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/zz-burnout-security.conf \
    && a2enconf zz-burnout-security \
    && printf 'expose_php=Off\n' > /usr/local/etc/php/conf.d/burnout-security.ini

WORKDIR /var/www/html
