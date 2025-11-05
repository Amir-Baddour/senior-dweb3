<?php
require_once __DIR__ . '/../../utils/cors.php';
echo json_encode(["ok" => true, "time" => time()]);
