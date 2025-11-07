<?php
// ---------------------- 🧠 DEBUG MODE ----------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: text/html; charset=utf-8");

// ---------------------- 🔑 API KEYS ----------------------
$OPENAI_API_KEY = getenv("OPENAI_API_KEY");
$OPENAI_PROJECT_ID = getenv("OPENAI_PROJECT_ID");
$OPENAI_ORG_ID = getenv("OPENAI_ORG_ID");
$FINNHUB_API_KEY = getenv("FINNHUB_API_KEY");

// ---------------------- 🧭 CHECK FINNHUB ----------------------
$finnhubStatus = "❌ ไม่สามารถเชื่อมต่อได้";
$finnhubColor = "red";
$finnhubPrice = "ไม่ทราบ";

$finnhubUrl = "https://finnhub.io/api/v1/quote?symbol=AAPL&token={$FINNHUB_API_KEY}";
$finnhubResponse = @file_get_contents($finnhubUrl);
if ($finnhubResponse) {
    $data = json_decode($finnhubResponse, true);
    if (!empty($data['c'])) {
        $finnhubStatus = "✅ Finnhub พร้อมใช้งาน (ราคาหุ้น AAPL: <span style='color:lime'>{$data['c']} USD</span>)";
        $finnhubColor = "lime";
        $finnhubPrice = $data['c'];
    }
}

// ---------------------- 🤖 CHECK OPENAI ----------------------
$openaiStatus = "❌ ไม่สามารถเชื่อมต่อ OpenAI ได้";
$openaiColor = "red";
$openaiError = "";

$ch = curl_init("https://api.openai.com/v1/models");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $OPENAI_API_KEY",
    "OpenAI-Project: $OPENAI_PROJECT_ID",
    "OpenAI-Organization: $OPENAI_ORG_ID"
]);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    $openaiError = "cURL Error: " . curl_error($ch);
} else {
    $result = json_decode($response, true);
    if (isset($result["data"][0]["id"])) {
        $openaiStatus = "✅ OpenAI พร้อมใช้งาน (Model ตัวอย่าง: <span style='color:lime'>{$result["data"][0]["id"]}</span>)";
        $openaiColor = "lime";
    } else {
        $openaiStatus = "⚠️ ได้รับข้อมูลจาก OpenAI แต่มีปัญหา";
        $openaiError = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
curl_close($ch);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>🧠 DOOHOON AI - API Connection Check</title>
<style>
body {
    background: #0d0d0d;
    color: #f1f1f1;
    font-family: "Kanit", sans-serif;
    text-align: left;
    padding: 40px;
}
h1 { color: #ffd700; }
.section {
    border-top: 1px solid #222;
    padding-top: 20px;
    margin-top: 20px;
}
.ok { color: lime; }
.err { color: #ff4d4d; }
pre {
    background: #111;
    padding: 10px;
    border-radius: 8px;
    color: #ccc;
    font-size: 14px;
    overflow-x: auto;
}
</style>
</head>
<body>

<h1>🔍 DOOHOON AI - API Connection Check</h1>
<p>ตรวจสอบการเชื่อมต่อกับ API ทั้งหมดของระบบ</p>

<div class="section">
    <h2>💰 Finnhub API</h2>
    <p><?= $finnhubStatus ?></p>
</div>

<div class="section">
    <h2>🧠 OpenAI API</h2>
    <p style="color:<?= $openaiColor ?>"><?= $openaiStatus ?></p>

<?php if (!empty($openaiError)): ?>
    <pre><?= htmlspecialchars($openaiError, ENT_QUOTES, 'UTF-8') ?></pre>
<?php endif; ?>
</div>

<hr>
<p style="color:#999;">© 2024 DOOHOON-AI | Status check page</p>

</body>
</html>
