<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Config;
use RuntimeException;

class Crypto
{
	private const CIPHER = 'aes-256-gcm';
	private const IV_LENGTH = 12;
	
	/*
	 * Encrypts plaintext and returns a Base64 string.
	*/
	public static function encrypt(string $plainText): string
	{
		$key = Config::get('encryption_key');
		
		if (!is_string($key) || strlen($key) !== 32) {
			throw new RuntimeException('Invalid encryption key');
		}
		
		$iv = random_bytes(self::IV_LENGTH);
		$tag = '';
		
		$cipherText = openssl_encrypt(
			$plainText,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ($cipherText === false) {
			throw new RuntimeException('Encryption failed');
		}

		$payLoad = [
			'version' => 1,
			'iv' => base64_encode($iv),
			'tag' => base64_encode($tag),
			'data' => base64_encode($cipherText)
		];
		
		$json = json_encode($payLoad);
		
		if ($json === false) {
			throw new RuntimeException('Failed to code encryption payload.');
		}

		return $json;
	}
	
	/*
	 * Decrypts Base64 string.
	*/
	public static function decrypt(string $encrypted): string
	{
		$key = Config::get('encryption_key');

		if (!is_string($key) || strlen($key) !== 32) {
			throw new RuntimeException('Invalid encryption key');
		}

		$payLoad = json_decode($encrypted, true);
		
		if (!is_array($payLoad)) {
			throw new RuntimeException('Invalid encrypted payload.');
		}

		foreach(['iv', 'tag', 'data'] as $field) {
			if (!isset($payLoad[$field])) {
				throw new RuntimeException("Missing payload field: {$field}");
			}
		}
		
		$iv = base64_decode($payLoad['iv'], true);
		$tag = base64_decode($payLoad['tag'], true);
		$cipherText = base64_decode($payLoad['data'], true);
		
		if ($iv === false || $tag === false || $cipherText === false) {
			throw new RuntimeException('Invalid Base64 payload');
		}
		
		$plainText = openssl_decrypt(
			$cipherText,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);
		
		if ($plainText === false) {
			throw new RuntimeException('Decryption failed');
		}
		
		return $plainText;
	}
	
	/*
	 * Encrypt an associative array
	*/
	public static function encryptArray(array $data): string
	{
		$json = json_encode(
			$data,
			JSON_UNESCAPED_UNICODE |
			JSON_UNESCAPED_SLASHES
		);
		
		if ($json === false) {
			throw new RuntimeException('Failed to encode array');
		}
		
		return self::encrypt($json);
	}
	
	/*
	 * Decrypt an associative array
	*/
	public static function decryptArray(string $encrypted): array
	{
		$json = self::decrypt($encrypted);
		
		$data = json_decode($json, true);
		
		if (!is_array($data)) {
			throw new RuntimeException('Failed to decode JSON');
		}
		
		return $data;
	}
}
