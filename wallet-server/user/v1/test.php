this file is test.php??
<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
echo "Checking PHPMailer installation...\n\n";
// Check if PHPMailer class exists
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "✓ PHPMailer is installed!\n";
    echo "PHPMailer class found: " . PHPMailer\PHPMailer\PHPMailer::class . "\n";
} else {
    echo "✗ PHPMailer is NOT installed\n";
    echo "You need to run: composer require phpmailer/phpmailer\n";
}
echo "\n";
// Check composer.json for PHPMailer
$composerJsonPath = __DIR__ . '/../../../composer.json';
if (file_exists($composerJsonPath)) {
    $composerData = json_decode(file_get_contents($composerJsonPath), true);
    echo "Packages in composer.json:\n";
    if (isset($composerData['require'])) {
        foreach ($composerData['require'] as $package => $version) {
            echo "  - $package: $version\n";
        }
    }
} else {
    echo "composer.json not found!\n";
}
?>