<?php
header("Content-Type: text/plain; charset=utf-8");
if (file_exists("debug_log.txt")) {
    echo file_get_contents("debug_log.txt");
} else {
    echo "❌ ไม่พบไฟล์ debug_log.txt";
}
?>
