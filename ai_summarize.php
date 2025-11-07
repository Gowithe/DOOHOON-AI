<?php
// -------------------- 🧠 DEBUG MODE --------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// -------------------- 🔑 API KEYS --------------------
$OPENAI_API_KEY = getenv("OPENAI_API_KEY");
$FINNHUB_API_KEY = getenv("FINNHUB_API_KEY");

// -------------------- ⚙️ รับ symbol --------------------
$input = trim($_GET['symbol'] ?? 'AAPL');
$symbol = strtoupper($input);

// -------------------- ✅ ตรวจสอบชื่อบริษัท / Symbol --------------------
if (!preg_match('/^[A-Z]{1,6}$/', $symbol)) {
    $searchUrl = "https://finnhub.io/api/v1/search?q={$symbol}&token={$FINNHUB_API_KEY}";
    $searchRes = @file_get_contents($searchUrl);
    $searchJson = json_decode($searchRes, true);
    if (!empty($searchJson['result'][0]['symbol'])) {
        $symbol = strtoupper($searchJson['result'][0]['symbol']);
    }
}

// -------------------- 💰 ดึงราคาหุ้น --------------------
$finnhubUrl = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token={$FINNHUB_API_KEY}";
$finnhubResponse = @file_get_contents($finnhubUrl);

if (!$finnhubResponse) {
    echo json_encode(["error" => "ไม่สามารถเชื่อมต่อ Finnhub API ได้"], JSON_UNESCAPED_UNICODE);
    exit;
}

$finnhubData = json_decode($finnhubResponse, true);
$currentPrice = $finnhubData['c'] ?? null;
$change = $finnhubData['d'] ?? null;
$percent = $finnhubData['dp'] ?? null;

if (!$currentPrice) {
  echo json_encode(["error" => "ไม่พบข้อมูลราคาหุ้นนี้ กรุณาตรวจสอบสัญลักษณ์อีกครั้ง"], JSON_UNESCAPED_UNICODE);
  exit;
}

// -------------------- 🗞️ ดึงข่าวล่าสุด (ย้อนหลัง 7 วัน) --------------------
$from = date('Y-m-d', strtotime('-7 days'));
$to   = date('Y-m-d');
$newsUrl = "https://finnhub.io/api/v1/company-news?symbol={$symbol}&from={$from}&to={$to}&token={$FINNHUB_API_KEY}";
$newsResponse = @file_get_contents($newsUrl);
$newsData = json_decode($newsResponse, true);

$latestNews = "";
if (!empty($newsData)) {
    $count = 0;
    foreach ($newsData as $news) {
        $headline = $news['headline'] ?? '';
        $summary  = $news['summary'] ?? '';
        $source   = $news['source'] ?? '';
        $url      = $news['url'] ?? '';
        $date     = date('Y-m-d', $news['datetime'] ?? time());

        if ($headline) {
            $latestNews .= "🗞️ [{$date}] {$headline} ({$source}) - {$summary}\n";
            if ($url) $latestNews .= "🔗 {$url}\n\n";
            $count++;
        }
        if ($count >= 5) break;
    }
} else {
    $latestNews = "ไม่มีข่าวสำคัญในช่วง 7 วันที่ผ่านมา";
}

// -------------------- 🤖 Prompt สำหรับ OpenAI --------------------
$prompt = "
คุณคือผู้เชี่ยวชาญด้านการลงทุน
โปรดวิเคราะห์หุ้น {$symbol} โดยใช้ข้อมูลต่อไปนี้:

ราคาปัจจุบัน: {$currentPrice} USD (เปลี่ยนแปลง {$change} USD / {$percent}%)
ข่าวล่าสุด:
{$latestNews}

สรุปใน 9 หัวข้อ (ภาษาไทยอย่างมืออาชีพ):
1. ข้อมูลบริษัท
2. โปรเจกต์น่าจับตามอง
3. แนวโน้ม (ระยะสั้น / กลาง / ยาว)
4. ความเสี่ยง
5. ราคาปัจจุบัน
6. ราคาแนวรับ (โดยประมาณ)
7. ราคาเป้าหมาย (โดยประมาณ)
8. คำแนะนำ (ซื้อ / ถือ / ขาย พร้อมเหตุผล)
9. สรุปข่าวล่าสุดที่มีผลต่อแนวโน้ม
";

$data = [
  "model" => "gpt-4o-mini",
  "messages" => [
    ["role" => "system", "content" => "คุณคือนักวิเคราะห์หลักทรัพย์มืออาชีพ ให้ข้อมูลเชิงลึกและเป็นกลาง"],
    ["role" => "user", "content" => $prompt]
  ],
  "temperature" => 0.8,
  "max_tokens" => 1800
];

// -------------------- 🔗 เรียก OpenAI API --------------------
$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Content-Type: application/json",
  "Authorization: Bearer $OPENAI_API_KEY"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
if (curl_errno($ch)) {
  echo json_encode(["error" => "cURL Error: " . curl_error($ch)], JSON_UNESCAPED_UNICODE);
  curl_close($ch);
  exit;
}
curl_close($ch);
$result = json_decode($response, true);

// -------------------- ⚙️ ตรวจสอบ JSON --------------------
if (!$result) {
  echo json_encode(["error" => "OpenAI ส่งข้อมูลไม่ถูกต้อง", "raw" => $response], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!isset($result["choices"][0]["message"]["content"])) {
  echo json_encode(["error" => "ไม่สามารถดึงข้อความจาก AI ได้", "raw" => $result], JSON_UNESCAPED_UNICODE);
  exit;
}

// -------------------- 🎨 แสดงผลแบบสวยงาม --------------------
$aiContent = $result["choices"][0]["message"]["content"];
$formatted = preg_replace_callback('/###\s*(.*?)\n(.*?)(?=\n###|$)/s', function ($m) {
  $title = trim($m[1]);
  $body = trim($m[2]);
  return "
    <div style='background:rgba(255,215,0,0.05); border:1px solid rgba(255,215,0,0.2); border-radius:10px; padding:18px 22px; margin-bottom:15px;'>
      <h4 style='color:#ffd700; margin-bottom:10px; font-weight:700;'>📌 {$title}</h4>
      <div style='color:#f1f1f1; line-height:1.8; font-size:1.05em;'>".nl2br($body)."</div>
    </div>
  ";
}, $aiContent);

$output = [
  "summary" => "
    <div style='padding:25px; border-radius:12px; background:rgba(0,0,0,0.5); border:1px solid rgba(255,215,0,0.3);'>
      <h3 style='color:#ffd700; margin-bottom:8px;'>📈 สรุปหุ้น {$symbol}</h3>
      <p style='color:#ccc; margin-bottom:15px; font-size:1.05em;'>
        ราคาปัจจุบัน: <strong style='color:#fff;'>{$currentPrice} USD</strong>
        <span style='color:#999;'> (</span>
        <span style='color:" . ($change >= 0 ? '#00ff88' : '#ff6b6b') . ";'>
          " . ($change >= 0 ? '+' : '') . "{$change} USD, " . ($change >= 0 ? '+' : '') . "{$percent}% 
        </span>
        <span style='color:#999;'>)</span>
      </p>
      {$formatted}
    </div>

    <div style='margin-top:20px; background:rgba(255,215,0,0.08); padding:15px; border-radius:10px; border:1px solid rgba(255,215,0,0.2); color:#bbb; font-size:0.9em;'>
      ⚠️ <strong>หมายเหตุ:</strong> ข้อมูลนี้จัดทำโดย AI เพื่อใช้ประกอบการตัดสินใจเท่านั้น 
      ผู้ลงทุนควรตรวจสอบข้อมูลล่าสุดก่อนการลงทุนจริง
    </div>
  "
];

// -------------------- 🧩 ตรวจสอบ JSON encode --------------------
$json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE | JSON_PRETTY_PRINT);
if (json_last_error() !== JSON_ERROR_NONE) {
  echo json_encode([
    "error" => "JSON encoding failed",
    "details" => json_last_error_msg()
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

echo $json;
?>

