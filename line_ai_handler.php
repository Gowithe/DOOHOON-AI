<?php
// =============================================
// ✅ DOOHOON LINE AI HANDLER (FINAL + 9 HEADINGS)
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
// ✅ ดึงข่าวหุ้นจาก Finnhub + สรุปด้วย OpenAI (9 หัวข้อ)
// =============================================
function summarizeStockNews($symbol) {
    $finnhubKey = getenv('FINNHUB_API_KEY');
    $openaiKey  = getenv('OPENAI_API_KEY');

    if (!$finnhubKey || !$openaiKey) {
        return "❌ ยังไม่ได้ตั้งค่า API key ของ Finnhub หรือ OpenAI ใน Render Environment ครับ";
    }

    // 📡 ดึงข่าวจาก Finnhub (ใช้ cURL)
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

    // 🔍 รวมข่าว 5 ข่าวล่าสุด
    $newsList = array_slice($newsData, 0, 5);
    $newsText = "";
    foreach ($newsList as $n) {
        $title = $n['headline'] ?? '';
        $summary = $n['summary'] ?? '';
        $source = $n['source'] ?? '';
        $newsText .= "- {$title} ({$source}) — {$summary}\n";
    }

    // 🧠 สร้าง prompt ให้ OpenAI สรุปตาม 9 หัวข้อที่พี่ต้องการ
    $prompt = <<<EOT
สรุปข้อมูลหุ้น {$symbol} ตามหัวข้อทั้ง 9 ข้อด้านล่างนี้
ให้อ่านเข้าใจง่าย กระชับ และเป็นภาษาไทย ใช้น้ำเสียงแบบนักวิเคราะห์ตลาดหุ้นไทย:

1. ข้อมูลบริษัท: สรุปธุรกิจหลัก จุดเด่น สินค้าหรือบริการสำคัญ
2. โปรเจกต์น่าจับตามอง: โครงการใหม่ ความร่วมมือ หรือเทคโนโลยีที่อาจสร้างรายได้ในอนาคต
3. แนวโน้ม: ภาพรวมทิศทางธุรกิจและตลาดที่เกี่ยวข้อง (ระยะสั้น / กลาง / ยาว)
4. ความเสี่ยง: ปัจจัยที่อาจกระทบต่อการเติบโต เช่น คู่แข่ง กฎหมาย หรือสภาวะเศรษฐกิจ
5. ราคาปัจจุบัน: ราคาล่าสุดของหุ้น (ดึงจาก FINNHUP)
6. ราคาแนวรับ: ระดับราคาที่นักลงทุนควรพิจารณาซื้อสะสม (ระบุเป็นตัวเลขโดยประมาณ)
7. ราคาเป้าหมาย: ราคาที่เหมาะสมในระยะ 6–12 เดือนข้างหน้า (ระบุเป็นตัวเลขโดยประมาณ)
8. คำแนะนำ: สรุปมุมมองเป็นคำแนะนำชัดเจน เช่น ซื้อ / ถือ / ขาย พร้อมเหตุผล
9. สรุปข่าวล่าสุดที่ส่งผลต่อราคาหรือแนวโน้มของบริษัท

ข้อมูลข่าวล่าสุด:
{$newsText}
EOT;

    // ✨ ส่งให้ OpenAI ประมวลผล
    $openaiUrl = "https://api.openai.com/v1/chat/completions";
    $postData = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "You are a professional Thai financial analyst who summarizes stock news in 9 clearly labeled points."],
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

    // ✅ ตัดข้อความเกิน และเพิ่มหัวเรื่องสวย ๆ
    return "📊 สรุปหุ้น {$symbol} ล่าสุด\n\n" . trim($summary);
}
?>
