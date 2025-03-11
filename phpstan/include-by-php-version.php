<?php declare(strict_types=1);

$includes = [];

$phpversion = phpversion();
if (version_compare($phpversion, '8.2', '>=')) {
    $includes[] = __DIR__ . '/php-at-least-8.2.neon';
}
if (version_compare($phpversion, '8.4', '<')) {
    $includes[] = __DIR__ . '/php-below-8.4.neon';
}
if (version_compare($phpversion, '8.2', '<')) {
    $includes[] = __DIR__ . '/php-below-8.2.neon';
}
if (version_compare($phpversion, '8.1', '<')) {
    $includes[] = __DIR__ . '/php-below-8.1.neon';
}
if (version_compare($phpversion, '8.0', '<')) {
    $includes[] = __DIR__ . '/php-below-8.0.neon';
}

$config = [];
$config['includes'] = $includes;
$config['parameters']['phpVersion'] = PHP_VERSION_ID;

return $config;
