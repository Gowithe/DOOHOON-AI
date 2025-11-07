<?php
// =====================================
// 🔍 DOOHOON-AI API STATUS CHECKER
// =====================================
header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$OPENAI_API_KEY = getenv("OPENAI_API_KEY");
$FINNHUB_API_KEY = getenv("FINNHUB_API_KEY");

echo "<body style='background:#0d0d0d; color:#eee; font-family:Segoe UI, sans-serif; padding:30px;'>";
echo "<h1 style='color:#ffd700;'>🔎 DOOHOON AI - API Connection Check</h1>";
echo "<p>ตรวจสอบการเชื่อมต่อกับ API ทั้งหมดของระบบ</p>";
echo "<hr style='border-color:#555;'>";

// -------------------------------------------------
// ✅ 1. ตรวจสอบ FINNHUB
// -------------------------------------------------
echo "<h2 style='color:#00ffff;'>💰 Finnhub API</h2>";

if (!$FINNHUB_API_KEY) {
    echo "<p style='color:#ff5555;'>❌ ไม่พบ FINNHUB_API_KEY ใน Environment Variable</p>";
} else {
    $url = "https://finnhub.io/api/v1/quote?symbol=AAPL&token={$FINNHUB_API_KEY}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "<p style='color:#ff5555;'>❌ เชื่อมต่อ Finnhub ไม่สำเร็จ: <b>$error</b></p>";
    } elseif (!$result) {
        echo "<p style='color:#ff5555;'>❌ ไม่ได้รับข้อมูลจาก Finnhub (response ว่างเปล่า)</p>";
    } else {
        $data = json_decode($result, true);
        if (isset($data['c'])) {
            echo "<p style='color:#00ff88;'>✅ Finnhub พร้อมใช้งาน (ราคาหุ้น AAPL: <b>{$data['c']} USD</b>)</p>";
        } else {
            echo "<p style='color:#ff5555;'>⚠️ ได้รับข้อมูล แต่ API key อาจไม่ถูกต้อง</p>";
            echo "<pre style='background:#111; color:#ccc; padding:10px; border-radius:6px;'>".htmlspecialchars($result)."</pre>";
        }
    }
}

// -------------------------------------------------
// ✅ 2. ตรวจสอบ OPENAI
// -------------------------------------------------
echo "<h2 style='color:#ffcc00;'>🧠 OpenAI API</h2>";

if (!$OPENAI_API_KEY) {
    echo "<p style='color:#ff5555;'>❌ ไม่พบ OPENAI_API_KEY ใน Environment Variable</p>";
} else {
    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "user", "content" => "ทดสอบการเชื่อมต่อ OpenAI เท่านั้น ตอบสั้น ๆ ว่า 'พร้อมครับ'"]
        ]
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $OPENAI_API_KEY",
        "OpenAI-Organization: org-default",
        "OpenAI-Project: default"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo "<p style='color:#ff5555;'>❌ OpenAI cURL Error: <b>$err</b></p>";
    } elseif (!$response) {
        echo "<p style='color:#ff5555;'>❌ ไม่ได้รับข้อมูลจาก OpenAI</p>";
    } else {
        $json = json_decode($response, true);
        if (isset($json['choices'][0]['message']['content'])) {
            $content = htmlspecialchars($json['choices'][0]['message']['content']);
            echo "<p style='color:#00ff88;'>✅ OpenAI พร้อมใช้งาน: <b>$content</b></p>";
        } else {
            echo "<p style='color:#ff5555;'>⚠️ ได้รับข้อมูลจาก OpenAI แต่มีปัญหา</p>";
            echo "<pre style='background:#111; color:#ccc; padding:10px; border-radius:6px;'>".htmlspecialchars($response)."</pre>";
        }
    }
}

echo "<hr style='border-color:#555; margin-top:40px;'>";
echo "<p style='color:#888;'>© 2024 DOOHOON-AI | Status check page</p>";
echo "</body>";
?>
