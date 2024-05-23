FROM php:8.2-cli
RUN curl -L https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions > /usr/local/bin/install-php-extensions &&\
    chmod +x /usr/local/bin/install-php-extensions &&\
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" &&\
    sed -i -e 's/^memory_limit.*/memory_limit = -1/g' $PHP_INI_DIR/php.ini &&\
    apt update &&\
    apt install -y clamav clamdscan clamav-daemon gdal-bin screen &&\
    sed -i -e 's/^User .*/User root/g' /etc/clamav/clamd.conf &&\
    install-php-extensions @composer ctype dom fileinfo gd iconv intl libxml mbstring simplexml xml xmlwriter zip zlib bz2 phar yaml
ENV COMPOSER_ALLOW_SUPERUSER=1 PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/vendor/bin
RUN composer require --no-update acdh-oeaw/arche-ingest &&\
    composer require --no-update acdh-oeaw/repo-file-checker &&\
    composer require --no-update acdh-oeaw/arche-metadata-crawler &&\
    composer update -o --no-dev &&\
    chmod 777 vendor/acdh-oeaw/repo-file-checker/aux &&\
    ln -s /usr/local/bin/php /usr/bin/php
ENTRYPOINT /bin/bash
