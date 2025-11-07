# ใช้ PHP 8.2 พร้อม Apache เป็น base image
FROM php:8.2-apache

# คัดลอกทุกไฟล์ในโปรเจกต์เข้าไปใน container
COPY . /var/www/html/

# เปิดพอร์ต 10000 (Render จะ forward ให้โดยอัตโนมัติ)
EXPOSE 10000

# เปลี่ยนค่า default port ของ Apache ให้ตรงกับ Render (10000)
RUN sed -i 's/80/10000/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# เปิด mod_rewrite (จำเป็นสำหรับ PHP เว็บทั่วไป)
RUN a2enmod rewrite

# เริ่มเซิร์ฟเวอร์
CMD ["apache2-foreground"]
