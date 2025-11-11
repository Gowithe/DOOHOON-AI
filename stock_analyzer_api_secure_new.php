<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function loadEnv($path = '.env') {
    if (!file_exists($path)) return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), '"\'');
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
    return true;
}

loadEnv(__DIR__ . '/.env');

$OPENAI_API_KEY  = getenv('OPENAI_API_KEY') ?: '';
$FINNHUB_API_KEY = getenv('FINNHUB_API_KEY') ?: 'd46ntu1r01qgc9etnfngd46ntu1r01qgc9etnfo0';
$USE_AI = !empty($OPENAI_API_KEY) && $OPENAI_API_KEY !== 'your_openai_api_key_here';

$symbol = strtoupper(trim($_GET['symbol'] ?? 'AAPL'));
if (!preg_match('/^[A-Z0-9\-\.]{1,10}$/', $symbol)) {
    http_response_code(400);
    die(json_encode(["error" => "❌ สัญลักษณ์หุ้นไม่ถูกต้อง"], JSON_UNESCAPED_UNICODE));
}

function getStockPrice($symbol) {
    global $FINNHUB_API_KEY;
    $url = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token={$FINNHUB_API_KEY}";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($response && $httpCode === 200) ? json_decode($response, true) : null;
}

function getNews($symbol) {
    global $FINNHUB_API_KEY;
    $from = date('Y-m-d', strtotime('-30 days'));
    $to = date('Y-m-d');
    $url = "https://finnhub.io/api/v1/company-news?symbol={$symbol}&from={$from}&to={$to}&token={$FINNHUB_API_KEY}";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
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

function getAIAnalysis($symbol, $price, $change, $percent, $news) {
    global $OPENAI_API_KEY, $USE_AI;
    if (!$price) return getBasicAnalysis($symbol, 0, 0, 0);
    if (!$USE_AI) return getBasicAnalysis($symbol, $price, $change, $percent);
    
    $prompt = "วิเคราะห์หุ้น {$symbol} ที่มีราคา {$price} USD (เปลี่ยนแปลง {$change} USD, {$percent}%)\n\nข่าวล่าสุด:\n{$news}\n\nให้ตอบเป็น JSON เท่านั้น:\n{\"summary\":\"สรุป\",\"keypoints\":[\"จุด1\",\"จุด2\"],\"trends\":\"แนวโน้ม\",\"risks\":[\"เสี่ยง1\"],\"support_level\":\"รับ\",\"resistance_level\":\"ต้าน\",\"target_price\":\"เป้าหมาย\",\"recommendation\":\"ซื้อ/ถือ/ขาย\",\"reason\":\"เหตุผล\"}";
    
    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "คุณคือนักวิเคราะห์หุ้น ตอบเป็น JSON เท่านั้น"],
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
            "Authorization: Bearer {$OPENAI_API_KEY}"
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return getBasicAnalysis($symbol, $price, $change, $percent);

    $result = json_decode($response, true);
    if (!isset($result["choices"][0]["message"]["content"])) {
        return getBasicAnalysis($symbol, $price, $change, $percent);
    }

    $aiResponse = preg_replace('/```json\s*/i', '', $result["choices"][0]["message"]["content"]);
    $aiResponse = preg_replace('/```\s*/i', '', $aiResponse);
    $analysis = json_decode(trim($aiResponse), true);
    
    return is_array($analysis) ? $analysis : getBasicAnalysis($symbol, $price, $change, $percent);
}

function getBasicAnalysis($symbol, $price, $change, $percent) {
    if ($price == 0) {
        return [
            "summary" => "ไม่พบข้อมูลหุ้น {$symbol}",
            "keypoints" => ["ตรวจสอบสัญลักษณ์หุ้นอีกครั้ง"],
            "trends" => "ไม่มีข้อมูล",
            "risks" => ["ข้อมูลไม่พบ"],
            "support_level" => "-",
            "resistance_level" => "-",
            "target_price" => "-",
            "recommendation" => "ไม่สามารถวิเคราะห์",
            "reason" => "ไม่มีข้อมูล"
        ];
    }
    
    $isPositive = $change >= 0;
    $momentum = abs($percent) > 2 ? "แรง" : "ปานกลาง";
    $support = round($price * 0.95, 2);
    $resistance = round($price * 1.05, 2);
    $target = round($price * 1.10, 2);
    
    if ($percent > 3) {
        $rec = "ถือ - รอปรับฐาน";
        $reason = "ราคาปรับตัวขึ้นแรง อาจมีการปรับฐานในระยะสั้น";
    } elseif ($percent > 1) {
        $rec = "ซื้อ - แนวโน้มบวก";
        $reason = "ราคาเคลื่อนไหวในทิศทางบวก มีโมเมนตัม";
    } elseif ($percent > -1) {
        $rec = "ถือ";
        $reason = "ราคาเคลื่อนไหวในกรอบแคบ รอสัญญาณชัดเจน";
    } elseif ($percent > -3) {
        $rec = "พิจารณาซื้อ";
        $reason = "ราคาอ่อนตัวเล็กน้อย อาจเป็นจังหวะเข้าซื้อ";
    } else {
        $rec = "ระวัง";
        $reason = "ราคาอ่อนตัวมาก ควรรอสัญญาณฟื้นตัว";
    }
    
    return [
        "summary" => "{$symbol} มีราคาปัจจุบันที่ \${$price} เคลื่อนไหว" . ($isPositive ? "เพิ่มขึ้น" : "ลดลง") . " {$percent}%",
        "keypoints" => [
            "ราคาปัจจุบัน: \${$price}",
            "การเปลี่ยนแปลง: " . ($isPositive ? "+" : "") . "{$change} ({$percent}%)",
            "โมเมนตัม: {$momentum}"
        ],
        "trends" => "แนวโน้ม" . ($isPositive ? "ขาขึ้น" : "ขาลง") . " โมเมนตัม{$momentum}",
        "risks" => ["ความผันผวนของตลาด", "ปัจจัยเศรษฐกิจมหภาค", "ข่าวสารของบริษัท"],
        "support_level" => (string)$support,
        "resistance_level" => (string)$resistance,
        "target_price" => (string)$target,
        "recommendation" => $rec,
        "reason" => $reason
    ];
}

try {
    $priceData = getStockPrice($symbol);
    if (!$priceData || !isset($priceData['c']) || $priceData['c'] == 0) {
        http_response_code(400);
        die(json_encode(["error" => "❌ ไม่พบข้อมูลหุ้น: {$symbol}"], JSON_UNESCAPED_UNICODE));
    }
    
    $price = $priceData['c'];
    $change = $priceData['d'] ?? 0;
    $percent = $priceData['dp'] ?? 0;
    $news = getNews($symbol);
    $analysis = getAIAnalysis($symbol, $price, $change, $percent, $news);
    
    echo json_encode([
        "symbol" => $symbol,
        "timestamp" => date('Y-m-d H:i:s'),
        "price_data" => [
            "currentPrice" => $price,
            "change" => $change,
            "percent" => $percent,
            "high" => $priceData['h'] ?? null,
            "low" => $priceData['l'] ?? null,
            "open" => $priceData['o'] ?? null,
            "previousClose" => $priceData['pc'] ?? null
        ],
        "analysis" => $analysis,
        "ai_enabled" => $USE_AI,
        "status" => "success"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "❌ เกิดข้อผิดพลาด: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
