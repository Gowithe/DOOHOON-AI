<?php
// -------------------- 🧠 DEBUG MODE --------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// -------------------- 🔑 API KEYS --------------------
$OPENAI_API_KEY   = getenv("OPENAI_API_KEY");
$FINNHUB_API_KEY  = getenv("FINNHUB_API_KEY");
$OPENAI_PROJECT_ID = getenv("OPENAI_PROJECT_ID");
$OPENAI_ORG_ID     = getenv("OPENAI_ORG_ID");

// -------------------- ⚙️ รับ symbol --------------------
$input = trim($_GET['symbol'] ?? 'AAPL');
$symbol = strtoupper($input);

// -------------------- 💰 ดึงราคาหุ้น --------------------
$finnhubUrl = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token={$FINNHUB_API_KEY}";
$finnhubResponse = @file_get_contents($finnhubUrl);

if (!$finnhubResponse) {
    echo json_encode(["error" => "❌ ไม่สามารถเชื่อมต่อ Finnhub API ได้"], JSON_UNESCAPED_UNICODE);
    exit;
}

$finnhubData = json_decode($finnhubResponse, true);
$currentPrice = $finnhubData['c'] ?? null;
$change = $finnhubData['d'] ?? null;
$percent = $finnhubData['dp'] ?? null;

if (!$currentPrice) {
    echo json_encode(["error" => "❌ ไม่พบข้อมูลราคาหุ้นนี้ กรุณาตรวจสอบสัญลักษณ์อีกครั้ง"], JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------- 🗞️ ดึงข่าวล่าสุด --------------------
$from = date('Y-m-d', strtotime('-7 days'));
$to   = date('Y-m-d');
$newsUrl = "https://finnhub.io/api/v1/company-news?symbol={$symbol}&from={$from}&to={$to}&token={$FINNHUB_API_KEY}";
$newsResponse = @file_get_contents($newsUrl);
$newsData = json_decode($newsResponse, true);

$latestNews = "";
if (!empty($newsData)) {
    foreach (array_slice($newsData, 0, 5) as $news) {
        $headline = $news['headline'] ?? '';
        $summary  = $news['summary'] ?? '';
        $source   = $news['source'] ?? '';
        $url      = $news['url'] ?? '';
        $date     = date('Y-m-d', $news['datetime'] ?? time());
        $latestNews .= "🗞️ [{$date}] {$headline} ({$source}) - {$summary}\n";
        if ($url) $latestNews .= "🔗 {$url}\n\n";
    }
} else {
    $latestNews = "ไม่มีข่าวสำคัญในช่วง 7 วันที่ผ่านมา";
}

// -------------------- 🤖 Prompt สำหรับ OpenAI --------------------
$prompt = "
คุณเป็นนักวิเคราะห์การลงทุนมืออาชีพ
โปรดสรุปข้อมูลหุ้น {$symbol} เป็นภาษาไทยโดยใช้ข้อมูลต่อไปนี้:

ราคาปัจจุบัน: {$currentPrice} USD (เปลี่ยนแปลง {$change} USD / {$percent}%)
ข่าวล่าสุด:
{$latestNews}

ให้วิเคราะห์ใน 8 หัวข้อดังนี้:
1. ข้อมูลบริษัทโดยย่อ  
2. โปรเจกต์สำคัญหรือนวัตกรรมเด่น  
3. แนวโน้มในอนาคต (ระยะสั้น / กลาง / ยาว)  
4. ปัจจัยเสี่ยงที่ควรระวัง  
5. ความเห็นของนักวิเคราะห์  
6. แนวรับ / แนวต้านโดยประมาณ  
7. ราคาเป้าหมายที่เหมาะสม  
8. คำแนะนำการลงทุน (ซื้อ / ถือ / ขาย พร้อมเหตุผล)  
";

// -------------------- 🔗 เรียก OpenAI API --------------------
$data = [
  "model" => "gpt-4o-mini",
  "messages" => [
    ["role" => "system", "content" => "คุณคือนักวิเคราะห์หลักทรัพย์ที่ให้ข้อมูลเป็นกลางและเข้าใจง่ายสำหรับนักลงทุนไทย"],
    ["role" => "user", "content" => $prompt]
  ],
  "temperature" => 0.8,
  "max_tokens" => 1800
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
  ]
]);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(["error" => "cURL Error: " . curl_error($ch)], JSON_UNESCAPED_UNICODE);
    exit;
}
curl_close($ch);
$result = json_decode($response, true);

// -------------------- ⚙️ ตรวจสอบผลลัพธ์ --------------------
if (!isset($result["choices"][0]["message"]["content"])) {
    echo json_encode(["error" => "❌ ไม่สามารถดึงข้อความจาก AI ได้", "raw" => $result], JSON_UNESCAPED_UNICODE);
    exit;
}

$aiContent = $result["choices"][0]["message"]["content"];

// -------------------- 🎨 จัดรูปแบบสวยงาม --------------------
$output = [
  "summary" => "
    <div style='padding:25px; border-radius:12px; background:rgba(0,0,0,0.5); border:1px solid rgba(255,215,0,0.3);'>
      <h3 style='color:#ffd700;'>📈 สรุปหุ้น {$symbol}</h3>
      <p style='color:#ccc;'>ราคาปัจจุบัน: <strong style='color:#fff;'>{$currentPrice} USD</strong> 
      <span style='color:" . ($change >= 0 ? '#00ff88' : '#ff6b6b') . ";'>
      " . ($change >= 0 ? '+' : '') . "{$change} USD, " . ($change >= 0 ? '+' : '') . "{$percent}%</span></p>
      <div style='white-space:pre-wrap; color:#f1f1f1; line-height:1.8;'>{$aiContent}</div>
    </div>
    <div style='margin-top:20px; background:rgba(255,215,0,0.08); padding:15px; border-radius:10px; border:1px solid rgba(255,215,0,0.2); color:#bbb; font-size:0.9em;'>
      ⚠️ <strong>หมายเหตุ:</strong> ข้อมูลนี้เป็นการวิเคราะห์โดย AI เพื่อใช้ประกอบการตัดสินใจเท่านั้น ผู้ลงทุนควรตรวจสอบข้อมูลจริงก่อนลงทุน
    </div>
  "
];

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>


