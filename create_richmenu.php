<?php
// create_richmenu.php
header('Content-Type: application/json; charset=utf-8');

$LINE_CHANNEL_TOKEN = getenv("LINE_CHANNEL_TOKEN");

// 1. สร้าง Rich Menu Structure
$richMenuData = [
    'size' => [
        'width' => 2500,
        'height' => 1686
    ],
    'selected' => true,
    'name' => 'DOOHOON Menu',
    'chatBarText' => 'เมนู DOOHOON',
    'areas' => [
        // ดูแนวรับ-จุดเข้าซื้อ (บนซ้าย)
        [
            'bounds' => [
                'x' => 0,
                'y' => 0,
                'width' => 1250,
                'height' => 843
            ],
            'action' => [
                'type' => 'uri',
                'uri' => 'https://your-site.onrender.com/support-resistance.html'
            ]
        ],
        // คำนวณต้นทุนเฉลี่ย (บนขวา)
        [
            'bounds' => [
                'x' => 1250,
                'y' => 0,
                'width' => 1250,
                'height' => 843
            ],
            'action' => [
                'type' => 'uri',
                'uri' => 'https://your-site.onrender.com/average-cost.html'
            ]
        ],
        // คำนวณดอกเบี้ยทบต้น (ล่างซ้าย)
        [
            'bounds' => [
                'x' => 0,
                'y' => 843,
                'width' => 1250,
                'height' => 843
            ],
            'action' => [
                'type' => 'uri',
                'uri' => 'https://your-site.onrender.com/compound-interest.html'
            ]
        ],
        // พื้นฐานหุ้น (ล่างขวา)
        [
            'bounds' => [
                'x' => 1250,
                'y' => 843,
                'width' => 1250,
                'height' => 843
            ],
            'action' => [
                'type' => 'uri',
                'uri' => 'https://your-site.onrender.com/stock-basics.html'
            ]
        ]
    ]
];

// 2. สร้าง Rich Menu
$ch = curl_init('https://api.line.me/v2/bot/richmenu');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($richMenuData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $LINE_CHANNEL_TOKEN
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    $richMenuId = $result['richMenuId'];
    
    echo json_encode([
        'success' => true,
        'richMenuId' => $richMenuId,
        'message' => 'Rich Menu created! Next step: Upload image to this URL: https://your-site.onrender.com/upload_richmenu_image.php?richMenuId=' . $richMenuId
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'success' => false,
        'error' => $response,
        'httpCode' => $httpCode
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>