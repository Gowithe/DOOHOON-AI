<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📈 Stock Analyzer Pro</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1a1f35 100%);
            color: #e2e8f0;
            min-height: 100vh;
            padding: 20px;
        }

        .wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            margin-bottom: 10px;
            color: #06b6d4;
            font-size: 2.5rem;
        }

        .subtitle {
            text-align: center;
            color: #94a3b8;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        input {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid #334155;
            background: #1e293b;
            color: #e2e8f0;
            border-radius: 8px;
            font-size: 1.1rem;
        }

        input:focus {
            outline: none;
            border-color: #06b6d4;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
        }

        button {
            padding: 14px 30px;
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.3s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.4);
        }

        .quick-btn {
            display: inline-block;
            padding: 8px 16px;
            background: rgba(6, 182, 212, 0.1);
            border: 1px solid #06b6d4;
            color: #06b6d4;
            border-radius: 6px;
            cursor: pointer;
            margin-right: 10px;
            margin-bottom: 15px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .quick-btn:hover {
            background: rgba(6, 182, 212, 0.2);
            transform: scale(1.05);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 50px;
            color: #06b6d4;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #334155;
            border-top: 5px solid #06b6d4;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading.show {
            display: block;
        }

        .error {
            display: none;
            background: rgba(220, 38, 38, 0.15);
            border: 1px solid #dc2626;
            color: #fca5a5;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error.show {
            display: block;
        }

        .result {
            display: none;
        }

        .result.show {
            display: block;
        }

        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: #06b6d4;
            margin-bottom: 18px;
            font-size: 1.5rem;
            border-bottom: 2px solid #334155;
            padding-bottom: 12px;
        }

        .price-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .price-info {
            font-size: 2.5rem;
            font-weight: bold;
            color: #06b6d4;
        }

        .price-change {
            font-size: 1.5rem;
            font-weight: bold;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }

        .price-change.up {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .price-change.down {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-top: 18px;
        }

        .stat-item {
            background: rgba(6, 182, 212, 0.05);
            padding: 14px;
            border-radius: 8px;
            border-left: 3px solid #06b6d4;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 0.9rem;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .stat-value {
            color: #06b6d4;
            font-size: 1.3rem;
            font-weight: bold;
        }

        .list-items {
            list-style: none;
            padding-left: 0;
        }

        .list-items li {
            color: #cbd5e1;
            padding: 10px 0 10px 24px;
            position: relative;
            line-height: 1.6;
        }

        .list-items li:before {
            content: "▸";
            position: absolute;
            left: 0;
            color: #06b6d4;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .recommendation {
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(30, 64, 175, 0.1));
            border: 2px solid #06b6d4;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .rec-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .rec-item {
            background: rgba(6, 182, 212, 0.05);
            padding: 18px;
            border-radius: 8px;
            border: 1px solid #334155;
        }

        .rec-label {
            color: #94a3b8;
            font-size: 1rem;
            margin-bottom: 10px;
        }

        .rec-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #06b6d4;
        }

        .badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: bold;
            margin-top: 10px;
        }

        .badge.buy {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .badge.sell {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .badge.hold {
            background: rgba(234, 179, 8, 0.2);
            color: #fbbf24;
        }

        .info-content {
            color: #cbd5e1;
            line-height: 1.7;
        }

        .footer {
            text-align: center;
            color: #94a3b8;
            margin-top: 50px;
            padding-top: 24px;
            border-top: 1px solid #334155;
            font-size: 0.95rem;
        }

        .warning {
            background: rgba(234, 179, 8, 0.15);
            border: 1px solid rgba(234, 179, 8, 0.3);
            color: #fcd34d;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        @media (max-width: 768px) {
            .price-section {
                grid-template-columns: 1fr;
            }

            .rec-grid {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 2rem;
            }

            .search-box {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h1>📈 Stock Analyzer Pro</h1>
        <p class="subtitle">วิเคราะห์หุ้นแบบครบวงจร พร้อม AI จาก OpenAI</p>

        <div style="margin-bottom: 18px;">
            <button class="quick-btn" onclick="search('AAPL')">🍎 AAPL</button>
            <button class="quick-btn" onclick="search('GOOGL')">🔍 GOOGL</button>
            <button class="quick-btn" onclick="search('MSFT')">💻 MSFT</button>
            <button class="quick-btn" onclick="search('TSLA')">⚡ TSLA</button>
            <button class="quick-btn" onclick="search('AMZN')">📦 AMZN</button>
            <button class="quick-btn" onclick="search('NVDA')">🎮 NVDA</button>
            <button class="quick-btn" onclick="search('META')">👥 META</button>
        </div>

        <div class="search-box">
            <input 
                type="text" 
                id="symbol" 
                placeholder="พิมพ์สัญลักษณ์หุ้น เช่น AAPL, GOOGL, MSFT"
                value="AAPL"
            >
            <button onclick="search()">🔍 ค้นหา</button>
        </div>

        <div class="warning">
            ⚠️ <strong>หมายเหตุ:</strong> ข้อมูลนี้ใช้เพื่อการศึกษาเท่านั้น ไม่ใช่คำแนะนำทางการเงิน
        </div>

        <div id="loading" class="loading">
            <div class="spinner"></div>
            <p><strong>กำลังวิเคราะห์ข้อมูล...</strong></p>
        </div>

        <div id="error" class="error"></div>

        <div id="result" class="result">
            <!-- Price Card -->
            <div class="card">
                <h2 id="symbol-title">💰 AAPL - ราคาและสถิติ</h2>
                <div class="price-section">
                    <div>
                        <div style="color: #94a3b8; margin-bottom: 8px;">ราคาปัจจุบัน</div>
                        <div class="price-info">$<span id="price">0.00</span></div>
                    </div>
                    <div>
                        <div style="color: #94a3b8; margin-bottom: 8px;">การเปลี่ยนแปลง</div>
                        <div class="price-change" id="change">
                            ↑ +0.00 (+0.00%)
                        </div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-label">เปิด (Open)</div>
                        <div class="stat-value">$<span id="open">-</span></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">สูงสุด (High)</div>
                        <div class="stat-value">$<span id="high">-</span></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">ต่ำสุด (Low)</div>
                        <div class="stat-value">$<span id="low">-</span></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">ปิดก่อน (Prev Close)</div>
                        <div class="stat-value">$<span id="prev">-</span></div>
                    </div>
                </div>
            </div>

            <!-- Recommendation Card -->
            <div class="recommendation">
                <h2>💡 คำแนะนำการลงทุน</h2>
                <div class="rec-grid">
                    <div class="rec-item">
                        <div class="rec-label">คำแนะนำ</div>
                        <div class="rec-value" id="rec-badge">
                            <span class="badge buy">ซื้อ</span>
                        </div>
                    </div>
                    <div class="rec-item">
                        <div class="rec-label">ราคาเป้าหมาย</div>
                        <div class="rec-value">$<span id="target">-</span></div>
                    </div>
                </div>
                <div style="color: #cbd5e1; line-height: 1.7; margin-top: 12px; padding-top: 12px; border-top: 1px solid #334155;">
                    <strong style="color: #06b6d4;">เหตุผล:</strong> <span id="reason">-</span>
                </div>
            </div>

            <!-- Analysis -->
            <div class="card">
                <h2>📋 สรุปการวิเคราะห์</h2>
                <p class="info-content" id="summary">-</p>
            </div>

            <div class="card">
                <h2>⭐ จุดสำคัญ</h2>
                <ul class="list-items" id="keypoints"></ul>
            </div>

            <div class="card">
                <h2>📊 แนวโน้ม</h2>
                <p class="info-content" id="trends">-</p>
            </div>

            <div class="card">
                <h2>⚠️ ความเสี่ยง</h2>
                <ul class="list-items" id="risks"></ul>
            </div>

            <div class="card">
                <h2>🎯 ระดับราคา</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-label">🟢 แนวรับ (Support)</div>
                        <div class="stat-value">$<span id="support">-</span></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">🔴 แนวต้าน (Resistance)</div>
                        <div class="stat-value">$<span id="resistance">-</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p><strong>📊 Stock Analyzer Pro</strong></p>
            <p>ข้อมูลจาก Finnhub API + OpenAI</p>
            <p style="margin-top: 8px;">© 2024 - ใช้เพื่อการศึกษาเท่านั้น</p>
        </div>
    </div>

    <script>
        // ใช้ API เดิมที่มีอยู่แล้ว
        const API_URL = 'stock_analyzer_api_v2.php';

        async function search(symbol = null) {
            const sym = (symbol || document.getElementById('symbol').value).trim().toUpperCase();
            
            if (!sym) {
                showError('กรุณากรอกสัญลักษณ์หุ้น');
                return;
            }

            showLoading(true);
            hideError();

            try {
                const response = await fetch(`${API_URL}?symbol=${sym}`);
                
                // ตรวจสอบ content-type ก่อน
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new Error('API ไม่ตอบกลับเป็น JSON - กรุณาตรวจสอบว่าไฟล์ PHP ถูกอัพโหลดแล้ว');
                }
                
                const data = await response.json();

                if (data.error) {
                    showError(data.error);
                    showLoading(false);
                    return;
                }

                displayData(data);
            } catch (error) {
                showError('❌ เกิดข้อผิดพลาด: ' + error.message + '\n\nกรุณาตรวจสอบว่าไฟล์ stock_analyzer_api_secure.php ถูกอัพโหลดขึ้น server แล้ว');
                showLoading(false);
            }
        }

        function displayData(data) {
            const p = data.price_data;
            const a = data.analysis;

            // Symbol
            document.getElementById('symbol-title').textContent = `💰 ${data.symbol} - ราคาและสถิติ`;

            // Price
            document.getElementById('price').textContent = p.currentPrice.toFixed(2);
            
            const changeEl = document.getElementById('change');
            const isUp = p.change >= 0;
            changeEl.className = 'price-change ' + (isUp ? 'up' : 'down');
            changeEl.textContent = (isUp ? '↑ +' : '↓ ') + p.change.toFixed(2) + ' (' + (isUp ? '+' : '') + p.percent.toFixed(2) + '%)';

            // Stats
            document.getElementById('open').textContent = p.open ? p.open.toFixed(2) : '-';
            document.getElementById('high').textContent = p.high ? p.high.toFixed(2) : '-';
            document.getElementById('low').textContent = p.low ? p.low.toFixed(2) : '-';
            document.getElementById('prev').textContent = p.previousClose ? p.previousClose.toFixed(2) : '-';

            // Recommendation
            const rec = a.recommendation || 'ถือ';
            const badge = document.getElementById('rec-badge');
            let badgeClass = 'hold';
            if (rec.includes('ซื้อ') || rec.toLowerCase().includes('buy')) badgeClass = 'buy';
            else if (rec.includes('ขาย') || rec.toLowerCase().includes('sell')) badgeClass = 'sell';
            
            badge.innerHTML = '<span class="badge ' + badgeClass + '">' + rec + '</span>';
            document.getElementById('target').textContent = a.target_price || '-';
            document.getElementById('reason').textContent = a.reason || '-';

            // Analysis
            document.getElementById('summary').textContent = a.summary || '-';

            const kpList = document.getElementById('keypoints');
            kpList.innerHTML = '';
            if (a.keypoints && Array.isArray(a.keypoints)) {
                a.keypoints.forEach(point => {
                    const li = document.createElement('li');
                    li.textContent = point;
                    kpList.appendChild(li);
                });
            }

            document.getElementById('trends').textContent = a.trends || '-';

            const riskList = document.getElementById('risks');
            riskList.innerHTML = '';
            if (a.risks && Array.isArray(a.risks)) {
                a.risks.forEach(risk => {
                    const li = document.createElement('li');
                    li.textContent = risk;
                    riskList.appendChild(li);
                });
            }

            document.getElementById('support').textContent = a.support_level || '-';
            document.getElementById('resistance').textContent = a.resistance_level || '-';

            // Show result
            document.getElementById('result').classList.add('show');
            showLoading(false);
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showLoading(show) {
            document.getElementById('loading').classList.toggle('show', show);
            if (show) {
                document.getElementById('result').classList.remove('show');
            }
        }

        function showError(msg) {
            const el = document.getElementById('error');
            el.textContent = msg;
            el.classList.add('show');
            document.getElementById('result').classList.remove('show');
        }

        function hideError() {
            document.getElementById('error').classList.remove('show');
        }

        // Enter key
        document.getElementById('symbol').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') search();
        });

        // Auto load
        search();
    </script>
</body>
</html>
