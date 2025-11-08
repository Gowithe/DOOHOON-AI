<?php
// =============================================
// ✅ DOOHOON LINE AI HANDLER (FINAL FIXED)
// =============================================

function attemptHandleStockQuery($text, $replyToken, $userId) {
    $keywords = ['NVDA', 'TSLA', 'AAPL', 'MSFT', 'GOOGL', 'O', 'AMZN', 'META', 'INTC', 'AMD'];
    $pattern = '/(' . implode('|', array_map('preg_quote', $keywords)) . ')/i';

    if (preg_match($pattern, $text, $matches)) {
        $symbol = strtoupper($matches[1]);
        $summary = summarizeStockNews($symbol);

        if ($summary) {
            sendLineReply($replyToken, [["type" => "text", "text" => $summary]]);
            return true;
        } else {
            sendLineReply($replyToken, [["type" => "text", "text" => "❌ ไม่พบข้อมูลหุ้น $symbol ในตอนนี้ครับ"]]);
            return true;
        }
    }
    return false;
}

// =============================================
// ✅ ดึงข่าวหุ้นจาก Finnhub + สรุปด้วย OpenAI
// =============================================
function summarizeStockNews($symbol) {
    $finnhubKey = getenv('FINNHUB_API_KEY');
    $openaiKey  = getenv('OPENAI_API_KEY');

    if (!$finnhubKey || !$openaiKey) {
        return "❌ ยังไม่ได้ตั้งค่า API key ของ Finnhub หรือ OpenAI ใน Render Environment ครับ";
    }

    // 📡 ดึงข่าวจาก Finnhub (ใช้ cURL แทน file_get_contents)
    $url = "https://finnhub.io/api/v1/company-news?symbol={$symbol}&from=" . date('Y-m-d', strtotime('-5 days')) . "&to=" . date('Y-m-d') . "&token={$finnhubKey}";
    file_put_contents('php://stderr', "[AI] Fetching news for {$symbol} from Finnhub\n");

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    $newsData = json_decode($response, true);

    if (!$newsData || count($newsData) == 0) {
        return "❌ ไม่พบข่าวล่าสุดของหุ้น {$symbol} ครับ";
    }

    $newsList = array_slice($newsData, 0, 5);
    $newsText = "";
    foreach ($newsList as $n) {
        $title = $n['headline'] ?? '';
        $summary = $n['summary'] ?? '';
        $source = $n['source'] ?? '';
        $newsText .= "- {$title} ({$source}) — {$summary}\n";
    }

    $prompt = "สรุปข่าวหุ้น {$symbol} จากข้อมูลต่อไปนี้ให้เข้าใจง่ายใน 9 ข้อ bullet points พร้อมน้ำเสียงเหมือนนักวิเคราะห์ตลาดหุ้นไทย:\n\n{$newsText}";

    // ✨ สรุปข่าวด้วย OpenAI
    $openaiUrl = "https://api.openai.com/v1/chat/completions";
    $postData = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "You are a professional Thai financial analyst who summarizes stock news clearly and concisely."],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.7
    ];

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer {$openaiKey}"
    ];

    $ch = curl_init($openaiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    file_put_contents('php://stderr', "[AI] OpenAI response: " . substr($response, 0, 400) . "\n");

    if ($err) {
        return "⚠️ ขัดข้องระหว่างเชื่อมต่อ OpenAI API: " . $err;
    }

    $result = json_decode($response, true);
    $summary = $result['choices'][0]['message']['content'] ?? null;

    if (!$summary) {
        return "⚠️ ไม่สามารถสรุปข่าวได้: " . json_encode($result);
    }

    return "📈 สรุปข่าวหุ้น {$symbol}\n\n" . trim($summary);
}
?>
