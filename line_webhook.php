<?php
// ===============================
// DOOHOON LINE BOT (Debug version)
// ===============================

require_once __DIR__ . '/line_ai_handler.php';

// สำหรับ debug log บน Render
function logConsole($msg) {
    file_put_contents('php://stderr', "[" . date('H:i:s') . "] " . $msg . "\n");
}

http_response_code(200);
$body = file_get_contents('php://input');
$data = json_decode($body, true);

logConsole("📩 Webhook received: " . $body);

if (!empty($data['events'])) {
    foreach ($data['events'] as $event) {
        $type = $event['type'] ?? '';
        if ($type === 'message' && ($event['message']['type'] ?? '') === 'text') {
            $text       = trim($event['message']['text'] ?? '');
            $replyToken = $event['replyToken'] ?? '';
            $userId     = $event['source']['userId'] ?? null;

            logConsole("👤 User message: $text");

            // ✅ ลองให้ AI จัดการก่อน
            try {
                $handled = attemptHandleStockQuery($text, $replyToken, $userId);
                if ($handled) {
                    logConsole("✅ Stock handler triggered for $text");
                    continue;
                }
            } catch (Exception $e) {
                logConsole("❌ Error in attemptHandleStockQuery: " . $e->getMessage());
                sendLineReply($replyToken, [["type"=>"text","text"=>"❌ เกิดข้อผิดพลาดในการประมวลผล: ".$e->getMessage()]]);
                continue;
            }

            // ✅ ถ้าไม่เข้าเงื่อนไขหุ้น ให้ตอบข้อความทั่วไป
            if (in_array(mb_strtolower($text), ['สวัสดี', 'hello', 'hi'])) {
                logConsole("🤖 Greeting detected");
                sendLineReply($replyToken, [["type" => "text", "text" => "สวัสดีครับ 😄 ผมคือบอทผู้ช่วยด้านหุ้น! พิมพ์ชื่อหุ้นเช่น NVDA หรือ TSLA เพื่อดูสรุปข่าวได้เลยครับ"]]);
            } else {
                logConsole("💬 Default reply");
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
    $err = curl_error($ch);
    curl_close($ch);

    file_put_contents('php://stderr', "[LINE-REPLY] " . ($err ?: "Success") . "\n");
}
?>
