<?php
// ================================================================================
// 📈 STOCK ANALYZER API - Simple Version
// ================================================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ================================================================================
// 🔑 API KEYS - HARDCODED (ง่ายสำหรับทดสอบ)
// ================================================================================
$OPENAI_API_KEY    = "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";
$FINNHUB_API_KEY   = "d46ntu1r01qgc9etnfngd46ntu1r01qgc9etnfo0";
$OPENAI_PROJECT_ID = "proj_92KLDoBqQc20XIoDthK6mQrG";
$OPENAI_ORG_ID     = "org-FCSQDR1fI5llIyCGUSVJKEpJ";

// ================================================================================
// 📊 GET SYMBOL
// ================================================================================
$symbol = trim($_GET['symbol'] ?? 'AAPL');
$symbol = strtoupper($symbol);

if (!preg_match('/^[A-Z0-9\-\.]{1,10}$/', $symbol)) {
    http_response_code(400);
    die(json_encode(["error" => "❌ สัญลักษณ์หุ้นไม่ถูกต้อง"], JSON_UNESCAPED_UNICODE));
}

// ================================================================================
// 💰 FETCH STOCK PRICE
// ================================================================================
function getStockPrice($symbol) {
    global $FINNHUB_API_KEY;
    
    $url = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token={$FINNHUB_API_KEY}";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) {
        return null;
    }
    
    return json_decode($response, true);
}

// ================================================================================
// 🗞️ FETCH NEWS
// ================================================================================
function getNews($symbol) {
    global $FINNHUB_API_KEY;
    
    $from = date('Y-m-d', strtotime('-30 days'));
    $to = date('Y-m-d');
    $url = "https://finnhub.io/api/v1/company-news?symbol={$symbol}&from={$from}&to={$to}&token={$FINNHUB_API_KEY}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $newsData = json_decode($response, true);
    $latestNews = "";
    
    if (!empty($newsData) && is_array($newsData)) {
        foreach (array_slice($newsData, 0, 5) as $news) {
            $headline = $news['headline'] ?? '';
            $summary = $news['summary'] ?? '';
            $source = $news['source'] ?? '';
            $latestNews .= "📅 {$headline} ({$source})\n{$summary}\n\n";
        }
    }
    
    return $latestNews ?: "ไม่มีข่าวเด่นในช่วง 30 วัน";
}

// ================================================================================
// 🤖 AI ANALYSIS
// ================================================================================
function getAIAnalysis($symbol, $price, $change, $percent, $news) {
    global $OPENAI_API_KEY, $OPENAI_PROJECT_ID, $OPENAI_ORG_ID;
    
    if (!$price) {
        return [
            "summary" => "ไม่สามารถดึงข้อมูลได้",
            "keypoints" => ["ตรวจสอบสัญลักษณ์หุ้นอีกครั้ง"],
            "trends" => "-",
            "risks" => ["ข้อมูลไม่พบ"],
            "support_level" => "-",
            "resistance_level" => "-",
            "target_price" => "-",
            "recommendation" => "ไม่สามารถวิเคราะห์",
            "reason" => "ไม่มีข้อมูล"
        ];
    }
    
    $prompt = "
โปรดวิเคราะห์หุ้น {$symbol} ที่มีราคา {$price} USD (เปลี่ยนแปลง {$change} USD, {$percent}%)

ข่าวล่าสุด:
{$news}

ให้ตอบเป็น JSON เท่านั้นในรูปแบบนี้:
{
  \"summary\": \"สรุปบริษัทสั้นๆ 1-2 ประโยค\",
  \"keypoints\": [\"จุดสำคัญ 1\", \"จุดสำคัญ 2\", \"จุดสำคัญ 3\"],
  \"trends\": \"แนวโน้มในอนาคต\",
  \"risks\": [\"ความเสี่ยง 1\", \"ความเสี่ยง 2\"],
  \"support_level\": \"ระดับรับสแนะนำ\",
  \"resistance_level\": \"ระดับต้านแนะนำ\",
  \"target_price\": \"ราคาเป้าหมาย (3-6 เดือน)\",
  \"recommendation\": \"ซื้อ/ถือ/ขาย\",
  \"reason\": \"เหตุผล 1-2 บรรทัด\"
}

สำคัญ: ตอบเป็น JSON ที่ถูกต้องเท่านั้น ไม่มีข้อความเพิ่มเติม
";

    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "คุณคือนักวิเคราะห์หลักทรัพย์มืออาชีพ ตอบเป็น JSON เท่านั้น"],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.7,
        "max_tokens" => 1000
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer $OPENAI_API_KEY",
            "OpenAI-Project: $OPENAI_PROJECT_ID",
            "OpenAI-Organization: $OPENAI_ORG_ID"
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return [
            "summary" => "AI Analysis ไม่สามารถใช้งานได้ (HTTP {$httpCode})",
            "keypoints" => ["ลองอีกครั้งในภายหลัง"],
            "trends" => "-",
            "risks" => ["ไม่สามารถโหลด"],
            "support_level" => "-",
            "resistance_level" => "-",
            "target_price" => "-",
            "recommendation" => "ถือ",
            "reason" => "ระบบวิเคราะห์ไม่สามารถใช้ได้ชั่วคราว"
        ];
    }

    $result = json_decode($response, true);
    
    if (!isset($result["choices"][0]["message"]["content"])) {
        return [
            "summary" => "ไม่สามารถเรียก AI ได้",
            "keypoints" => ["ลองใหม่อีกครั้ง"],
            "trends" => "-",
            "risks" => ["API Error"],
            "support_level" => "-",
            "resistance_level" => "-",
            "target_price" => "-",
            "recommendation" => "ถือ",
            "reason" => "ระบบไม่ตอบสนอง"
        ];
    }

    $aiResponse = $result["choices"][0]["message"]["content"];
    
    // Clean up JSON
    $aiResponse = preg_replace('/```json\s*/i', '', $aiResponse);
    $aiResponse = preg_replace('/```\s*/i', '', $aiResponse);
    $aiResponse = trim($aiResponse);
    
    $analysis = json_decode($aiResponse, true);
    
    if (!is_array($analysis)) {
        $analysis = [
            "summary" => "ไม่สามารถแยกผลการวิเคราะห์ได้",
            "keypoints" => ["ข้อมูลจาก AI ไม่ชัดเจน"],
            "trends" => "-",
            "risks" => ["ข้อมูลไม่สมบูรณ์"],
            "support_level" => "-",
            "resistance_level" => "-",
            "target_price" => "-",
            "recommendation" => "ถือ",
            "reason" => "ไม่สามารถแยก JSON ได้"
        ];
    }
    
    return $analysis;
}

// ================================================================================
// 🎯 MAIN EXECUTION
// ================================================================================
try {
    // Get price
    $priceData = getStockPrice($symbol);
    
    if (!$priceData || !isset($priceData['c'])) {
        http_response_code(400);
        die(json_encode(["error" => "❌ ไม่พบข้อมูลหุ้น: {$symbol}"], JSON_UNESCAPED_UNICODE));
    }
    
    $price = $priceData['c'];
    $change = $priceData['d'] ?? 0;
    $percent = $priceData['dp'] ?? 0;
    $high = $priceData['h'] ?? null;
    $low = $priceData['l'] ?? null;
    $open = $priceData['o'] ?? null;
    $prevClose = $priceData['pc'] ?? null;
    $volume = $priceData['v'] ?? null;
    
    // Get news
    $news = getNews($symbol);
    
    // Get AI analysis
    $analysis = getAIAnalysis($symbol, $price, $change, $percent, $news);
    
    // Return result
    $output = [
        "symbol" => $symbol,
        "timestamp" => date('Y-m-d H:i:s'),
        "price_data" => [
            "currentPrice" => $price,
            "change" => $change,
            "percent" => $percent,
            "high" => $high,
            "low" => $low,
            "open" => $open,
            "previousClose" => $prevClose,
            "volume" => $volume
        ],
        "analysis" => $analysis,
        "status" => "success"
    ];
    
    echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "❌ เกิดข้อผิดพลาด: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>