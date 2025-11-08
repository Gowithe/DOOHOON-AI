<?php
http_response_code(200); // บอก LINE ว่ารับแล้ว

$body = file_get_contents('php://input');
$data = json_decode($body, true);

// ตรวจสอบว่ามี event มาจริงไหม
if (!empty($data['events'])) {
    foreach ($data['events'] as $event) {
        if ($event['type'] === 'message' && $event['message']['type'] === 'text') {
            $text = $event['message']['text'];
            $replyToken = $event['replyToken'];

            // ✅ ส่งข้อความตอบกลับกลับไป
            sendLineReply($replyToken, "คุณพิมพ์ว่า: " . $text);
        }
    }
}

function sendLineReply($replyToken, $message) {
    $access_token = getenv('LINE_CHANNEL_TOKEN');
    $url = 'https://api.line.me/v2/bot/message/reply';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ];
    $postData = json_encode([
        'replyToken' => $replyToken,
        'messages' => [
            ['type' => 'text', 'text' => $message]
        ]
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
