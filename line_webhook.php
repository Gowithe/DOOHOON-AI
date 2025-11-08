<?php
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
  "status" => "ok",
  "file" => __FILE__,
  "time" => date("Y-m-d H:i:s")
]);
exit;
?>
