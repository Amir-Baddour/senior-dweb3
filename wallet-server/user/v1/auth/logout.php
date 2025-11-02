<?php
require_once __DIR__ . '/../../../utils/cors.php';

$response = [
    "status" => "success",
    "message" => "Logout successful. Please remove your token client-side."
];

echo json_encode($response);