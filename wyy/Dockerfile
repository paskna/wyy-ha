FROM ghcr.io/home-assistant/base:3.24-2026.06.1

ARG BUILD_ARCH
ARG BUILD_VERSION=0.1.1

RUN apk add --no-cache \
    nginx \
    php84 \
    php84-bcmath \
    php84-ctype \
    php84-curl \
    php84-dom \
    php84-exif \
    php84-fileinfo \
    php84-fpm \
    php84-gd \
    php84-iconv \
    php84-intl \
    php84-mbstring \
    php84-opcache \
    php84-openssl \
    php84-pdo \
    php84-pdo_mysql \
    php84-pdo_sqlite \
    php84-phar \
    php84-session \
    php84-tokenizer \
    php84-xml \
    php84-xmlreader \
    php84-xmlwriter \
    php84-zip \
    imagemagick \
    libheif \
    tzdata \
    && mkdir -p /run/nginx /run/php /opt/wyy

COPY rootfs /
COPY run.sh /run.sh

RUN chmod 0755 /run.sh /etc/nginx/http.d/wyy.conf \
    && chmod 0755 /opt/wyy/artisan \
    && rm -rf /opt/wyy/storage \
    && ln -s /data/wyy/storage /opt/wyy/storage

LABEL \
    io.hass.version="${BUILD_VERSION}" \
    io.hass.type="app" \
    io.hass.arch="${BUILD_ARCH}" \
    org.opencontainers.image.title="WYY Home Assistant App" \
    org.opencontainers.image.description="Personal wine management for Home Assistant" \
    org.opencontainers.image.source="https://github.com/paskna/wyy-ha" \
    org.opencontainers.image.licenses="MIT"

CMD ["/run.sh"]
