<?php

declare(strict_types=1);

namespace App\Core;

use Exception;

class Config
{
    private static array $values = [];

    /**
     * @throws Exception
     */
    public static function load(): void
    {
        $env_path = __DIR__ . '/../../.env';

        if (!file_exists($env_path)) {
            Logger::log("No .env file found at $env_path", Urgency::critical);
            throw new Exception("No .env file found.");
        }

        self::$values = parse_ini_file($env_path);

        $required = [
            'DECK_NAME',
            'FRONT_FIELD',
            'SENTENCE_AUDIO_FIELD',
            'SENTENCE_FIELD',
            'IMAGE_FIELD',
            'PREFIX'
        ];

        foreach ($required as $key) {
            if (empty(self::$values[$key])) {
                $message = "Key '$key' is missing from .env! Consult the example .env, aborting";
                Logger::log($message, Urgency::critical);
                throw new Exception($message);
            }
        }

        self::defineConstants();
    }

    private static function defineConstants(): void
    {
        foreach (self::$values as $key => $value) {
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }

    public static function get(string $key, $default = null)
    {
        return self::$values[$key] ?? $default;
    }
}
