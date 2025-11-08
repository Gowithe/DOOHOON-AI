<?php
// =============================================
// ✅ DOOHOON LINE AI HANDLER — 9 ข้อ (FINAL)
// =============================================

// ตรวจจับคำเกี่ยวกับหุ้น + เรียกสรุปแบบ 9 ข้อ
function attemptHandleStockQuery($text, $replyToken, $userId) {
    // ทริกเกอร์: มีคำว่า "สรุปข่าว" + ตัวย่อ / หรือพิมพ์ตัวย่อหุ้นเดี่ยว ๆ
    $tickers = ['NVDA','TSLA','AAPL','MSFT','GOOGL','AMZN','META','INTC','AMD','O'];
    $pattern = '/\b(' . implode('|', array_map('preg_quote', $tickers)) . ')\b/i';

    if (preg_match('/สรุปข่าว/i', $text) && preg_match($pattern, $text, $m)) {
        $symbol = strtoupper($m[1]);
        return buildAndSendNinePoints($symbol, $replyToken, $userId);
    }
    if (preg_match($pattern, $text, $m)) {
        $symbol = strtoupper($m[1]);
        return buildAndSendNinePoints($symbol, $replyToken, $userId);
    }
    return false;
}

/**
 * ดึงข้อมูลจริง (ข่าว, โปรไฟล์, ราคา) + ใช้ OpenAI ให้สรุป 9 หัวข้อ
 * แล้วส่ง 9 ข้อความแยกกัน (5 ข้อด้วย reply, 4 ข้อด้วย push)
 */
function buildAndSendNinePoints($symbol, $replyToken, $userId) {
    $finnhubKey = getenv('FINNHUB_API_KEY');
    $openaiKey  = getenv('OPENAI_API_KEY');

    if (!$finnhubKey || !$openaiKey) {
        sendLineReply($replyToken, [[
            "type" => "text",
            "text" => "❌ ยังไม่ได้ตั้งค่า API key ของ Finnhub หรือ OpenAI"
        ]]);
        return true;
    }

    // -------- Finnhub: company profile
    $profileUrl = "https://finnhub.io/api/v1/stock/profile2?symbol={$symbol}&token={$finnhubKey}";
    $profile    = httpGetJson($profileUrl);

    // -------- Finnhub: last price (ใช้ในข้อ 5)
    $quoteUrl   = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token={$finnhubKey}";
    $quote      = httpGetJson($quoteUrl);
    $price      = isset($quote['c']) ? (float)$quote['c'] : null;
    $currency   = $profile['currency'] ?? '';

    // -------- Finnhub: last 5 news (หัวข้อ + สรุปสั้น)
    $from = date('Y-m-d', strtotime('-5 days'));
    $to   = date('Y-m-d');
    $newsUrl   = "https://finnhub.io/api/v1/company-news?symbol={$symbol}&from={$from}&to={$to}&token={$finnhubKey}";
    $newsData  = httpGetJson($newsUrl);
    $newsData  = is_array($newsData) ? array_slice($newsData, 0, 5) : [];
    $newsText  = "";
    foreach ($newsData as $n) {
        $title   = $n['headline'] ?? '';
        $source  = $n['source'] ?? '';
        $summary = $n['summary'] ?? '';
        if ($title) {
            $newsText .= "- {$title} ({$source}) — {$summary}\n";
        }
    }
    if ($newsText === "") { $newsText = "- (ไม่พบข่าวสำคัญในรอบ 5 วัน)\n"; }

    // สร้าง context ให้ AI (ชื่อบริษัท / ธุรกิจ / ประเทศ / กลุ่มฯ)
    $companyName   = $profile['name']        ?? $symbol;
    $industry      = $profile['finnhubIndustry'] ?? '';
    $country       = $profile['country']     ?? '';
    $businessHint  = "{$companyName} | Industry: {$industry} | Country: {$country}";

    // -------- OpenAI: ขอให้สรุปหัวข้อ 1,2,3,4,6,7,8,9 (ปล่อย 5 ให้เราคิดเองด้วยราคา)
    $prompt = <<<EOT
คุณคือนักวิเคราะห์หลักทรัพย์ไทย ช่วยสรุปข้อมูลหุ้น {$symbol} เป็นภาษาไทย
โดย **ต้องคืนค่ารูปแบบ JSON เท่านั้น** ตามโครงสร้างนี้ (ห้ามมีข้อความนอก JSON):

{
  "company_info": "ข้อ 1: ข้อมูลบริษัท (สรุปธุรกิจหลัก จุดเด่น สินค้าหรือบริการสำคัญ; 3-5 บรรทัด)",
  "projects_to_watch": "ข้อ 2: โปรเจกต์น่าจับตามอง (โครงการใหม่/ความร่วมมือ/เทคโนโลยีที่อาจสร้างรายได้; 2-4 bullet)",
  "outlook": {
    "short": "แนวโน้มระยะสั้น (1-3 เดือน; 1-2 บรรทัด)",
    "medium": "แนวโน้มระยะกลาง (3-12 เดือน; 1-2 บรรทัด)",
    "long": "แนวโน้มระยะยาว (12 เดือนขึ้นไป; 1-2 บรรทัด)"
  },
  "risks": "ข้อ 4: ความเสี่ยงหลัก 2-4 ข้อ",
  "support_price": "ข้อ 6: แนวรับ (ตัวเลขเป็นค่าโดยประมาณ; ให้หน่วย/สกุลเงินเดียวกับราคาหุ้น)",
  "target_price_6_12m": "ข้อ 7: ราคาเป้าหมาย 6-12 เดือน (ตัวเลขโดยประมาณ)",
  "recommendation": "ข้อ 8: คำแนะนำ (ซื้อ/ถือ/ขาย + เหตุผลสั้นๆ)",
  "latest_news_summary": "ข้อ 9: สรุปข่าวล่าสุดที่ส่งผลต่อราคาหรือแนวโน้ม (2-5 บรรทัด)"
}

Context บริษัท:
{$businessHint}

ข่าว 5 รายการล่าสุด:
{$newsText}

หมายเหตุสำคัญ:
- ให้ **คืนค่าเป็น JSON ที่ parse ได้จริงเท่านั้น** (ห้ามมีคำอธิบายหรือข้อความอื่น)
- ตัวเลขแนวรับ/ราคาเป้าหมายให้สมเหตุสมผลเทียบกับราคาปัจจุบัน (เราจะเติมราคาปัจจุบันเอง)
- ถ้าไม่แน่ใจในตัวเลข ให้ใส่เป็นช่วงโดยประมาณได้ เช่น "ประมาณ 110–125"
EOT;

    $json = callOpenAIJson($prompt, $openaiKey);
    if (!$json) {
        sendLineReply($replyToken, [[
            "type" => "text",
            "text" => "⚠️ ไม่สามารถสรุปข้อมูลจาก AI ได้ตอนนี้ ลองใหม่อีกครั้งนะครับ"
        ]]);
        return true;
    }

    // ประกอบ 9 ข้อความ
    $msgs = [];

    // 1
    $msgs[] = "1) ข้อมูลบริษัท\n" . trim($json['company_info'] ?? '-');
    // 2
    $msgs[] = "2) โปรเจกต์น่าจับตามอง\n" . trim($json['projects_to_watch'] ?? '-');
    // 3
    $outS = $json['outlook']['short']  ?? '-';
    $outM = $json['outlook']['medium'] ?? '-';
    $outL = $json['outlook']['long']   ?? '-';
    $msgs[] = "3) แนวโน้ม\n• ระยะสั้น: {$outS}\n• ระยะกลาง: {$outM}\n• ระยะยาว: {$outL}";
    // 4
    $msgs[] = "4) ความเสี่ยง\n" . trim($json['risks'] ?? '-');
    // 5 (ราคาปัจจุบันจาก Finnhub)
    $priceText = ($price !== null) ? number_format($price, 2) : '—';
    $curText   = $currency ? " {$currency}" : "";
    $msgs[]    = "5) ราคาปัจจุบัน\n{$symbol}: {$priceText}{$curText}";
    // 6
    $msgs[] = "6) ราคาแนวรับ (ประมาณ)\n" . trim($json['support_price'] ?? '-');
    // 7
    $msgs[] = "7) ราคาเป้าหมาย 6–12 เดือน\n" . trim($json['target_price_6_12m'] ?? '-');
    // 8
    $msgs[] = "8) คำแนะนำ\n" . trim($json['recommendation'] ?? '-');
    // 9
    $msgs[] = "9) สรุปข่าวล่าสุด\n" . trim($json['latest_news_summary'] ?? '-');

    // ส่ง 5 ข้อแรกด้วย reply, ที่เหลือ 4 ข้อด้วย push (จำกัดของ LINE)
    $first5  = array_slice($msgs, 0, 5);
    $next4   = array_slice($msgs, 5);

    sendLineReply($replyToken, array_map(fn($t)=>["type"=>"text","text"=>$t], $first5));
    if (!empty($next4) && $userId) {
        // หน่วงนิดกัน rate-limit (ปล่อยได้เลยก็ได้ ส่วนมากไม่จำเป็น)
        usleep(200 * 1000);
        sendLinePush($userId, array_map(fn($t)=>["type"=>"text","text"=>$t], $next4));
    }

    return true;
}

/* ---------- Utilities ---------- */

function httpGetJson($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 20
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    return is_array($data) ? $data : null;
}

function callOpenAIJson($prompt, $openaiKey) {
    $url = "https://api.openai.com/v1/chat/completions";
    $payload = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role"=>"system","content"=>"You return ONLY valid JSON. No extra text."],
            ["role"=>"user","content"=>$prompt],
        ],
        "temperature" => 0.5
    ];
    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer {$openaiKey}"
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) { return null; }

    $obj = json_decode($res, true);
    $content = $obj['choices'][0]['message']['content'] ?? '';
    if (!$content) { return null; }

    // ดึงเฉพาะ JSON (กันกรณีมีข้อความหลุดมา)
    $json = tryExtractJson($content);
    return $json;
}

function tryExtractJson($text) {
    // พยายามจับบล็อก JSON แรก
    if (preg_match('/\{.*\}/s', $text, $m)) {
        $j = json_decode($m[0], true);
        if (is_array($j)) return $j;
    }
    // เผื่อ OpenAI ส่ง JSON อยู่แล้ว
    $j = json_decode($text, true);
    return is_array($j) ? $j : null;
}

/* ---------- LINE send helpers (ใช้ร่วมกับ line_webhook.php ได้ทันที) ---------- */

function sendLineReply($replyToken, $messages) {
    $access_token = getenv('LINE_CHANNEL_TOKEN');
    if (!$access_token) return;
    $url = "https://api.line.me/v2/bot/message/reply";
    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer {$access_token}"
    ];
    $postData = json_encode(["replyToken"=>$replyToken,"messages"=>$messages], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_POSTFIELDS=>$postData
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function sendLinePush($userId, $messages) {
    $access_token = getenv('LINE_CHANNEL_TOKEN');
    if (!$access_token) return;
    $url = "https://api.line.me/v2/bot/message/push";
    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer {$access_token}"
    ];
    $postData = json_encode(["to"=>$userId,"messages"=>$messages], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_POSTFIELDS=>$postData
    ]);
    curl_exec($ch);
    curl_close($ch);
}
?>
