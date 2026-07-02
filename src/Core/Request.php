<?php

namespace App\Core;

class Request
{
	public static function method(): string
	{
		return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
	}
	
	public static function path(): string
	{
		$uri = self::uri();
		
		$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
		
		if ($scriptPath !== '/' && str_starts_with($uri, $scriptPath)) {
			$uri = substr($uri, strlen($scriptPath));
		}
		
		return $uri ?: '/';
	}
	
	public static function uri(): string
	{
		return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	}
	
	public static function body(): array
	{
		$raw = file_get_contents('php://input');
		
		if ($raw === '') {
			return [];
		}

		$json = json_decode($raw, true);
		
		return is_array($json)
			? $json
			: [];
	}
	
	public static function query(string $key = null, mixed $default = null)
	{
		if ($key === null) {
			return $_GET;
		}
		
		foreach ($_GET as $queryKey => $value) {
			if (strcasecmp($queryKey, $key) === 0) {
				return $value;
			}
		}
		
		return $default;
	}
	
	public static function header(string $name): ?string
	{
		$headers = function_exists('getallheaders')
			? getallheaders()
			: [];
		
		foreach ($headers as $key => $value) {
			if (strcasecmp($key, $name) === 0) {
				return $value;
			}
		}
		
		return null;
	}
	
	public static function bearerToken() : ?string
	{
		$auth = self::header('Authorization');
		
		if (!$auth) {
			return null;
		}
		
		if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
			return null;
		}
		
		return trim($matches[1]);
	}
}
