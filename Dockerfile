# ===== Clínica Salud Total — imagen para Render (PHP + Apache) =====
FROM php:8.2-apache

# Extensión MySQL y módulos de Apache que usa el .htaccess
RUN docker-php-ext-install mysqli \
 && a2enmod rewrite headers expires deflate

# Configuración de PHP para producción
RUN { \
      echo 'upload_max_filesize = 10M'; \
      echo 'post_max_size = 12M'; \
      echo 'expose_php = Off'; \
      echo 'date.timezone = America/Bogota'; \
    } > /usr/local/etc/php/conf.d/clinica.ini

# Permitir .htaccess y reconocer el HTTPS que termina en el proxy de Render
RUN { \
      echo '<Directory /var/www/html>'; \
      echo '    AllowOverride All'; \
      echo '</Directory>'; \
      echo 'SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on'; \
      echo 'ServerTokens Prod'; \
      echo 'ServerSignature Off'; \
    } > /etc/apache2/conf-enabled/clinica.conf

COPY . /var/www/html/

# En Render el sitio vive en la raíz del dominio, no en /proyecto_productivo
RUN sed -i 's#ErrorDocument 404 /proyecto_productivo/404.php#ErrorDocument 404 /404.php#' /var/www/html/.htaccess \
 && mkdir -p /var/www/html/log_in/uploads/identidad /var/www/html/log_in/uploads/perfil /var/www/html/log_in/uploads/resultados \
 && chown -R www-data:www-data /var/www/html/log_in/uploads

ENV APP_BASE_URL="" \
    APP_FORZAR_HTTPS="true" \
    PORT=10000

# Render indica el puerto en la variable PORT
CMD sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf \
 && sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-enabled/000-default.conf \
 && apache2-foreground
