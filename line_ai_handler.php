<?php
// ===============================
//  line_ai_handler.php (Final AI + Finnhub version)
// ===============================

function attemptHandleStockQuery($text, $replyToken, $userId = null) {
    $symbol = strtoupper(trim($text));
    if (!preg_match('/^[A-Z.]{1,10}$/', $symbol) && !str_contains(strtolower($text), 'สรุป')) {
        return false;
    }

    // ดึงข้อมูลหุ้นจาก Finnhub
    $finnhubKey = getenv('FINNHUB_API_KEY');
    $openaiKey  = getenv('OPENAI_API_KEY');

    if (!$finnhubKey || !$openaiKey) {
        sendLineReply($replyToken, [["type"=>"text","text"=>"❌ ยังไม่ได้ตั้งค่า API key ของ Finnhub หรือ OpenAI"]]);
        return true;
    }

    $symbol = extractSymbol($text);
    if (!$symbol) {
        sendLineReply($replyToken, [["type"=>"text","text"=>"กรุณาระบุชื่อหุ้น เช่น NVDA หรือ AAPL"]]);
        return true;
    }

    $quote = getFinnhubData("quote?symbol=$symbol", $finnhubKey);
    $profile = getFinnhubData("stock/profile2?symbol=$symbol", $finnhubKey);

    if (empty($profile['name'])) {
        sendLineReply($replyToken, [["type"=>"text","text"=>"❌ ไม่พบข้อมูลหุ้น $symbol"]]);
        return true;
    }

    $price = number_format($quote['c'] ?? 0, 2);
    $company = $profile['name'] ?? $symbol;
    $sector = $profile['finnhubIndustry'] ?? '-';

    // ใช้ OpenAI วิเคราะห์
    $prompt = "คุณคือผู้ช่วยวิเคราะห์หุ้นไทยและต่างประเทศ ช่วยสรุปข้อมูลหุ้น $company ($symbol)
โดยสรุปเป็น 9 หัวข้อ:
1. ข้อมูลบริษัท  
2. โปรเจกต์น่าจับตามอง  
3. แนวโน้มธุรกิจ  
4. ความเสี่ยง  
5. ราคาปัจจุบัน ($price USD)  
6. ราคาแนวรับ (ประมาณการจากแนวโน้มตลาด)  
7. ราคาเป้าหมาย (ประมาณการ 6-12 เดือน)  
8. คำแนะนำ (ซื้อ/ถือ/ขาย)  
9. ข่าวล่าสุดที่ส่งผลต่อราคา";

    $analysis = getOpenAIResponse($openaiKey, $prompt);

    $messages = explode("\n", trim($analysis));
    $replyMsgs = [];

    foreach ($messages as $msg) {
        if (trim($msg) !== "") {
            $replyMsgs[] = ["type" => "text", "text" => trim($msg)];
        }
    }

    sendLineReply($replyToken, $replyMsgs);
    return true;
}

function extractSymbol($text) {
    preg_match('/[A-Z]{2,6}/', strtoupper($text), $matches);
    return $matches[0] ?? null;
}

function getFinnhubData($endpoint, $apiKey) {
    $url = "https://finnhub.io/api/v1/$endpoint&token=$apiKey";
    $res = file_get_contents($url);
    return json_decode($res, true);
}

function getOpenAIResponse($apiKey, $prompt) {
    $url = "https://api.openai.com/v1/chat/completions";
    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [["role" => "user", "content" => $prompt]],
        "temperature" => 0.7,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json",
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);
    return $json["choices"][0]["message"]["content"] ?? "ไม่สามารถสรุปข้อมูลได้ในขณะนี้";
}

function sendLineReply($replyToken, $messages) {
    $access_token = getenv('LINE_CHANNEL_TOKEN');
    $url = "https://api.line.me/v2/bot/message/reply";
    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer " . $access_token
    ];
    $postData = json_encode([
        "replyToken" => $replyToken,
        "messages" => is_array($messages[0]) ? $messages : [["type"=>"text","text"=>$messages]]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_exec($ch);
    curl_close($ch);
}
?>
