<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit607367f316dc0e94114beee62316416b
{
    public static $files = array (
        '320cde22f66dd4f5d3fd621d3e88b98f' => __DIR__ . '/..' . '/symfony/polyfill-ctype/bootstrap.php',
        '0e6d7bf4a5811bfa5cf40c5ccd6fae6a' => __DIR__ . '/..' . '/symfony/polyfill-mbstring/bootstrap.php',
        'a4a119a56e50fbb293281d9a48007e0e' => __DIR__ . '/..' . '/symfony/polyfill-php80/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Symfony\\Polyfill\\Php80\\' => 23,
            'Symfony\\Polyfill\\Mbstring\\' => 26,
            'Symfony\\Polyfill\\Ctype\\' => 23,
        ),
        'P' => 
        array (
            'PhpOption\\' => 10,
            'PHPMailer\\PHPMailer\\' => 20,
        ),
        'G' => 
        array (
            'GrahamCampbell\\ResultType\\' => 26,
        ),
        'E' => 
        array (
            'Endroid\\QrCode\\' => 15,
        ),
        'D' => 
        array (
            'Dotenv\\' => 7,
            'DASPRiD\\Enum\\' => 13,
        ),
        'B' => 
        array (
            'BaconQrCode\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Symfony\\Polyfill\\Php80\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-php80',
        ),
        'Symfony\\Polyfill\\Mbstring\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-mbstring',
        ),
        'Symfony\\Polyfill\\Ctype\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-ctype',
        ),
        'PhpOption\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpoption/phpoption/src/PhpOption',
        ),
        'PHPMailer\\PHPMailer\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpmailer/phpmailer/src',
        ),
        'GrahamCampbell\\ResultType\\' => 
        array (
            0 => __DIR__ . '/..' . '/graham-campbell/result-type/src',
        ),
        'Endroid\\QrCode\\' => 
        array (
            0 => __DIR__ . '/..' . '/endroid/qr-code/src',
        ),
        'Dotenv\\' => 
        array (
            0 => __DIR__ . '/..' . '/vlucas/phpdotenv/src',
        ),
        'DASPRiD\\Enum\\' => 
        array (
            0 => __DIR__ . '/..' . '/dasprid/enum/src',
        ),
        'BaconQrCode\\' => 
        array (
            0 => __DIR__ . '/..' . '/bacon/bacon-qr-code/src',
        ),
    );

    public static $classMap = array (
        'Attribute' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/Attribute.php',
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'PhpToken' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/PhpToken.php',
        'Stringable' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/Stringable.php',
        'UnhandledMatchError' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/UnhandledMatchError.php',
        'ValueError' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/ValueError.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit607367f316dc0e94114beee62316416b::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit607367f316dc0e94114beee62316416b::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit607367f316dc0e94114beee62316416b::$classMap;

        }, null, ClassLoader::class);
    }
}
