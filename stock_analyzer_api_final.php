<?php
// ================================================================================
// 📈 STOCK ANALYZER API - Enhanced Version with Complete Information
// ================================================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ================================================================================
// 🔐 LOAD ENVIRONMENT VARIABLES
// ================================================================================
function loadEnv($path = '.env') {
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');
            
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
    return true;
}

loadEnv(__DIR__ . '/.env');

$OPENAI_API_KEY  = getenv('OPENAI_API_KEY') ?: '';
$FINNHUB_API_KEY = getenv('FINNHUB_API_KEY') ?: 'd46ntu1r01qgc9etnfngd46ntu1r01qgc9etnfo0';

$USE_AI = !empty($OPENAI_API_KEY) && $OPENAI_API_KEY !== 'your_openai_api_key_here';

// ================================================================================
// 📊 GET SYMBOL
// ================================================================================
$symbol = trim($_GET['symbol'] ?? 'AAPL');
$symbol = strtoupper($symbol);

if (!preg_match('/^[A-Z0-9\-\.]{1,10}$/', $symbol)) {
    http_response_code(400);
    die(json_encode(["error" => "❌ สัญลักษณ์หุ้นไม่ถูกต้อง"], JSON_UNESCAPED_UNICODE));
}

// ================================================================================
// 💰 FETCH STOCK PRICE
// ================================================================================
function getStockPrice($symbol) {
    global $FINNHUB_API_KEY;
    
    $url = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token={$FINNHUB_API_KEY}";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (!$response || $httpCode !== 200) {
        return null;
    }
    
    return json_decode($response, true);
}

// ================================================================================
// 🏢 FETCH COMPANY PROFILE
// ================================================================================
function getCompanyProfile($symbol) {
    global $FINNHUB_API_KEY;
    
    $url = "https://finnhub.io/api/v1/stock/profile2?symbol={$symbol}&token={$FINNHUB_API_KEY}";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (empty($data)) {
        return null;
    }
    
    return [
        'name' => $data['name'] ?? $symbol,
        'country' => $data['country'] ?? 'N/A',
        'currency' => $data['currency'] ?? 'USD',
        'exchange' => $data['exchange'] ?? 'N/A',
        'industry' => $data['finnhubIndustry'] ?? 'N/A',
        'ipo' => $data['ipo'] ?? 'N/A',
        'marketCap' => $data['marketCapitalization'] ?? 0,
        'phone' => $data['phone'] ?? '',
        'shareOutstanding' => $data['shareOutstanding'] ?? 0,
        'weburl' => $data['weburl'] ?? '',
        'logo' => $data['logo'] ?? ''
    ];
}

// ================================================================================
// 🗞️ FETCH NEWS
// ================================================================================
function getNews($symbol) {
    global $FINNHUB_API_KEY;
    
    $from = date('Y-m-d', strtotime('-7 days'));
    $to = date('Y-m-d');
    $url = "https://finnhub.io/api/v1/company-news?symbol={$symbol}&from={$from}&to={$to}&token={$FINNHUB_API_KEY}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $newsData = json_decode($response, true);
    $newsItems = [];
    
    if (!empty($newsData) && is_array($newsData)) {
        foreach (array_slice($newsData, 0, 10) as $news) {
            $newsItems[] = [
                'headline' => $news['headline'] ?? '',
                'summary' => $news['summary'] ?? '',
                'source' => $news['source'] ?? '',
                'url' => $news['url'] ?? '',
                'datetime' => $news['datetime'] ?? 0,
                'image' => $news['image'] ?? ''
            ];
        }
    }
    
    // สร้าง text สำหรับ AI
    $newsText = "";
    foreach (array_slice($newsItems, 0, 5) as $news) {
        $newsText .= "📅 {$news['headline']} ({$news['source']})\n{$news['summary']}\n\n";
    }
    
    return [
        'items' => $newsItems,
        'text' => $newsText ?: "ไม่มีข่าวเด่นในช่วง 7 วันที่ผ่านมา"
    ];
}

// ================================================================================
// 🤖 AI ANALYSIS - Enhanced Version
// ================================================================================
function getAIAnalysis($symbol, $price, $change, $percent, $newsText, $companyProfile) {
    global $OPENAI_API_KEY, $USE_AI;
    
    if (!$price) {
        return getBasicAnalysis($symbol, 0, 0, 0, $companyProfile);
    }
    
    if (!$USE_AI) {
        return getBasicAnalysis($symbol, $price, $change, $percent, $companyProfile);
    }
    
    $companyInfo = "";
    if ($companyProfile) {
        $companyInfo = "
ข้อมูลบริษัท:
- ชื่อ: {$companyProfile['name']}
- อุตสาหกรรม: {$companyProfile['industry']}
- ประเทศ: {$companyProfile['country']}
- Market Cap: {$companyProfile['marketCap']} ล้าน USD
";
    }
    
    $prompt = "วิเคราะห์หุ้น {$symbol} อย่างละเอียด

{$companyInfo}

ราคาปัจจุบัน: {$price} USD (เปลี่ยนแปลง {$change} USD, {$percent}%)

ข่าวล่าสุด:
{$newsText}

ให้ตอบเป็น JSON เท่านั้นในรูปแบบนี้:
{
  \"company_overview\": \"ธุรกิจหลักของบริษัท แหล่งรายได้หลัก และจุดเด่น (3-4 ประโยค)\",
  \"revenue_sources\": [\"แหล่งรายได้ที่ 1\", \"แหล่งรายได้ที่ 2\", \"แหล่งรายได้ที่ 3\"],
  \"key_projects\": [\"โปรเจ็กต์/นวัตกรรมที่ 1\", \"โปรเจ็กต์/นวัตกรรมที่ 2\", \"โปรเจ็กต์/นวัตกรรมที่ 3\"],
  \"summary\": \"สรุปสถานะปัจจุบันของบริษัท\",
  \"keypoints\": [\"จุดสำคัญ 1\", \"จุดสำคัญ 2\", \"จุดสำคัญ 3\"],
  \"trends\": \"แนวโน้มในอนาคต\",
  \"risks\": [\"ความเสี่ยง 1\", \"ความเสี่ยง 2\", \"ความเสี่ยง 3\"],
  \"opportunities\": [\"โอกาส 1\", \"โอกาส 2\"],
  \"support_level\": \"ระดับรับแนะนำ\",
  \"resistance_level\": \"ระดับต้านแนะนำ\",
  \"target_price\": \"ราคาเป้าหมาย (3-6 เดือน)\",
  \"recommendation\": \"ซื้อ/ถือ/ขาย\",
  \"reason\": \"เหตุผลสั้นๆ\"
}

สำคัญมาก: ตอบเป็น JSON ที่ถูกต้องเท่านั้น ไม่มีข้อความอื่น";

    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "คุณคือนักวิเคราะห์หลักทรัพย์และธุรกิจมืออาชีพ ตอบเป็น JSON เท่านั้น"],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.7,
        "max_tokens" => 2000
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer {$OPENAI_API_KEY}"
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return getBasicAnalysis($symbol, $price, $change, $percent, $companyProfile);
    }

    $result = json_decode($response, true);
    
    if (!isset($result["choices"][0]["message"]["content"])) {
        return getBasicAnalysis($symbol, $price, $change, $percent, $companyProfile);
    }

    $aiResponse = $result["choices"][0]["message"]["content"];
    $aiResponse = preg_replace('/```json\s*/i', '', $aiResponse);
    $aiResponse = preg_replace('/```\s*/i', '', $aiResponse);
    $aiResponse = trim($aiResponse);
    
    $analysis = json_decode($aiResponse, true);
    
    if (!is_array($analysis)) {
        return getBasicAnalysis($symbol, $price, $change, $percent, $companyProfile);
    }
    
    return $analysis;
}

// ================================================================================
// 📊 BASIC ANALYSIS - Enhanced
// ================================================================================
function getBasicAnalysis($symbol, $price, $change, $percent, $companyProfile) {
    if ($price == 0) {
        return [
            "company_overview" => "ไม่พบข้อมูลบริษัท",
            "revenue_sources" => ["ไม่มีข้อมูล"],
            "key_projects" => ["ไม่มีข้อมูล"],
            "summary" => "ไม่พบข้อมูลหุ้น {$symbol}",
            "keypoints" => ["ตรวจสอบสัญลักษณ์หุ้นอีกครั้ง"],
            "trends" => "ไม่มีข้อมูล",
            "risks" => ["ข้อมูลไม่พบ"],
            "opportunities" => [],
            "support_level" => "-",
            "resistance_level" => "-",
            "target_price" => "-",
            "recommendation" => "ไม่สามารถวิเคราะห์",
            "reason" => "ไม่มีข้อมูล"
        ];
    }
    
    $isPositive = $change >= 0;
    $momentum = abs($percent) > 2 ? "แรง" : "ปานกลาง";
    
    $support = round($price * 0.95, 2);
    $resistance = round($price * 1.05, 2);
    $target = round($price * 1.10, 2);
    
    // ข้อมูลบริษัท
    $companyOverview = "ไม่มีข้อมูล AI - กรุณาเพิ่ม OpenAI API Key";
    $revenueSources = ["ข้อมูลต้องการ AI Analysis"];
    $keyProjects = ["ข้อมูลต้องการ AI Analysis"];
    
    if ($companyProfile) {
        $companyOverview = "{$companyProfile['name']} เป็นบริษัทในอุตสาหกรรม {$companyProfile['industry']} มี Market Cap ประมาณ " . number_format($companyProfile['marketCap']) . " ล้าน USD";
        $revenueSources = ["ข้อมูลรายได้ต้องการ AI Analysis"];
        $keyProjects = ["โปรเจ็กต์หลักต้องการ AI Analysis"];
    }
    
    if ($percent > 3) {
        $rec = "ถือ - รอปรับฐาน";
        $reason = "ราคาปรับตัวขึ้นแรง อาจมีการปรับฐานในระยะสั้น";
    } elseif ($percent > 1) {
        $rec = "ซื้อ - แนวโน้มบวก";
        $reason = "ราคาเคลื่อนไหวในทิศทางบวก มีโมเมนตัม";
    } elseif ($percent > -1) {
        $rec = "ถือ";
        $reason = "ราคาเคลื่อนไหวในกรอบแคบ รอสัญญาณชัดเจน";
    } elseif ($percent > -3) {
        $rec = "พิจารณาซื้อ";
        $reason = "ราคาอ่อนตัวเล็กน้อย อาจเป็นจังหวะเข้าซื้อ";
    } else {
        $rec = "ระวัง";
        $reason = "ราคาอ่อนตัวมาก ควรรอสัญญาณฟื้นตัว";
    }
    
    return [
        "company_overview" => $companyOverview,
        "revenue_sources" => $revenueSources,
        "key_projects" => $keyProjects,
        "summary" => "{$symbol} มีราคาปัจจุบันที่ \${$price} เคลื่อนไหว" . ($isPositive ? "เพิ่มขึ้น" : "ลดลง") . " {$percent}%",
        "keypoints" => [
            "ราคาปัจจุบัน: \${$price}",
            "การเปลี่ยนแปลง: " . ($isPositive ? "+" : "") . "{$change} ({$percent}%)",
            "โมเมนตัม: {$momentum}"
        ],
        "trends" => "แนวโน้ม" . ($isPositive ? "ขาขึ้น" : "ขาลง") . " โมเมนตัม{$momentum}",
        "risks" => [
            "ความผันผวนของตลาด",
            "ปัจจัยเศรษฐกิจมหภาค",
            "ข่าวสารของบริษัท"
        ],
        "opportunities" => [
            "ติดตามข่าวสารบริษัท",
            "วิเคราะห์งบการเงิน"
        ],
        "support_level" => (string)$support,
        "resistance_level" => (string)$resistance,
        "target_price" => (string)$target,
        "recommendation" => $rec,
        "reason" => $reason
    ];
}

// ================================================================================
// 🎯 MAIN EXECUTION
// ================================================================================
try {
    $priceData = getStockPrice($symbol);
    
    if (!$priceData || !isset($priceData['c']) || $priceData['c'] == 0) {
        http_response_code(400);
        die(json_encode([
            "error" => "❌ ไม่พบข้อมูลหุ้น: {$symbol}",
            "suggestion" => "ตรวจสอบสัญลักษณ์หุ้น หรือลองอีกครั้งในภายหลัง"
        ], JSON_UNESCAPED_UNICODE));
    }
    
    $price = $priceData['c'];
    $change = $priceData['d'] ?? 0;
    $percent = $priceData['dp'] ?? 0;
    $high = $priceData['h'] ?? null;
    $low = $priceData['l'] ?? null;
    $open = $priceData['o'] ?? null;
    $prevClose = $priceData['pc'] ?? null;
    
    $companyProfile = getCompanyProfile($symbol);
    $newsData = getNews($symbol);
    $analysis = getAIAnalysis($symbol, $price, $change, $percent, $newsData['text'], $companyProfile);
    
    $output = [
        "symbol" => $symbol,
        "timestamp" => date('Y-m-d H:i:s'),
        "company_profile" => $companyProfile,
        "price_data" => [
            "currentPrice" => $price,
            "change" => $change,
            "percent" => $percent,
            "high" => $high,
            "low" => $low,
            "open" => $open,
            "previousClose" => $prevClose
        ],
        "news" => $newsData['items'],
        "analysis" => $analysis,
        "ai_enabled" => $USE_AI,
        "status" => "success"
    ];
    
    echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "❌ เกิดข้อผิดพลาด: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
