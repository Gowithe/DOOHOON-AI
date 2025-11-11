<?php
// ================================================================================
// 📈 PROFESSIONAL STOCK ANALYZER API
// Stock Analysis with AI-Powered Insights for Thai Investors
// ================================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// ================================================================================
// 🔑 API KEYS & CONFIGURATION
// ================================================================================
$OPENAI_API_KEY    = getenv("OPENAI_API_KEY");
$FINNHUB_API_KEY   = getenv("FINNHUB_API_KEY");
$OPENAI_PROJECT_ID = getenv("OPENAI_PROJECT_ID");
$OPENAI_ORG_ID     = getenv("OPENAI_ORG_ID");

// Cache duration (in seconds)
$CACHE_DURATION = 3600;

// ================================================================================
// 🛡️ INPUT VALIDATION & SANITIZATION
// ================================================================================
function validateSymbol($symbol) {
    if (!preg_match('/^[A-Z0-9\-\.]{1,10}$/', strtoupper($symbol))) {
        return false;
    }
    return strtoupper($symbol);
}

// ================================================================================
// 💾 CACHE MANAGEMENT
// ================================================================================
function getCacheKey($symbol) {
    return md5("stock_analysis_{$symbol}");
}

function getCachedData($symbol) {
    $cacheFile = "/tmp/stock_cache_" . md5($symbol) . ".json";
    if (file_exists($cacheFile)) {
        $cacheTime = filemtime($cacheFile);
        if (time() - $cacheTime < 3600) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        @unlink($cacheFile);
    }
    return null;
}

function setCachedData($symbol, $data) {
    $cacheFile = "/tmp/stock_cache_" . md5($symbol) . ".json";
    file_put_contents($cacheFile, json_encode($data));
}

// ================================================================================
// 📊 FETCH STOCK DATA
// ================================================================================
function getStockData($symbol) {
    global $FINNHUB_API_KEY;
    
    $finnhubUrl = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token={$FINNHUB_API_KEY}";
    $finnhubResponse = @file_get_contents($finnhubUrl);
    
    if (!$finnhubResponse) {
        return ["error" => "❌ ไม่สามารถเชื่อมต่อ Finnhub API ได้"];
    }
    
    $data = json_decode($finnhubResponse, true);
    
    return [
        "currentPrice" => $data['c'] ?? null,
        "change" => $data['d'] ?? null,
        "percent" => $data['dp'] ?? null,
        "high" => $data['h'] ?? null,
        "low" => $data['l'] ?? null,
        "open" => $data['o'] ?? null,
        "volume" => $data['v'] ?? null,
        "previousClose" => $data['pc'] ?? null,
        "timestamp" => $data['t'] ?? null
    ];
}

// ================================================================================
// 🗞️ FETCH LATEST NEWS
// ================================================================================
function getCompanyNews($symbol) {
    global $FINNHUB_API_KEY;
    
    $from = date('Y-m-d', strtotime('-30 days'));
    $to   = date('Y-m-d');
    $newsUrl = "https://finnhub.io/api/v1/company-news?symbol={$symbol}&from={$from}&to={$to}&token={$FINNHUB_API_KEY}";
    $newsResponse = @file_get_contents($newsUrl);
    $newsData = json_decode($newsResponse, true);
    
    $latestNews = "";
    if (!empty($newsData)) {
        foreach (array_slice($newsData, 0, 8) as $news) {
            $headline = htmlspecialchars($news['headline'] ?? '');
            $summary  = htmlspecialchars($news['summary'] ?? '');
            $source   = htmlspecialchars($news['source'] ?? '');
            $url      = htmlspecialchars($news['url'] ?? '');
            $date     = date('Y-m-d', $news['datetime'] ?? time());
            $latestNews .= "📅 [{$date}] {$headline} ({$source})\n{$summary}\n";
            if ($url) $latestNews .= "🔗 {$url}\n\n";
        }
    } else {
        $latestNews = "ไม่มีข่าวในช่วง 30 วันที่ผ่านมา";
    }
    
    return $latestNews;
}

// ================================================================================
// 🤖 AI ANALYSIS ENGINE
// ================================================================================
function getAIAnalysis($symbol, $stockData, $news) {
    global $OPENAI_API_KEY, $OPENAI_PROJECT_ID, $OPENAI_ORG_ID;
    
    $currentPrice = $stockData['currentPrice'];
    $change = $stockData['change'];
    $percent = $stockData['percent'];
    
    if (!$currentPrice) {
        return ["error" => "❌ ไม่พบข้อมูลราคาหุ้นนี้ กรุณาตรวจสอบสัญลักษณ์"];
    }
    
    $prompt = "
คุณเป็นนักวิเคราะห์การลงทุนมืออาชีพที่มีประสบการณ์สูง

ให้สรุปข้อมูลหุ้น {$symbol} เป็นภาษาไทยโดยพิจารณาจากข้อมูลต่อไปนี้:

💰 ข้อมูลตลาด:
- ราคาปัจจุบัน: {$currentPrice} USD
- เปลี่ยนแปลง: {$change} USD ({$percent}%)
- High/Low: {$stockData['high']}/{$stockData['low']} USD
- Volume: " . ($stockData['volume'] ?? 'N/A') . "

📰 ข่าวล่าสุด:
{$news}

กรุณาวิเคราะห์และให้ข้อมูลในรูปแบบ JSON ที่มีส่วนต่อไปนี้:
{
  \"summary\": \"สรุปบริษัทโดยย่อ (2-3 บรรทัด)\",
  \"keypoints\": [\"จุดสำคัญ 1\", \"จุดสำคัญ 2\", \"จุดสำคัญ 3\"],
  \"trends\": \"แนวโน้มในอนาคต (ระยะสั้น/กลาง/ยาว)\",
  \"risks\": [\"ความเสี่ยง 1\", \"ความเสี่ยง 2\"],
  \"support_level\": \"แนวรับโดยประมาณ\",
  \"resistance_level\": \"แนวต้านโดยประมาณ\",
  \"target_price\": \"ราคาเป้าหมาย (ระบุระยะเวลา)\",
  \"recommendation\": \"ซื้อ/ถือ/ขาย\",
  \"reason\": \"เหตุผลการแนะนำ\"
}

ให้ตอบเป็น JSON เท่านั้น
";

    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "คุณคือนักวิเคราะห์หลักทรัพย์มืออาชีพที่ให้ข้อมูลเป็นกลางและเข้าใจง่ายสำหรับนักลงทุนไทย ตอบเป็น JSON เท่านั้น"],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.7,
        "max_tokens" => 2000
    ];
    
    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
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
        return ["error" => "❌ ไม่สามารถเรียก AI API ได้ (Code: {$httpCode})"];
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result["choices"][0]["message"]["content"])) {
        return ["error" => "❌ ไม่สามารถดึงข้อความจาก AI ได้"];
    }
    
    $aiContent = $result["choices"][0]["message"]["content"];
    
    // Clean JSON response
    $aiContent = preg_replace('/```json\n?/', '', $aiContent);
    $aiContent = preg_replace('/```\n?/', '', $aiContent);
    
    $analysis = json_decode($aiContent, true);
    return $analysis ?: json_decode($aiContent, true);
}

// ================================================================================
// 🎯 MAIN EXECUTION
// ================================================================================
$symbol = validateSymbol($_GET['symbol'] ?? 'AAPL');

if (!$symbol) {
    http_response_code(400);
    echo json_encode(["error" => "❌ สัญลักษณ์หุ้นไม่ถูกต้อง"], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check cache
$cached = getCachedData($symbol);
if ($cached) {
    echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Get fresh data
$stockData = getStockData($symbol);
if (isset($stockData['error'])) {
    http_response_code(400);
    echo json_encode($stockData, JSON_UNESCAPED_UNICODE);
    exit;
}

$news = getCompanyNews($symbol);
$analysis = getAIAnalysis($symbol, $stockData, $news);

if (isset($analysis['error'])) {
    http_response_code(500);
    echo json_encode($analysis, JSON_UNESCAPED_UNICODE);
    exit;
}

// Combine results
$output = [
    "symbol" => $symbol,
    "timestamp" => date('Y-m-d H:i:s'),
    "price_data" => $stockData,
    "analysis" => $analysis,
    "status" => "success"
];

// Cache the result
setCachedData($symbol, $output);

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
