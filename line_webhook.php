<?php
// line_webhook.php
header('Content-Type: application/json; charset=utf-8');

// โหลดค่า environment
$channelSecret = getenv('LINE_CHANNEL_SECRET');
$channelToken  = getenv('LINE_CHANNEL_TOKEN');

// Debug Log (เขียนลงไฟล์)
$logFile = __DIR__ . '/line_log.txt';
function writeLog($message) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . "\n", FILE_APPEND);
}

// ตรวจสอบว่า token มีหรือไม่
if (empty($channelSecret) || empty($channelToken)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Missing LINE credentials']);
    writeLog('❌ Missing LINE credentials');
    exit;
}

// รับข้อมูลจาก LINE
$input = file_get_contents('php://input');
if ($input === false || $input === '') {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'No body']);
    writeLog(⚠️ Empty body received');
    exit;
}

writeLog("📩 Received: " . $input);

// แปลง JSON เป็น array
$data = json_decode($input, true);
if (!isset($data['events'][0])) {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'No event']);
    writeLog('ℹ️ No event found');
    exit;
}

// ดึงข้อความที่ผู้ใช้ส่งมา
$event = $data['events'][0];
if (isset($event['message']['text'])) {
    $userText = trim($event['message']['text']);
    $replyToken = $event['replyToken'];

    // ตอบกลับข้อความกลับไปที่ผู้ใช้
    $responseText = "คุณพิมพ์ว่า: " . $userText;

    $replyData = [
        'replyToken' => $replyToken,
        'messages' => [[
            'type' => 'text',
            'text' => $responseText
        ]]
    ];

    $ch = curl_init('https://api.line.me/v2/bot/message/reply');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $channelToken
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($replyData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    writeLog("✅ Reply sent: " . $responseText);
}

http_response_code(200);
echo json_encode(['status' => 'success']);
?>
