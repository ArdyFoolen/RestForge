<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Validator;
use App\Controllers\UserController;
use App\Controllers\SessionController;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Security\Jwt;
use App\Security\Roles;
use App\Storage\Storage;
use DateTimeImmutable;

class AuthController
{
	public function login(): void
	{
		$data = Request::body();

		Validator::required($data, [
			'username',
			'password'
		]);

		Validator::string($data, 'username');
		Validator::string($data, 'password');
		
		$username = trim($data['username']?? '');
		$password = trim($data['password'] ?? '');
		
		Jwt::clearCache();

		$this->setupOwner();

		$user = Storage::findFirst(UserController::COLLECTION, [
			'username' => $username
		]);
		
		if ($user === null || !$user['enabled'] || !self::verifyPassword($password, $user['password'])) {
				Response::error('Invalid username or password.', 401);
		}
		
		$refreshToken = bin2hex(random_bytes(64));
		$sessionId = $this->setupSession($user['id'], $refreshToken);
		
		$token = Jwt::create($username, $sessionId);

		Response::success([
			'token' => $token,
			'refresh_token' => $refreshToken,
			'expires_in' => Config::get('token_lifetime')
		]);
	}

	public function logout(): void
	{
		$session = [
			'revoked' => true,
		];
		
		Storage::update(SessionController::COLLECTION, Jwt::sessionId(), $session);

		Response::success([], 204);
	}

	public function refresh(): void
	{
		$data = Request::body();

		Validator::required($data, [
			'refresh_token',
		]);

		Validator::string($data, 'refresh_token');
		
		$id = trim($data['refresh_token']?? '');

		$sessionId = Jwt::sessionId();
		
		if (($sessionId ?? '') == '') {
				Response::error('Invalid session id.', 400);
		}
		
		$session = Storage::read(SessionController::COLLECTION, $sessionId);
		
		if ($session === null || $session['revoked'] === true) {
			Response::error('Invalid session.', 401);
		}

		$currentRefreshTokenHash = hash_hmac(
			'sha256',
			$id,
			Config::get('app_secret')
		);

		$index = true;
		foreach ($session['refresh_tokens'] as $i => $refreshToken) {
			if ($refreshToken['token_hash'] === $currentRefreshTokenHash) {

				$index = $i;

				if (strtotime($refreshToken['expires_at']) < time()) {
					$this->invalidateSessionAndRefreshTokens($session);
					Response::error('Invalid refresh token.', 401);
				}

				if ($refreshToken['revoked'] === true) {
					$this->invalidateSessionAndRefreshTokens($session);
					Response::error('Invalid refresh token.', 401);
				}

				break;
			}
		}

		if ($index === null) {
			$this->invalidateSessionAndRefreshTokens($session);
			Response::error('Invalid refresh token.', 401);
		}

		$newRefreshToken = bin2hex(random_bytes(64));
		$newRefreshTokenHash = hash_hmac(
			'sha256',
			$newRefreshToken,
			Config::get('app_secret')
		);

		$now = new DateTimeImmutable();
		$expiresAt = $now
			->modify(Config::get('refresh_expiration'))
			->format('Y-m-d H:i:s');

		$newToken = [
			'token_hash' => $newRefreshTokenHash,
			'parent_id'  => $currentRefreshTokenHash,
			'expires_at' => $expiresAt,
			'revoked'    => false,
		];

		// Mark current token as revoked
		$session['refresh_tokens'][$index]['revoked'] = true;

		// Add the new refresh token
		$session['refresh_tokens'][] = $newToken;

		usort(
			$session['refresh_tokens'],
			fn($a, $b) => strtotime($a['expires_at']) <=> strtotime($b['expires_at'])
		);

		// Keep only the 5 newest tokens
		while (count($session['refresh_tokens']) > 5) {
			array_shift($session['refresh_tokens']);
		}

		Storage::update(SessionController::COLLECTION, $session['id'], $session);

		$user = Jwt::principal();
		$token = Jwt::create($user['username'], $sessionId);

		Response::success([
			'token' => $token,
			'refresh_token' => $newRefreshToken,
			'expires_in' => Config::get('token_lifetime')
		]);
	}

	public static function verifyPassword(string $password, string $hash): bool
	{
		return password_verify($password, $hash);
	}
	
	public static function hashPassword(string $password): string
	{
		return password_hash($password, PASSWORD_DEFAULT);
	}

	private function setupOwner(): void
	{
		$owner = Storage::findFirst(UserController::COLLECTION, [
			'roles' => [Roles::OWNER]
		]);

		if ($owner === null) {
			
			$owner = [
				'username' => Config::get('default_owner_username'),
				'password' => self::hashPassword(Config::get('default_owner_password')),
				'roles' => [Roles::OWNER],
				'enabled' => true
			];
			
			Storage::create(UserController::COLLECTION, $owner);
		}
	}
	
	private function invalidateSessionAndRefreshTokens(array $session): void
	{
		$session['revoked'] = true;
		foreach ($session['refresh_tokens'] as &$refreshToken) {
			$refreshToken['revoked'] = true;
		}
		unset($refreshToken);

		Storage::update(SessionController::COLLECTION, $session['id'], $session);
	}
	
	private function setupSession(string $userId, string $refreshToken): string
	{
		$now = new DateTimeImmutable();
		$absoluteExpiresAt = $now
			->modify(Config::get('refresh_absolute_expiration'))
			->format('Y-m-d H:i:s');
		$expiresAt = $now
			->modify(Config::get('refresh_expiration'))
			->format('Y-m-d H:i:s');
	
		$refreshTokenHash = hash_hmac(
			'sha256',
			$refreshToken,
			Config::get('app_secret')
		);

		$session = [
			'user_id' => $userId,
			'absolute_expires_at' => $absoluteExpiresAt,
			'revoked' => false,
			'refresh_tokens' => [[
				'token_hash' => $refreshTokenHash,
				'parent_id' => null,
				'expires_at' => $expiresAt,
				'revoked' => false
			]]
		];
		
		$id = Storage::create(SessionController::COLLECTION, $session);
		
		return $id;
	}
}
