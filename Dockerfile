# ===============================
# ✅ DOOHOON LINE BOT - PHP + APACHE
# ===============================

FROM php:8.2-apache

# คัดลอกไฟล์ทั้งหมดเข้าไปใน web root
COPY . /var/www/html/

# ให้ Apache เข้าถึงไฟล์ได้
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# เปิดพอร์ต 10000
EXPOSE 10000

# ✅ บังคับให้ Apache ใช้พอร์ต 10000 (Render ต้องใช้)
CMD sed -i 's/80/10000/' /etc/apache2/ports.conf && apache2-foreground
