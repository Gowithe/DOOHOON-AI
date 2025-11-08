# ใช้ PHP image ที่มีเว็บเซิร์ฟเวอร์ในตัว
FROM php:8.2-cli

# ตั้ง working directory ไปยัง root ของโปรเจกต์
WORKDIR /var/www/html

# คัดลอกไฟล์ทั้งหมดไปไว้ใน container
COPY . /var/www/html

# เปิดพอร์ตให้ Render ใช้งาน
EXPOSE 10000

# ✅ สั่งให้ PHP รันโฟลเดอร์ปัจจุบันเป็น web root
CMD ["php", "-S", "0.0.0.0:10000", "-t", "."]
