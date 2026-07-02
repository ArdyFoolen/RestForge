<?php

namespace App\Core;

final class Config
{
	private static array $config = [];
	
	public static function load(): void
	{
		self::$config = require dirname(__DIR__,2) . '/Config.php';
	}
	
	public static function get(string $key, $default = null)
	{
		return self::$config[$key] ?? $default;
	}
}
