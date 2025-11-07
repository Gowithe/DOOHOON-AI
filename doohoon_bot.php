<?php
// ----------------------
// DOOHOON AI LINE BOT
// ----------------------
header("Content-Type: application/json; charset=UTF-8");

// ใส่ LINE Channel Access Token ของพี่ตรงนี้ 👇
$ACCESS_TOKEN = "YOUR_LINE_CHANNEL_ACCESS_TOKEN"; // <== เปลี่ยนตรงนี้

// รับข้อมูลที่ LINE ส่งมา
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['events'][0])) {
    echo "no events";
    exit;
}

// ดึงข้อความที่ผู้ใช้พิมพ์
$userMessage = strtolower(trim($data['events'][0]['message']['text']));
$replyToken = $data['events'][0]['replyToken'];

// ----------------------
// ดึงข้อมูลจาก ai_summarize.php
// ----------------------
$apiUrl = "https://doohoon-ai.onrender.com/ai_summarize.php?symbol=" . urlencode($userMessage);
$response = @file_get_contents($apiUrl);
$result = json_decode($response, true);

// ตรวจสอบผลลัพธ์
if (isset($result['summary'])) {
    $replyText = "📊 สรุปหุ้น " . strtoupper($userMessage) . "\n\n" . strip_tags($result['summary']);
} else {
    $replyText = "❌ ไม่พบข้อมูลหุ้น '" . strtoupper($userMessage) . "' ลองใส่ชื่อหรือสัญลักษณ์ใหม่อีกครั้งครับ";
}

// ----------------------
// ส่งข้อความกลับไปที่ LINE
// ----------------------
$replyData = [
    'replyToken' => $replyToken,
    'messages' => [
        ['type' => 'text', 'text' => $replyText]
    ]
];

$ch = curl_init("https://api.line.me/v2/bot/message/reply");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer {$ACCESS_TOKEN}"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($replyData, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

// ส่งกลับเพื่อ LINE ตรวจสอบว่า Webhook ตอบ 200
echo json_encode(['status' => 'ok']);
?>
