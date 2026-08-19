<?php

namespace App\Core;

class Response
{
	public static function json(array $data, int $status = 200): never
	{
		http_response_code($status);
		
		header('Content-Type: application/json');
		
		echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		
		exit;
	}

	public static function success(array $data = [], int $status = 200): never
	{
		self::json([
			'success' => true,
			'data' => $data
		], $status);
	}
	
	public static function error(string|array $message, int $status = 400): never
	{
		self::json([
			'success' => false,
			'error' => $message
		], $status);
	}
	
	public static function html(string $html, int $status = 200): never
	{
		http_response_code($status);
		
		header('Content-Type: text/html; charset=UTF-8');
		
		echo $html;
		
		exit;
	}

	public static function ics(string $ics): never
	{
		http_response_code(200);

		header('Content-Type: text/calendar; charset=utf-8');
		header('Cache-Control: no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');

		echo $ics;
		
		exit;
	}
	
	public static function file(
		string $path,
		string $contentType,
		int $status = 200
	): never
	{
		
		http_response_code($status);
		
		header("Content-Type: {$contentType}; charset=UTF-8");
		
		readfile($path);
		
		exit;
	}
}
