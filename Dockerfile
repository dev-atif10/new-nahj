# استخدم صورة PHP الرسمية مع Apache
FROM php:8.3-apache

# تثبيت امتدادات PHP المطلوبة
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo_mysql zip

# تفعيل mod_rewrite
RUN a2enmod rewrite

# نسخ ملفات المشروع
WORKDIR /var/www/html
COPY . .

# منع Laravel من محاولة الوصول للقاعدة أثناء composer install
ENV LARAVEL_PACKAGE_DISCOVERY=false

# تثبيت Composer dependencies بدون محاولة الاتصال بالقاعدة
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# تعيين صلاحيات المجلدات
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# إعادة تمكين الـ package discovery عند التشغيل
ENV LARAVEL_PACKAGE_DISCOVERY=true

# فتح المنفذ 80
EXPOSE 80

# تشغيل Apache
CMD ["apache2-foreground"]
