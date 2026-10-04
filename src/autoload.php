<?php

declare(strict_types=1);

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

$prefix = 'Hartenthaler\\Webtrees\\Module\\ExidModule\\';
$base   = __DIR__ . '/';

spl_autoload_register(static function (string $class) use ($prefix, $base): void {
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});
