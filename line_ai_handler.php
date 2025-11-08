function getOpenAIResponse($apiKey, $prompt) {
    $url = "https://api.openai.com/v1/chat/completions";
    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [[
            "role" => "user",
            "content" => $prompt . "

โปรดสรุปในรูปแบบ 9 ข้อ โดยแต่ละข้อขึ้นต้นด้วยเลขและชื่อหัวข้อ เช่น
1. ข้อมูลบริษัท:
2. โปรเจกต์น่าจับตามอง:
3. แนวโน้มธุรกิจ:
4. ความเสี่ยง:
5. ราคาปัจจุบัน:
6. ราคาแนวรับ:
7. ราคาเป้าหมาย:
8. คำแนะนำ:
9. ข่าวล่าสุด:
ให้ตอบสั้นกระชับในแต่ละข้อ"
        ]],
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
