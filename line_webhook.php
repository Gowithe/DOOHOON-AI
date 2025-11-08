<?php
// line_webhook.php — DOOHOON AI LINE Bot
header('Content-Type: application/json; charset=utf-8');

// ====== ENVIRONMENT VARIABLES ======
$channelSecret   = getenv('LINE_CHANNEL_SECRET');
$channelToken    = getenv('LINE_CHANNEL_TOKEN');
$openaiApiKey    = getenv('OPENAI_API_KEY');
$finnhubApiKey   = getenv('FINNHUB_API_KEY');

// ====== DEBUG LOG FUNCTION ======
$logFile = __DIR__ . '/line_log.txt';
function logMsg($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

// ====== LINE EVENT HANDLER ======
$input = file_get_contents('php://input');
if (!$input) {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

logMsg("📩 Input: " . $input);
$data = json_decode($input, true);

// ตรวจสอบว่าเป็น event message หรือไม่
if (!isset($data['events'][0])) {
    http_response_code(200);
    echo json_encode(['status' => 'no_event']);
    exit;
}

$event = $data['events'][0];
if ($event['type'] !== 'message' || $event['message']['type'] !== 'text') {
    http_response_code(200);
    echo json_encode(['status' => 'ignored']);
    exit;
}

$userText = trim($event['message']['text']);
$replyToken = $event['replyToken'];
$replyMsg = "";

// ====== 1️⃣ ตรวจสอบว่าผู้ใช้พิมพ์ชื่อหุ้นหรือไม่ ======
if (preg_match('/^[A-Za-z]{1,5}$/', $userText)) {
    $symbol = strtoupper($userText);
    $finnhubUrl = "https://finnhub.io/api/v1/quote?symbol=$symbol&token=$finnhubApiKey";
    $response = file_get_contents($finnhubUrl);
    $data = json_decode($response, true);

    if (isset($data['c'])) {
        $current = $data['c'];
        $change = $data['d'];
        $percent = $data['dp'];
        $emoji = $change >= 0 ? "🟢" : "🔻";
        $replyMsg = "$emoji ราคาหุ้น $symbol ปัจจุบัน: $current USD\n";
        $replyMsg .= "การเปลี่ยนแปลง: " . number_format($change, 2) . " USD (" . number_format($percent, 2) . "%)";
    } else {
        $replyMsg = "❌ ไม่พบข้อมูลหุ้น $symbol";
    }
}

// ====== 2️⃣ ถ้าไม่ใช่ชื่อหุ้น → ใช้ OpenAI ======
else {
    $prompt = "คุณคือผู้ช่วยอัจฉริยะของระบบ DOOHOON AI ช่วยตอบข้อความนี้เป็นภาษาไทย:\n" . $userText;

    $payload = [
        "model" => "gpt-4o-mini",
        "messages" => [["role" => "user", "content" => $prompt]],
        "temperature" => 0.7,
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $openaiApiKey"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $result = curl_exec($ch);
    curl_close($ch);

    $resData = json_decode($result, true);
    if (isset($resData['choices'][0]['message']['content'])) {
        $replyMsg = $resData['choices'][0]['message']['content'];
    } else {
        $replyMsg = "⚠️ ระบบ AI มีปัญหาชั่วคราว กรุณาลองใหม่อีกครั้งครับ";
    }
}

// ====== 3️⃣ ส่งข้อความกลับ LINE ======
if (!empty($replyMsg)) {
    $replyData = [
        'replyToken' => $replyToken,
        'messages' => [[
            'type' => 'text',
            'text' => $replyMsg
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

    logMsg("✅ Reply Sent: " . $replyMsg);
}

http_response_code(200);
echo json_encode(['status' => 'success']);
?>
