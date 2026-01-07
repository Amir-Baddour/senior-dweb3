<?php
$autoload = __DIR__ . '/../../../vendor/autoload.php';
echo "Looking for: " . $autoload . "\n";
echo "File exists: " . (file_exists($autoload) ? 'YES' : 'NO') . "\n";
echo "Actual path: " . realpath($autoload) . "\n";

if (file_exists($autoload)) {
    require_once $autoload;
    echo "PHPMailer class exists: " . (class_exists(\PHPMailer\PHPMailer\PHPMailer::class) ? 'YES' : 'NO') . "\n";
} else {
    echo "ERROR: Autoload file not found!\n";
}
?>