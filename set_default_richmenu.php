<?php
// set_default_richmenu.php
header('Content-Type: application/json; charset=utf-8');

$LINE_CHANNEL_TOKEN = getenv("LINE_CHANNEL_TOKEN");
$richMenuId = $_GET['richMenuId'] ?? '';

if (empty($richMenuId)) {
    echo json_encode(['error' => 'กรุณาระบุ richMenuId'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ตั้งเป็น Default Rich Menu
$ch = curl_init("https://api.line.me/v2/bot/user/all/richmenu/{$richMenuId}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $LINE_CHANNEL_TOKEN
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode([
        'success' => true,
        'message' => '✅ Rich Menu ตั้งเป็น Default เรียบร้อย! ลองเปิด LINE Bot ของคุณดูได้เลย'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'success' => false,
        'error' => $response,
        'httpCode' => $httpCode
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>