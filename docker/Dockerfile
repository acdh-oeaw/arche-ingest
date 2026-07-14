FROM ubuntu:resolute
RUN apt update &&\
    apt install -y clamav clamdscan clamav-daemon gdal-bin screen default-jre-headless curl git vim exiftool php-cli php-ctype php-dom php-exif php-fileinfo php-gd php-iconv php-intl php-mbstring php-pdo-pgsql php-pdo-sqlite php-simplexml php-xml php-xmlwriter php-zip php-bz2 php-phar php-yaml php-xdebug composer jing blender python3-pip
RUN sed -i -e 's/^User .*/User root/g' /etc/clamav/clamd.conf &&\
    sed -i -e 's/^memory_limit.*/memory_limit = -1/g' /etc/php/*/*/php.ini &&\
    usermod -s /bin/bash root &&\
    echo 'shell "/bin/bash"' >> /root/.screenrc &&\
    echo "syntax on\nfiletype plugin indent on\nset tabstop=4\nset shiftwidth=4\nset expandtab" > /root/.vimrc &&\
    ln -s libextern_draco.cpython-314-x86_64-linux-gnu.so /usr/lib/blender/scripts/addons_core/io_scene_gltf2/libextern_draco.so &&\
    curl -L https://github.com/dnglab/dnglab/releases/download/v0.7.2/dnglab_0.7.2-1_amd64.deb > tmp.dpkg && dpkg -i tmp.dpkg && rm -fR tmp.dpkg &&\
    pip3 install --break-system-packages bagit

ENV COMPOSER_ALLOW_SUPERUSER=1 PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/vendor/bin LC_ALL=C.utf8 LANG=C.utf8
RUN cd / &&\
    composer require --no-update acdh-oeaw/arche-ingest &&\
    composer require --no-update acdh-oeaw/repo-file-checker &&\
    composer require --no-update acdh-oeaw/arche-metadata-crawler &&\
    composer update -o --no-dev &&\
    chmod 777 vendor/acdh-oeaw/repo-file-checker/aux &&\
    /vendor/acdh-oeaw/repo-file-checker/aux/install_deps.sh
COPY start.sh /start.sh
ENTRYPOINT /start.sh
