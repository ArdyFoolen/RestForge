<?php

declare(strict_types=1);

namespace App\Security;

use App\Controllers\UserController;
use App\Controllers\SessionController;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Storage\Storage;

class Jwt
{
	private static ?array $principal = null;
	private static ?string $sessionId = null;
	
	public static function clearCache(): void
	{
		self::$principal = null;
		self::$sessionId = null;
	}
	
	/*
	 * Creates a JWT
	*/
	public static function create(string $subject, string $sessionId): string
	{
		$header = [
			'alg' => 'HS256',
			'typ' => 'JWT'
		];
		
		$now = time();
		
		$payload = [
			'sub' => $subject,
			'iss' => Config::get('app_name'),
			'iat' => $now,
			'exp' => $now + Config::get('token_lifetime'),
			'jti' => bin2hex(random_bytes(16)),
			'sid' => $sessionId
		];
		
		$headerEncoded = self::base64UrlEncode(
			json_encode($header, JSON_UNESCAPED_SLASHES)
		);
		
		$payloadEncoded = self::base64UrlEncode(
			json_encode($payload, JSON_UNESCAPED_SLASHES)
		);
		
		$signature = self::sign(
			$headerEncoded,
			$payloadEncoded
		);
		
		return implode('.', [
			$headerEncoded,
			$payloadEncoded,
			$signature
		]);
	}

	/*
	 * Verifies a JWT.
	 * Returns the payload if valid otherwise null
	*/
	public static function verify(string $token): ?array
	{
		$parts = explode('.', $token);
		
		if (count($parts) !== 3) {
			return null;
		}
		
		[$header, $payload, $signature] = $parts;
		
		$headerData = json_decode(
			self::base64UrlDecode($header),
			true
		);
		
		if (!is_array($headerData)) {
			return null;
		}
		
		if (($headerData['alg'] ?? '') !== 'HS256') {
			return null;
		}
		
		if (($headerData['typ'] ?? '') !== 'JWT') {
			return null;
		}

		$expected = self::sign($header, $payload);
		
		if (!hash_equals($expected, $signature)) {
			return null;
		}
		
		$decoded = json_decode(
			self::base64UrlDecode($payload),
			true
		);
		
		if (!is_array($decoded)) {
			return null;
		}
		
		$required = [
			'sub',
			'iss',
			'iat',
			'exp',
			'jti',
			'sid'
		];
		foreach ($required as $claim) {
			if (!array_key_exists($claim, $decoded)) {
				return null;
			}
		}
	
		if ($decoded['exp'] < time()) {
			return null;
		}
		
		if (($decoded['iss'] ?? '') !== Config::get('app_name')) {
			return null;
		}

		$session = Storage::read(
			SessionController::COLLECTION,
			$decoded['sid']
		);
		if ($session === null || $session['revoked'] !== false) {
			return null;
		}

		return $decoded;
	}

	/*
	 * Principal.
	 * Returns the user of the principal
	*/
	public static function principal(): ?array
	{
		if (self::$principal !== null) {
			return self::$principal;
		}

		$token = Request::bearerToken();
		
		if ($token === null) {
			return null;
		}
		
		$parts = explode('.', $token);
		
		if (count($parts) !== 3) {
			return null;
		}
		
		[$header, $payload, $signature] = $parts;
		
		$decoded = json_decode(
			self::base64UrlDecode($payload),
			true
		);
		
		if (!is_array($decoded)) {
			return null;
		}
		
		$username = $decoded['sub'];
		
		if ($username === null) {
			return null;
		}

		$user = Storage::findFirst(UserController::COLLECTION, [
			'username' => $username
		]);
		
		if ($user === null) {
			Response::error('Invalid token', 401);
		}
		
		self::$principal = $user;
		
		return self::$principal;
	}
	
	/*
	 * Session.
	 * Returns the sessionid of the principal
	*/
	public static function sessionId(): ?string
	{
		if (self::$sessionId !== null) {
			return self::$sessionId;
		}

		$token = Request::bearerToken();
		
		if ($token === null) {
			return null;
		}
		
		$parts = explode('.', $token);
		
		if (count($parts) !== 3) {
			return null;
		}
		
		[$header, $payload, $signature] = $parts;
		
		$decoded = json_decode(
			self::base64UrlDecode($payload),
			true
		);
		
		if (!is_array($decoded)) {
			return null;
		}
		
		$sid = $decoded['sid'];
		
		if ($sid === null) {
			Response::error('Invalid token', 401);
		}
		
		self::$sessionId = $sid;
		
		return self::$sessionId;
	}
	
	public static function sign(
		string $header,
		string $payload
	): string
	{
		$signature = hash_hmac(
			'sha256',
			$header . '.' . $payload,
			Config::get('jwt_secret'),
			true
		);
		
		return self::base64UrlEncode($signature);
	}
	
	/*
	 * Base64 URL encoding.
	*/
	private static function base64UrlEncode(string $data): string
	{
		return trim(
			strtr(base64_encode($data), '+/', '-_'),
			'='
		);
	}
	
	/*
	 * Base64 URL decoding.
	*/
	private static function base64UrlDecode(string $data): string
	{
		$padding = strlen($data) % 4;
		if ($padding > 0) {
			$data .= str_repeat('=', 4 - $padding);
		}
		
		return base64_decode(
			strtr($data, '-_', '+/')
		);
	}
}
