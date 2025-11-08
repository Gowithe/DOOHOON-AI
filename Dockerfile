# ===============================
# ✅ PHP + Render Web Server
# ===============================

FROM php:8.2-cli

# ตั้ง working directory (Render จะรันจากตรงนี้)
WORKDIR /var/www/html

# คัดลอกไฟล์ทั้งหมดใน repo ไปไว้ใน container
COPY . /var/www/html

# เปิดพอร์ตที่ Render ใช้
EXPOSE 10000

# ✅ สั่งให้ PHP รัน server โดยใช้โฟลเดอร์นี้เป็น web root
CMD ["php", "-S", "0.0.0.0:10000", "-t", "/var/www/html"]
