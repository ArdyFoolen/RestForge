<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Validator;
use App\Controllers\UserController;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Security\Jwt;
use App\Security\Roles;
use App\Storage\Storage;

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
		
		$token = Jwt::create($username);
		
		Response::success([
			'token' => $token,
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
}
