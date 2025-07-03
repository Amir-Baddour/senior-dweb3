<?php
// mail_config.php

return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'amirbaddour675@gmail.com',
    'password' => getenv('MAIL_PASSWORD'),
    'from_email' => 'amirbaddour675@gmail.com',
    'from_name' => 'Your Wallet App',
];