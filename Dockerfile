# ===============================
# ✅ DOOHOON LINE BOT - PHP + APACHE
# ===============================

FROM php:8.2-apache

# ✅ เปิด mod_rewrite (จำเป็นหากใช้ include หรือ routing)
RUN a2enmod rewrite

# ✅ คัดลอกทุกไฟล์ไปยัง web root
COPY . /var/www/html/

# ✅ ตั้งสิทธิ์ให้ Apache อ่าน/เขียนได้
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# ✅ แก้ Apache ให้ใช้พอร์ต 10000 (Render ใช้พอร์ตนี้)
RUN sed -i 's/80/10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80/:10000/' /etc/apache2/sites-enabled/000-default.conf

# ✅ แก้ชื่อ server ป้องกัน warning "Could not reliably determine the server's fully qualified domain name"
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ✅ เปิดพอร์ต 10000
EXPOSE 10000

# ✅ เริ่ม Apache
CMD ["apache2-foreground"]
