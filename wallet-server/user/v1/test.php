<?php
echo "Current dir: " . __DIR__ . "\n";
echo "Two levels up: " . realpath(__DIR__ . '/../../') . "\n";
echo "Three levels up: " . realpath(__DIR__ . '/../../../') . "\n";
echo "Vendor exists at three levels: " . (file_exists(__DIR__ . '/../../../vendor/autoload.php') ? 'YES' : 'NO');
?>