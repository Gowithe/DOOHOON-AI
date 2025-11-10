<?php
/**
 * line_ai_handler.php
 * -------------------
 * ฟังก์ชันวิเคราะห์หุ้นด้วย Finnhub + OpenAI
 * แล้วส่งกลับเป็น 9 ข้อความ (Reply 5 ข้อความ + Push 4 ข้อความ)
 *
 * ต้องตั้ง ENV ไว้แล้ว:
 * - FINNHUB_API_KEY
 * - OPENAI_API_KEY
 * - OPENAI_ORG_ID   (ถ้ามี)
 * - OPENAI_PROJECT_ID (ถ้ามี)
 * - LINE_CHANNEL_TOKEN
 */

function attemptHandleStockQuery(string $text, string $replyToken, ?string $userId): bool
{
    $symbol = extractSymbolOrSearch($text);
    if (!$symbol) {
        return false; // ไม่ใช่คำถามหุ้น ปล่อยให้ handler อื่นทำงานต่อ
    }

    // 1) ดึงราคาหุ้นล่าสุด
    $quote = finnhubQuote($symbol);
    if (!$quote || !isset($quote['c']) || !$quote['c']) {
        sendLineReply($replyToken, [
            ["type" => "text", "text" => "ขออภัยครับ ไม่พบข้อมูลราคาหุ้นของ {$symbol} ในขณะนี้"]
        ]);
        return true;
    }

    $current = $quote['c'];
    $change  = $quote['d'] ?? 0;
    $percent = $quote['dp'] ?? 0;

    // 2) ดึงข่าว 7 วันล่าสุด
    $newsList = finnhubNews($symbol, 7); // array ของข่าว (headline, summary, url, datetime)

    // 3) ขอให้ OpenAI สรุป 9 หัวข้อ (ภาษาไทย) ตามฟอร์แมต
    $sections = openaiNineSections($symbol, $current, $change, $percent, $newsList);

    if (!$sections || count($sections) < 9) {
        sendLineReply($replyToken, [
            ["type" => "text", "text" => "ขออภัยครับ AI ติดขัดชั่วคราว ลองใหม่อีกครั้งได้ครับ"]
        ]);
        return true;
    }

    // 4) จัด 9 ข้อความแยกส่ง: Reply 5 ข้อความ + Push 4 ข้อความ
    $messages = [];
    foreach ($sections as $i => $line) {
        $messages[] = [
            "type" => "text",
            "text" => $line
        ];
    }

    // แบ่งชุดส่ง
    $firstBatch  = array_slice($messages, 0, 5);
    $secondBatch = array_slice($messages, 5);

    // ส่ง reply 5 ข้อความแรก
    sendLineReply($replyToken, $firstBatch);

    // ส่ง 4 ข้อความที่เหลือด้วย push (ต้องมี $userId)
    if ($userId && count($secondBatch) > 0) {
        sendLinePush($userId, $secondBatch);
    }

    return true;
}

/* -------------------- Helpers -------------------- */

function extractSymbolOrSearch(string $text): ?string
{
    $text = trim($text);

    // กรณีพิมพ์เช่น "nvda", "AAPL", "tsla"
    if (preg_match('/\b([A-Za-z]{1,6})\b/u', $text, $m)) {
        $raw = strtoupper($m[1]);

        // ถ้ามีตัวเลขต่อท้าย (บางตลาด) ก็เก็บสูงสุด 6 ตัว
        if (!preg_match('/^[A-Z]{1,6}$/', $raw)) {
            // ไม่ตรง format ก็ลอง search ใน Finnhub
            return finnhubSearchSymbol($text) ?: null;
        }
        return $raw;
    }

    // กรณีเป็นคำอธิบายบริษัท เช่น "Nvidia", "Microsoft"
    return finnhubSearchSymbol($text) ?: null;
}

function finnhubSearchSymbol(string $query): ?string
{
    $key = getenv("FINNHUB_API_KEY");
    if (!$key) return null;

    $url = "https://finnhub.io/api/v1/search?q=" . urlencode($query) . "&token=" . urlencode($key);
    $json = httpGetJson($url);
    if (!$json || empty($json['result'])) return null;

    // เอาตัวแรกที่เป็นสัญลักษณ์หุ้นหลัก ๆ (ไม่ใช่ crypto/forex)
    foreach ($json['result'] as $r) {
        if (!empty($r['symbol']) && !empty($r['type']) && strtoupper($r['type']) === 'EQUITY') {
            return strtoupper($r['symbol']);
        }
    }
    // fallback: ตัวแรก
    return strtoupper($json['result'][0]['symbol'] ?? '');
}

function finnhubQuote(string $symbol): ?array
{
    $key = getenv("FINNHUB_API_KEY");
    if (!$key) return null;

    $url = "https://finnhub.io/api/v1/quote?symbol=" . urlencode($symbol) . "&token=" . urlencode($key);
    return httpGetJson($url);
}

function finnhubNews(string $symbol, int $days = 7): array
{
    $key = getenv("FINNHUB_API_KEY");
    if (!$key) return [];

    $to   = date('Y-m-d');
    $from = date('Y-m-d', strtotime("-{$days} days"));
    $url  = "https://finnhub.io/api/v1/company-news?symbol=" . urlencode($symbol) .
            "&from={$from}&to={$to}&token=" . urlencode($key);

    $data = httpGetJson($url);
    if (!$data || !is_array($data)) return [];

    // เก็บ headline/summary/url ล่าสุดสั้น ๆ ไม่เกิน 5 ข่าว
    $out = [];
    foreach ($data as $n) {
        $headline = $n['headline'] ?? '';
        if (!$headline) continue;
        $summary = $n['summary'] ?? '';
        $url     = $n['url'] ?? '';
        $dt      = !empty($n['datetime']) ? date('Y-m-d', $n['datetime']) : '';
        $out[] = [
            'headline' => $headline,
            'summary'  => $summary,
            'url'      => $url,
            'date'     => $dt,
        ];
        if (count($out) >= 5) break;
    }
    return $out;
}

function openaiNineSections(string $symbol, float $current, float $change, float $percent, array $newsList): ?array
{
    $apiKey   = getenv("OPENAI_API_KEY");
    $orgId    = getenv("OPENAI_ORG_ID");
    $project  = getenv("OPENAI_PROJECT_ID");
    if (!$apiKey) return null;

    // เตรียมสตริงข่าว
    $newsText = "";
    if ($newsList) {
        foreach ($newsList as $n) {
            $line = "• [{$n['date']}] {$n['headline']}";
            if (!empty($n['summary'])) $line .= " — {$n['summary']}";
            if (!empty($n['url']))     $line .= " ({$n['url']})";
            $newsText .= $line . "\n";
        }
    } else {
        $newsText = "ไม่มีข่าวสำคัญในช่วงที่ผ่านมา";
    }

    $prompt = <<<PROMPT
คุณเป็นนักวิเคราะห์หลักทรัพย์มืออาชีพ ตอบเป็นภาษาไทยแบบกระชับ อ่านง่าย และชัดเจน
บริษัท: {$symbol}
ราคาปัจจุบัน: {$current} USD (เปลี่ยนแปลง {$change} USD / {$percent}%)
ข่าวล่าสุด:
{$newsText}

จงสรุปเป็น 9 หัวข้อ โดยส่ง "ข้อความสั้น 1 บรรทัด" สำหรับแต่ละหัวข้อ (ไม่ใช้ bullet/ตัวเลข)
1. ข้อมูลบริษัท
2. โปรเจกต์น่าจับตามอง
3. แนวโน้ม (ระยะสั้น / กลาง / ยาว)
4. ความเสี่ยง
5. ราคาปัจจุบัน (ระบุเป็นตัวเลขเดียวกัน)
6. ราคาแนวรับ (ระบุเป็นตัวเลขโดยประมาณ)
7. ราคาเป้าหมาย (ระยะ 6-12 เดือน ระบุเป็นตัวเลขโดยประมาณ)
8. คำแนะนำ (ซื้อ / ถือ / ขาย พร้อมเหตุผลสั้น ๆ)
9. สรุปข่าวล่าสุดที่มีผลต่อแนวโน้ม

**สำคัญ:** ส่งกลับมาเป็น 9 บรรทัดเท่านั้น แต่ละบรรทัด = 1 ข้อความ
PROMPT;

    $payload = [
        "model" => "gpt-4o-mini",
        "temperature" => 0.6,
        "messages" => [
            ["role" => "system", "content" => "คุณคือนักวิเคราะห์หลักทรัพย์มืออาชีพ ให้ข้อมูลที่แม่นยำ กระชับ และไม่โอเวอร์เคลม"],
            ["role" => "user", "content" => $prompt],
        ],
        "max_tokens" => 800,
    ];

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer {$apiKey}",
    ];
    if ($orgId)   $headers[] = "OpenAI-Organization: {$orgId}";
    if ($project) $headers[] = "OpenAI-Project: {$project}";

    $res = httpPostJson("https://api.openai.com/v1/chat/completions", $payload, $headers);
    if (!$res || empty($res['choices'][0]['message']['content'])) return null;

    $raw = trim($res['choices'][0]['message']['content']);
    $lines = preg_split('/\r\n|\r|\n/', $raw);

    // กรองเอา 9 บรรทัดแรก
    $out = [];
    foreach ($lines as $ln) {
        $ln = trim($ln);
        if ($ln === '') continue;
        // ตัดตัวเลข/จุดนำหน้า ถ้ามี
        $ln = preg_replace('/^\d+[\)\.\-]?\s*/u', '', $ln);
        $out[] = $ln;
        if (count($out) >= 9) break;
    }
    if (count($out) < 9) return null;
    return $out;
}

function sendLineReply(string $replyToken, array $messages): void
{
    $token = getenv("LINE_CHANNEL_TOKEN");
    if (!$token) return;

    $body = [
        "replyToken" => $replyToken,
        "messages"   => $messages,
    ];
    httpPostJson("https://api.line.me/v2/bot/message/reply", $body, [
        "Content-Type: application/json",
        "Authorization: Bearer {$token}",
    ]);
}

function sendLinePush(string $to, array $messages): void
{
    $token = getenv("LINE_CHANNEL_TOKEN");
    if (!$token) return;

    $body = [
        "to"       => $to,
        "messages" => $messages,
    ];
    httpPostJson("https://api.line.me/v2/bot/message/push", $body, [
        "Content-Type: application/json",
        "Authorization: Bearer {$token}",
    ]);
}

/* --------------- HTTP helpers --------------- */

function httpGetJson(string $url): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$res) return null;
    $json = json_decode($res, true);
    return is_array($json) ? $json : null;
}

function httpPostJson(string $url, array $payload, array $headers = []): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$res) return null;
    $json = json_decode($res, true);
    return is_array($json) ? $json : null;
}
