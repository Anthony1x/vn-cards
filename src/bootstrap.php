<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;

try {
    Config::load();
} catch (Exception $e) {
    die("Configuration Error: " . $e->getMessage() . "\n");
}
