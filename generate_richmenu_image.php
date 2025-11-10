<?php
// generate_richmenu_image.php
header('Content-Type: image/png');

// สร้างรูป 2500x1686
$width = 2500;
$height = 1686;
$image = imagecreatetruecolor($width, $height);

// สี
$bgColor = imagecolorallocate($image, 26, 26, 26); // #1a1a1a
$goldColor = imagecolorallocate($image, 255, 215, 0); // #ffd700
$darkGold = imagecolorallocate($image, 218, 165, 32); // #daa520
$whiteColor = imagecolorallocate($image, 255, 255, 255);

// เติมพื้นหลัง
imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

// วาดเส้นแบ่ง
$lineColor = imagecolorallocate($image, 50, 50, 50);
imageline($image, $width/2, 0, $width/2, $height, $lineColor); // เส้นแนวตั้ง
imageline($image, 0, $height/2, $width, $height/2, $lineColor); // เส้นแนวนอน

// วาดกล่องแต่ละส่วน
$sections = [
    ['x' => 312, 'y' => 200, 'text' => '📊', 'subtext' => 'ดูแนวรับ-จุดเข้าซื้อ'],
    ['x' => 1562, 'y' => 200, 'text' => '💰', 'subtext' => 'คำนวณต้นทุนเฉลี่ย'],
    ['x' => 312, 'y' => 1050, 'text' => '📈', 'subtext' => 'คำนวณดอกเบี้ยทบต้น'],
    ['x' => 1562, 'y' => 1050, 'text' => '📚', 'subtext' => 'พื้นฐานหุ้น']
];

$fontPath = __DIR__ . '/fonts/NotoSansThai-Regular.ttf'; // ต้องมีฟอนต์ภาษาไทย

foreach ($sections as $section) {
    // วาด Emoji (ใช้ text แทน)
    imagettftext($image, 120, 0, $section['x'], $section['y'], $goldColor, $fontPath, $section['text']);
    
    // วาดข้อความ
    imagettftext($image, 50, 0, $section['x'] - 200, $section['y'] + 150, $whiteColor, $fontPath, $section['subtext']);
}

// Output
imagepng($image);
imagedestroy($image);
?>