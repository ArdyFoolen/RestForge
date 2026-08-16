<?php

namespace App\Core;

use App\Controllers\ConfigController;
use App\Storage\Storage;

final class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$config = require dirname(__DIR__, 2) . '/Config.php';

        self::createConfig();

        $records = Storage::list(
            ConfigController::COLLECTION
        );

        if (!empty($records)) {
            self::$config = array_merge(
                self::$config,
                $records[0]
            );
        }

        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        return self::$config[$key] ?? $default;
    }

    private static function createConfig(): void
    {
        if (!Storage::hasRecords(ConfigController::COLLECTION)) {
            Storage::create(
                ConfigController::COLLECTION,
                [
                    'jwt_secret' => bin2hex(random_bytes(32)),
                    'app_secret' => bin2hex(random_bytes(32))
                ]
            );
        }
    }
}
