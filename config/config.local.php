<?php
/**
 * Local override for the ExchangePro defaults in config/config.php.
 * Recreated 2026-08-12 (was missing) with the local Laragon MySQL defaults.
 * On a fresh install the web installer regenerates this file automatically.
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'exchange_cms',
        'user' => 'root',
        'pass' => '',
    ],
    'app' => [
        'name' => 'ExchangePro',
        'base_currency' => 'CAD',
    ],
];
