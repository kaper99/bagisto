FROM php:7.4-apache

RUN addgroup --gid 1001 aat; \
    adduser --uid 1001 --gid 1001 --disabled-password aat

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    exiftool \
    libpng-dev \
    zlib1g-dev \
    libxml2-dev \
    libzip-dev \
    libjpeg-dev \
    libpq-dev \
    libonig-dev \
    iputils-ping \
    zip \
    curl \
    unzip \
    libmagickwand-dev --no-install-recommends \
    && pecl install -o -f redis \
    && pecl install -o -f imagick \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-configure bcmath \
    && docker-php-ext-configure pcntl \
    && docker-php-ext-configure mbstring \
    && docker-php-ext-configure exif \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install bcmath exif intl pcntl pdo mbstring zip \
    && docker-php-ext-enable redis pcntl mbstring imagick \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-enable pdo_mysql imagick \
    && docker-php-source delete

COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY .docker/php-config.ini $PHP_INI_DIR/conf.d/php-overrides.ini
COPY --chown=www-data:www-data . /var/www/html

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN cd /var/www/html && composer install

RUN a2enmod rewrite
RUN sed -i "s/APACHE_RUN_USER:=www-data/APACHE_RUN_USER:=aat/g" /etc/apache2/envvars \
    && sed -i "s/APACHE_RUN_GROUP:=www-data/APACHE_RUN_GROUP:=aat/g" /etc/apache2/envvars
RUN chmod -R 775 /var/www/html \
    && chown -R aat:aat /var/www/html
EXPOSE 80