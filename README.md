# 💼 ตัววิเคราะห์หุ้น Pro - Stock Analyzer

ตัววิเคราะห์หุ้นด้วย AI ที่อ่านข้อมูลจาก **Finnhub API** และแสดงผลการวิเคราะห์ทางการเงินแบบเรียลไทม์

## ✨ คุณสมบัติ

- 🔍 ค้นหาหุ้นจากสัญลักษณ์ (AAPL, MSFT, TSLA ฯลฯ)
- 📊 ตัวชี้วัดทางการเงินจริง (P/E, ROE, Revenue Growth ฯลฯ)
- 🎲 คะแนนสุขภาพทางการเงิน 0-100
- 🤖 คำแนะนำการลงทุน (ซื้อ/ถือ/ขาย)
- 💎 ตัวชี้วัดการประเมินมูลค่า
- 📈 ข้อมูลสินทรัพย์และกระแสเงินสด
- 🌐 ภาษาไทย 100%

---

## 🚀 การติดตั้งและการใช้งาน

### 1️⃣ ติดตั้ง Python Dependencies

```bash
pip install -r requirements.txt
```

### 2️⃣ เริ่มต้น Backend Server

```bash
python app.py
```

**ผลลัพธ์ที่ควรจะเห็น:**
```
╔══════════════════════════════════════════════════╗
║   Stock Analyzer Backend - API Proxy Server      ║
║   🚀 กำลังทำงาน http://localhost:5000            ║
║   ✅ ถ้าเห็นข้อความนี้ให้เปิดไฟล์ HTML ที่สร้างขึ้น ║
╚══════════════════════════════════════════════════╝
```

### 3️⃣ เปิดไฟล์ HTML

**วิธี A - ใช้ Browser:**
1. เปิด `stock_analyzer.html` ในเบราว์เซอร์
2. ค้นหาสัญลักษณ์หุ้น (เช่น AAPL)
3. คลิก "🔍 วิเคราะห์"

**วิธี B - ใช้ Live Server (VS Code):**
1. Install extension "Live Server"
2. คลิกขวาบน `stock_analyzer.html` → "Open with Live Server"

---

## 📝 โครงสร้างไฟล์

```
📁 ตัววิเคราะห์หุ้น
├── 📄 app.py                      ← Backend (Flask API Proxy)
├── 📄 stock_analyzer.html          ← Frontend (HTML + JavaScript)
├── 📄 requirements.txt             ← Python Dependencies
└── 📄 README.md                    ← คำแนะนำนี้
```

---

## 🔧 Architecture

```
Browser (HTML/JS)
    ↓
    └─→ http://localhost:5000 (Flask Backend)
             ↓
             └─→ https://finnhub.io/api/v1 (Finnhub API)
                      ↓
                      └─→ Real Financial Data ✅
```

**ทำไมต้องใช้ Backend?**
- 🔒 CORS Protection: ป้องกันการเรียก API โดยตรงจาก Browser
- 🔐 API Key Security: ไม่เปิดเผย API key ในฟ้อนต์เอนด์
- ⚡ Performance: สามารถ cache และ rate limit ได้

---

## 📊 ข้อมูลที่สามารถดู

### ตัวชี้วัดหลัก
- **ราคาปัจจุบัน** - ราคาจรจัดในตลาด
- **เปลี่ยนแปลง %** - เพิ่มขึ้น/ลดลงในวันนี้
- **52 สัปดาห์** - สูงสุด/ต่ำสุด

### ตัวชี้วัดการเงิน
- **P/E Ratio** - อัตราส่วนราคา/กำไร
- **P/B Ratio** - ราคา/มูลค่าทางบัญชี
- **ROE** - ผลตอบแทนต่อส่วนของผู้ถือหุ้น
- **ROA** - ผลตอบแทนต่อสินทรัพย์
- **Debt/Equity** - สัดส่วนหนี้ต่อทุน
- **Revenue Growth** - การเติบโตรายได้
- **Profit Margin** - อัตรากำไร

### คะแนนสุขภาพ
- **80-100**: ✨ ยอดเยี่ยม
- **60-79**: 👍 ดี
- **40-59**: ⚖️ ปานกลาง
- **<40**: ⚠️ อ่อนแอ

---

## 🤖 การตีความคำแนะนำ

### 🚀 ซื้อแบบเข้มแข็ง
- ✓ รายได้เติบโต > 10% ต่อปี
- ✓ อัตรากำไร > 15%
- ✓ P/E < 25x
- ✓ ROE > 15%

### 📈 ซื้อ
- ✓ รายได้เติบโต > 5% ต่อปี
- ✓ อัตรากำไร > 10%
- ✓ P/E < 30x

### ⚖️ ถือ
- ○ สถานะปานกลาง
- ○ รอการพัฒนาที่ชัดเจน

### 📉 ขาย
- ✗ รายได้ลดลง
- ✗ อัตรากำไร < 5%
- ✗ P/E > 50x

---

## ⚙️ การตั้งค่า API Key

ถ้าต้องการเปลี่ยน API Key:

**ไฟล์ app.py:**
```python
FINNHUB_API_KEY = 'คีย์ของคุณ'  # บรรทัดที่ 9
```

**ไฟล์ stock_analyzer.html:**
```javascript
const API_BASE_URL = 'http://localhost:5000/api';  // บรรทัดที่ 350
```

---

## 🐛 การแก้ไขปัญหา

### ❌ "ไม่สามารถเชื่อมต่อ API"
**วิธีแก้:**
1. ตรวจสอบว่า Backend server กำลังทำงาน (python app.py)
2. ตรวจสอบ port 5000 ไม่ถูกยึด
3. ลอง: `curl http://localhost:5000/health`

### ❌ "ModuleNotFoundError: No module named 'flask'"
**วิธีแก้:**
```bash
pip install -r requirements.txt
```

### ❌ "Address already in use"
**วิธีแก้:**
```bash
# Windows
netstat -ano | findstr :5000
taskkill /PID <PID> /F

# Mac/Linux
lsof -i :5000
kill -9 <PID>
```

### ❌ "อัตรา API ถูกจำกัด"
**เหตุผล:** Finnhub API มี limit ~60 requests/นาที
**วิธีแก้:** รอสักครู่แล้วลองอีกครั้ง

---

## 📌 ข้อมูลสำคัญ

### 💡 หมายเหตุ
- ข้อมูลมีการปรับปรุงแบบเรียลไทม์
- ไม่ใช่คำแนะนำการลงทุน - สำหรับการศึกษาเท่านั้น
- โปรดปรึกษาผู้เชี่ยวชาญทางการเงินก่อนลงทุน

### 🔒 ความปลอดภัย
- API Key ได้รับการป้องกันในฝั่ง Backend
- ไม่มีการจัดเก็บข้อมูลส่วนตัวใด ๆ
- แต่ละ request ทำงานอย่างอิสระ

---

## 🌐 API Endpoints

```
GET http://localhost:5000/health                    ← ตรวจสอบสถานะ
GET http://localhost:5000/api/quote/<ticker>       ← ราคา
GET http://localhost:5000/api/company/<ticker>     ← ข้อมูลบริษัท
GET http://localhost:5000/api/metrics/<ticker>     ← ตัวชี้วัด
GET http://localhost:5000/api/recommendation/<ticker> ← คำแนะนำ
```

---

## 📚 ทดสอบ Ticker ยอดนิยม

```
AAPL  - Apple
MSFT  - Microsoft
GOOGL - Google
AMZN  - Amazon
TSLA  - Tesla
META  - Facebook
NVDA  - NVIDIA
AMD   - Advanced Micro Devices
JPM   - JPMorgan Chase
V     - Visa
```

---

## 📝 License

สำหรับการศึกษาและใช้งานส่วนตัว

---

## 💬 คำถามที่พบบ่อย

**Q: ทำไมต้องรัน Backend?**
A: เพื่อหลีกเลี่ยงปัญหา CORS และเก็บ API Key ไว้ปลอดภัย

**Q: สามารถใช้งานบนโปรแกรมขนาดเล็กได้ไหม?**
A: ได้ ใช้งานได้ยาว ตราบใดที่ Backend กำลังทำงาน

**Q: ข้อมูลแม่นยำแค่ไหน?**
A: ข้อมูลมาจาก Finnhub API ซึ่งมีความแม่นยำสูง

**Q: สามารถ Deploy ออนไลน์ได้ไหม?**
A: ได้ เช่น Heroku, Railway, Replit

---

## 🚀 อัพเกรดในอนาคต

- [ ] เพิ่มกราฟแนวโน้มราคา
- [ ] บันทึกโปรดให้ Favorites
- [ ] เปรียบเทียบหุ้นหลายตัว
- [ ] ส่วน Market Overview
- [ ] Notification เมื่อราคาเปลี่ยน

---

**สร้างโดย:** AI Stock Analyzer Team ✨
**ปรับปรุงล่าสุด:** 2024
