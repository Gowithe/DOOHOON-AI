<?php
// ==============================================
// 🧠 DOOHOON-AI — PHP DEBUG + API READY 2025
// ==============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ----------------------------------------------
// 🔑 API KEYS (from Render Environment)
// ----------------------------------------------
$OPENAI_API_KEY = getenv("OPENAI_API_KEY");
$FINNHUB_API_KEY = getenv("FINNHUB_API_KEY");

// Debug Log File
$log_file = __DIR__ . '/debug_log.txt';
function debug_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// ----------------------------------------------
// 🧩 ตรวจสอบ Environment Variable
// ----------------------------------------------
if (!$OPENAI_API_KEY) {
    debug_log("❌ Missing OPENAI_API_KEY");
    echo json_encode(["error" => "Missing OPENAI_API_KEY in environment."], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!$FINNHUB_API_KEY) {
    debug_log("❌ Missing FINNHUB_API_KEY");
    echo json_encode(["error" => "Missing FINNHUB_API_KEY in environment."], JSON_UNESCAPED_UNICODE);
    exit;
}
debug_log("✅ Environment variables loaded successfully");

// ----------------------------------------------
// ⚙️ รับ symbol จากผู้ใช้
// ----------------------------------------------
$symbol = strtoupper(trim($_GET['symbol'] ?? 'AAPL'));
debug_log("🔍 Symbol received: $symbol");

// ----------------------------------------------
// 💰 ดึงราคาหุ้นจาก Finnhub
// ----------------------------------------------
$finnhubUrl = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token={$FINNHUB_API_KEY}";
$finnhubResponse = @file_get_contents($finnhubUrl);
// -------------------- 💰 ดึงราคาหุ้นจาก Finnhub --------------------
$finnhubUrl = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token={$FINNHUB_API_KEY}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $finnhubUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$finnhubResponse = curl_exec($ch);

if (curl_errno($ch)) {
    debug_log("❌ Finnhub cURL Error: " . curl_error($ch));
    echo json_encode(["error" => "ไม่สามารถเชื่อมต่อ Finnhub API ได้ (cURL error)"], JSON_UNESCAPED_UNICODE);
    exit;
}
curl_close($ch);

if (!$finnhubResponse) {
    debug_log("❌ Empty response from Finnhub for $symbol");
    echo json_encode(["error" => "ไม่สามารถเชื่อมต่อ Finnhub API ได้"], JSON_UNESCAPED_UNICODE);
    exit;
}

$finnhubData = json_decode($finnhubResponse, true);
$currentPrice = $finnhubData['c'] ?? null;
$change = $finnhubData['d'] ?? 0;
$percent = $finnhubData['dp'] ?? 0;

if (!$currentPrice) {
    debug_log("❌ Invalid Finnhub data: $finnhubResponse");
    echo json_encode(["error" => "ไม่พบข้อมูลราคาหุ้น กรุณาตรวจสอบสัญลักษณ์อีกครั้ง"], JSON_UNESCAPED_UNICODE);
    exit;
}
debug_log("✅ Stock data fetched: {$currentPrice} USD");

$currentPrice = $finnhubData['c'] ?? null;
$change = $finnhubData['d'] ?? 0;
$percent = $finnhubData['dp'] ?? 0;

if (!$currentPrice) {
    debug_log("❌ No stock data found for $symbol");
    echo json_encode(["error" => "ไม่พบข้อมูลราคาหุ้น กรุณาตรวจสอบสัญลักษณ์"], JSON_UNESCAPED_UNICODE);
    exit;
}
debug_log("✅ Stock data fetched: {$currentPrice} USD");

// ----------------------------------------------
// 🗞️ ดึงข่าวย้อนหลัง 7 วัน
// ----------------------------------------------
$from = date('Y-m-d', strtotime('-7 days'));
$to = date('Y-m-d');
$newsUrl = "https://finnhub.io/api/v1/company-news?symbol={$symbol}&from={$from}&to={$to}&token={$FINNHUB_API_KEY}";
$newsResponse = @file_get_contents($newsUrl);
$newsData = json_decode($newsResponse, true);

$latestNews = "";
if (!empty($newsData)) {
    $count = 0;
    foreach ($newsData as $news) {
        $headline = $news['headline'] ?? '';
        $url = $news['url'] ?? '';
        $source = $news['source'] ?? '';
        $date = date('Y-m-d', $news['datetime'] ?? time());
        if ($headline) {
            $latestNews .= "🗞️ [{$date}] {$headline} ({$source})\n";
            if ($url) $latestNews .= "🔗 {$url}\n\n";
            $count++;
        }
        if ($count >= 5) break;
    }
} else {
    $latestNews = "ไม่มีข่าวสำคัญในช่วง 7 วันที่ผ่านมา";
}
debug_log("✅ News summary prepared");

// ----------------------------------------------
// 🧠 สร้าง Prompt สำหรับ AI
// ----------------------------------------------
$prompt = "
คุณคือผู้เชี่ยวชาญด้านการลงทุน
วิเคราะห์หุ้น {$symbol} จากข้อมูลนี้:

ราคาปัจจุบัน: {$currentPrice} USD (เปลี่ยนแปลง {$change} USD / {$percent}%)
ข่าวล่าสุด:
{$latestNews}

กรุณาสรุปเป็นภาษาไทยแบบมืออาชีพ:
1. ข้อมูลบริษัท
2. โปรเจกต์ที่น่าจับตา
3. แนวโน้มระยะสั้น-กลาง-ยาว
4. ความเสี่ยง
5. ราคาเป้าหมาย
6. แนวรับ/แนวต้านโดยประมาณ
7. ความเห็นจากข่าว
8. คำแนะนำ (ซื้อ/ถือ/ขาย พร้อมเหตุผล)
9. สรุปภาพรวม
";

// ----------------------------------------------
// 🤖 เรียก OpenAI API (รองรับ proj-key)
// ----------------------------------------------
$openai_url = "https://api.openai.com/v1/chat/completions";
$data = [
    "model" => "gpt-4o-mini",
    "messages" => [
        ["role" => "system", "content" => "คุณคือนักวิเคราะห์หลักทรัพย์มืออาชีพ"],
        ["role" => "user", "content" => $prompt]
    ],
    "temperature" => 0.8,
    "max_tokens" => 1500
];

$headers = [
    "Content-Type: application/json",
    "Authorization: Bearer $OPENAI_API_KEY",
    "OpenAI-Organization: org-default",
    "OpenAI-Project: default"
];

$ch = curl_init($openai_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    $error = curl_error($ch);
    debug_log("❌ cURL Error: $error");
    echo json_encode(["error" => "cURL Error: $error"], JSON_UNESCAPED_UNICODE);
    exit;
}
curl_close($ch);

// ----------------------------------------------
// 📦 ตรวจสอบผลลัพธ์จาก AI
// ----------------------------------------------
$result = json_decode($response, true);
if (!isset($result["choices"][0]["message"]["content"])) {
    debug_log("❌ Invalid AI response: " . substr($response, 0, 200));
    echo json_encode(["error" => "ไม่สามารถดึงข้อมูลจาก AI ได้", "raw" => $response], JSON_UNESCAPED_UNICODE);
    exit;
}

$aiContent = $result["choices"][0]["message"]["content"];
debug_log("✅ AI response received successfully");

// ----------------------------------------------
// 🎨 แสดงผล
// ----------------------------------------------
echo json_encode([
    "symbol" => $symbol,
    "price" => $currentPrice,
    "change" => $change,
    "percent" => $percent,
    "summary" => nl2br($aiContent)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

debug_log("✅ Response sent successfully");

?>

