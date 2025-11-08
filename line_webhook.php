<?php
// ------------------------------------------------------------
// 🌐 LINE Webhook (เวอร์ชันต่อกับ DOOHOON AI Stock Analyzer)
// ------------------------------------------------------------

// ✅ เปิด error ให้เห็นชัดตอน debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ ตั้ง header
header("Content-Type: application/json; charset=UTF-8");

// ✅ โหลดไฟล์ AI Stock Handler
require_once __DIR__ . '/line_ai_handler.php';

// ✅ ดึง token ของ LINE จาก environment variables
$channelAccessToken = getenv("LINE_CHANNEL_TOKEN");
$channelSecret = getenv("LINE_CHANNEL_SECRET");

if (!$channelAccessToken) {
    echo json_encode(["error" => "❌ Missing LINE_CHANNEL_TOKEN environment variable"]);
    exit;
}

// ✅ ฟังก์ชันส่งข้อความกลับ (Reply)
function sendLineReply($replyToken, $messages)
{
    $accessToken = getenv("LINE_CHANNEL_TOKEN");
    $url = "https://api.line.me/v2/bot/message/reply";

    $data = [
        "replyToken" => $replyToken,
        "messages" => $messages
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    $result = curl_exec($ch);
    curl_close($ch);
}

// ✅ ฟังก์ชันส่งข้อความแบบ Push (ใช้ตอนแบ่งข้อความยาว)
function sendLinePush($to, $messages)
{
    $accessToken = getenv("LINE_CHANNEL_TOKEN");
    $url = "https://api.line.me/v2/bot/message/push";

    $data = [
        "to" => $to,
        "messages" => $messages
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    $result = curl_exec($ch);
    curl_close($ch);
}

// ✅ อ่านข้อมูลจาก LINE Webhook
$body = file_get_contents('php://input');
$data = json_decode($body, true);

// ✅ บันทึก log (เผื่อ debug ทีหลัง)
file_put_contents("line_log.txt", date("Y-m-d H:i:s") . "\n" . $body . "\n\n", FILE_APPEND);

// ✅ ตรวจว่ามี event จากผู้ใช้ไหม
if (!empty($data['events'])) {
    foreach ($data['events'] as $event) {
        $type = $event['type'] ?? '';
        if ($type === 'message' && ($event['message']['type'] ?? '') === 'text') {
            $text = trim($event['message']['text'] ?? '');
            $replyToken = $event['replyToken'] ?? '';
            $userId = $event['source']['userId'] ?? null;

            // 🧠 วิเคราะห์หุ้น (OpenAI + Finnhub)
            $handled = attemptHandleStockQuery($text, $replyToken, $userId);
            if ($handled) {
                // ถ้าจัดการแล้ว จบการทำงานได้เลย
                continue;
            }

            // 💬 ตอบข้อความทั่วไป
            $lower = mb_strtolower($text, 'UTF-8');
            if (in_array($lower, ['สวัสดี', 'hello', 'hi'])) {
                sendLineReply($replyToken, [
                    ["type" => "text", "text" => "สวัสดีครับ 👋 ยินดีต้อนรับสู่ DOOHOON AI นักลงทุนคู่ใจคุณครับ"]
                ]);
            } elseif (strpos($lower, 'แนวโน้ม') !== false) {
                sendLineReply($replyToken, [
                    ["type" => "text", "text" => "📊 แนวโน้มตลาดวันนี้ดูผันผวนเล็กน้อยครับ นักลงทุนควรจับตาข่าวเศรษฐกิจสหรัฐ และผลประกอบการบริษัทใหญ่ ๆ"]
                ]);
            } elseif (strpos($lower, 'หุ้นแนะนำ') !== false) {
                sendLineReply($replyToken, [
                    ["type" => "text", "text" => "💡 หุ้นแนะนำวันนี้:\n1️⃣ NVDA – ผู้นำตลาดชิป AI\n2️⃣ MSFT – เทคโนโลยีเติบโตต่อเนื่อง\n3️⃣ O – หุ้นปันผลรายเดือนที่แข็งแกร่ง"]
                ]);
            } else {
                // ตอบ fallback ถ้าไม่เข้าเงื่อนไข
                sendLineReply($replyToken, [
                    ["type" => "text", "text" => "พิมพ์ชื่อหุ้น (เช่น NVDA, AAPL, META) เพื่อดูการวิเคราะห์ครับ 🔍"]
                ]);
            }
        }
    }
}

// ✅ ส่ง response OK กลับให้ LINE
echo json_encode(["status" => "ok"]);
