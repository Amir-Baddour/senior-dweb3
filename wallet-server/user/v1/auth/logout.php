<?php
header("Content-Type: application/json");

$response = [
    "status" => "success",
    "message" => "Logout successful. Please remove your token client-side."
];

echo json_encode($response);