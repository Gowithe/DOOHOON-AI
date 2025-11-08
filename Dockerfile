# ===============================
# ✅ DOOHOON LINE BOT - PHP SERVER
# ===============================

FROM php:8.2-cli

# ตั้ง working directory
WORKDIR /var/www/html

# คัดลอกไฟล์ทั้งหมดใน repo เข้าไปใน container
COPY . /var/www/html

# เปิดพอร์ต 10000 (Render ใช้พอร์ตนี้)
EXPOSE 10000

# ✅ สั่งให้ PHP รันเซิร์ฟเวอร์ โดยใช้โฟลเดอร์ปัจจุบันเป็น root
CMD ["php", "-S", "0.0.0.0:10000", "-t", "."]
