<?php
// test_line.php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'OK',
    'message' => 'ไฟล์ทำงานได้แล้ว!',
    'line_token_exists' => !empty(getenv("LINE_CHANNEL_TOKEN")),
    'finnhub_api_exists' => !empty(getenv("FINNHUB_API_KEY"))
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
```

**ทดสอบ:**
```
https://your-site.onrender.com/test_line.php