<?php
// ===============================
// DOOHOON LINE BOT (Final version)
// ===============================

require_once __DIR__ . '/line_ai_handler.php';

http_response_code(200);

// อ่าน body ที่มาจาก LINE Webhook
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!empty($data['events'])) {
    foreach ($data['events'] as $event) {
        $type = $event['type'] ?? '';
        if ($type === 'message' && ($event['message']['type'] ?? '') === 'text') {
            $text       = trim($event['message']['text'] ?? '');
            $replyToken = $event['replyToken'] ?? '';
            $userId     = $event['source']['userId'] ?? null;

            // ✅ ลองให้ AI จัดการก่อน
            $handled = attemptHandleStockQuery($text, $replyToken, $userId);
            if ($handled) {
                continue;
            }

            // ✅ ถ้าไม่เข้าเงื่อนไขหุ้น ให้ตอบข้อความทั่วไป
            if (in_array(mb_strtolower($text), ['สวัสดี', 'hello', 'hi'])) {
                sendLineReply($replyToken, [["type" => "text", "text" => "สวัสดีครับ 😄 ผมคือบอทผู้ช่วยด้านหุ้น! พิมพ์ชื่อหุ้นเช่น NVDA หรือ TSLA เพื่อดูสรุปข่าวได้เลยครับ"]]);
            } else {
                sendLineReply($replyToken, [["type" => "text", "text" => "คุณพิมพ์ว่า: " . $text]]);
            }
        }
    }
}

// ===============================
// ฟังก์ชันส่งข้อความกลับไป LINE
// ===============================
function sendLineReply($replyToken, $messages) {
    $access_token = getenv('LINE_CHANNEL_TOKEN');
    $url = "https://api.line.me/v2/bot/message/reply";
    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer " . $access_token
    ];

    $postData = json_encode([
        "replyToken" => $replyToken,
        "messages" => $messages
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $result = curl_exec($ch);
    curl_close($ch);
}
?>
