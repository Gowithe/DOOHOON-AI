<?php
// =============================================
// ✅ DOOHOON LINE BOT - MAIN WEBHOOK (Final)
// =============================================

require_once __DIR__ . '/line_ai_handler.php';

// ===============================
// ⚙️ Debug log helper
// ===============================
function logConsole($msg) {
    file_put_contents('php://stderr', "[" . date('H:i:s') . "] " . $msg . "\n");
}

// ===============================
// 📩 รับ Webhook จาก LINE
// ===============================
http_response_code(200); // ส่งกลับ 200 ให้ LINE เพื่อป้องกัน timeout

$body = file_get_contents('php://input');
$data = json_decode($body, true);

// log ข้อมูลดิบจาก LINE
logConsole("📩 Webhook received: " . $body);

if (!empty($data['events'])) {
    foreach ($data['events'] as $event) {
        $type = $event['type'] ?? '';

        // ตรวจสอบว่าเป็นข้อความไหม
        if ($type === 'message' && ($event['message']['type'] ?? '') === 'text') {

            $text       = trim($event['message']['text'] ?? '');
            $replyToken = $event['replyToken'] ?? '';
            $userId     = $event['source']['userId'] ?? null;

            logConsole("👤 User message: " . $text);

            try {
                // ✅ ตรวจว่าผู้ใช้พิมพ์เกี่ยวกับหุ้นไหม
                $handled = attemptHandleStockQuery($text, $replyToken, $userId);
                if ($handled) {
                    logConsole("✅ Stock handler triggered for " . $text);
                    continue;
                }

                // ✅ ตอบทักทายทั่วไป
                $lower = mb_strtolower($text);
                if (in_array($lower, ['สวัสดี', 'hello', 'hi'])) {
                    logConsole("🤖 Greeting detected");
                    sendLineReply($replyToken, [[
                        "type" => "text",
                        "text" => "สวัสดีครับ 😄 ผมคือ DOOHOON AI ผู้ช่วยด้านหุ้นของคุณ!\n\nพิมพ์ชื่อหุ้น เช่น NVDA, TSLA, O, AAPL เพื่อดูสรุปข่าวล่าสุดได้เลยครับ 📊"
                    ]]);
                    continue;
                }

                // ✅ คำสั่งช่วยเหลือ
                if (in_array($lower, ['help', 'ช่วยเหลือ', 'คู่มือ'])) {
                    sendLineReply($replyToken, [[
                        "type" => "text",
                        "text" => "🧠 คำสั่งที่ใช้ได้:\n\n• พิมพ์ชื่อหุ้น เช่น NVDA, O, AAPL, TSLA\n• พิมพ์ 'สรุปข่าว NVDA' เพื่อดูข่าวย่อ\n• พิมพ์ 'สวัสดี' เพื่อเริ่มสนทนา\n\nข้อมูลทั้งหมดอัปเดตแบบเรียลไทม์จากตลาดโลก 🌍"
                    ]]);
                    continue;
                }

                // ✅ กรณีทั่วไป (ไม่ตรงเงื่อนไขใด ๆ)
                logConsole("💬 Default reply");
                sendLineReply($replyToken, [[
                    "type" => "text",
                    "text" => "คุณพิมพ์ว่า: " . $text . "\n\n💡 พิมพ์ชื่อหุ้น เช่น NVDA หรือ TSLA เพื่อดูข่าวล่าสุดได้เลยครับ"
                ]]);

            } catch (Exception $e) {
                logConsole("❌ Exception: " . $e->getMessage());
                sendLineReply($replyToken, [[
                    "type" => "text",
                    "text" => "⚠️ เกิดข้อผิดพลาดระหว่างประมวลผล: " . $e->getMessage()
                ]]);
            }
        }
    }
}

// ===============================
// 📤 ฟังก์ชันส่งข้อความกลับ LINE
// ===============================
function sendLineReply($replyToken, $messages) {
    $access_token = getenv('LINE_CHANNEL_TOKEN');

    if (!$access_token) {
        logConsole("❌ Missing LINE_CHANNEL_TOKEN in environment variables");
        return;
    }

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

    if ($err) {
        logConsole("❌ LINE API error: " . $err);
    } else {
        logConsole("✅ LINE reply success: " . $result);
    }
}
?>
