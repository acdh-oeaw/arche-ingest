FROM php:8.1
RUN curl -L https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions > /usr/local/bin/install-php-extensions &&\
    chmod +x /usr/local/bin/install-php-extensions &&\
    install-php-extensions @composer &&\
    composer require acdh-oeaw/arche-ingest

