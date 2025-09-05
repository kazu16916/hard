FROM php:8.1-apache

# 必要な拡張機能をインストール
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Apache設定
RUN a2enmod rewrite

# 作業ディレクトリ設定
WORKDIR /var/www/html

# ファイルアップロード用ディレクトリ作成
RUN mkdir -p /var/www/html/uploads && chmod 777 /var/www/html/uploads

# PHP設定
RUN echo "file_uploads = On" >> /usr/local/etc/php/php.ini && \
    echo "upload_max_filesize = 10M" >> /usr/local/etc/php/php.ini && \
    echo "post_max_size = 10M" >> /usr/local/etc/php/php.ini

EXPOSE 80