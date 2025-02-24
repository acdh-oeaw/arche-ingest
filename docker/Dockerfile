#FROM php:8.3-cli
#RUN curl -L https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions > /usr/local/bin/install-php-extensions &&\
#    chmod +x /usr/local/bin/install-php-extensions &&\
#    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" &&\
#    sed -i -e 's/^memory_limit.*/memory_limit = -1/g' $PHP_INI_DIR/php.ini &&\
#    apt update &&\
#    apt install -y clamav clamdscan clamav-daemon gdal-bin screen default-jre-headless git vim exiftool &&\
#    install-php-extensions @composer ctype dom exif fileinfo gd iconv intl libxml mbstring simplexml xml xmlwriter zip zlib bz2 phar yaml xdebug &&\
#    ln -s /usr/local/bin/php /usr/bin/php &&\
FROM ubuntu:24.04
RUN apt update &&\
    apt install -y clamav clamdscan clamav-daemon gdal-bin screen default-jre-headless git vim exiftool php-cli php-ctype php-dom php-exif php-fileinfo php-gd php-iconv php-intl php-mbstring php-simplexml php-xml php-xmlwriter php-zip php-bz2 php-phar php-yaml php-xdebug composer
RUN sed -i -e 's/^User .*/User root/g' /etc/clamav/clamd.conf &&\
    sed -i -e 's/^memory_limit.*/memory_limit = -1/g' /etc/php/*/*/php.ini &&\
    usermod -s /bin/bash root &&\
    echo 'shell "/bin/bash"' >> /root/.screenrc &&\
    echo "syntax on\nfiletype plugin indent on\nset tabstop=4\nset shiftwidth=4\nset expandtab" > /root/.vimrc

ENV COMPOSER_ALLOW_SUPERUSER=1 PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/vendor/bin LC_ALL=C.utf8 LANG=C.utf8
RUN composer require --no-update acdh-oeaw/arche-ingest &&\
    composer require --no-update acdh-oeaw/repo-file-checker &&\
    composer require --no-update "acdh-oeaw/arche-metadata-crawler <1" &&\
    composer update -o --no-dev &&\
    chmod 777 vendor/acdh-oeaw/repo-file-checker/aux &&\
    /vendor/acdh-oeaw/repo-file-checker/aux/install_deps.sh
ENTRYPOINT /bin/bash
