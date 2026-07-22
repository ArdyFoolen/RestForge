<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Request;
use App\Core\Response;
use App\Security\Authorization;

final class AuthMiddleware
{
	/*
	 * Generic function
	*/
	public static function handle(array $route): void
	{

		self::authenticate();
		
		if ($route['permissions'] !== null) {
			self::authorize($route['permissions'], $route['restrictions']);
		}

	}
	
	/*
	 * Authenticates the current Request
	 * @return array The verified JWT payload
	*/
	public static function authenticate(): array
	{
		$token = Request::bearerToken();
		
		if ($token === null) {
			Response::error('Missing bearer token.', 401);
		}
		
		$payload = Jwt::verify($token);
		
		if ($payload === null) {
			Response::error('Invalid or expired token.', 401);
		}
		
		return $payload;
	}
	
	/*
	 * Authorize the current request for the current user
	*/
	public static function authorize(?array $permissions, ?array $restrictions): void
	{
		if ($permissions === null) {
			return;
		}
		
		$principal = Jwt::principal();
				
		if (!$principal['enabled']) {
			Response::error('Forbidden', 403);
		}

		$message = Authorization::restrictionMessage($principal, $restrictions);
		if ($message !== null) {
			Response::error($message, 403);
		}
		
		if (!Authorization::allows($principal, $permissions)) {
			Response::error('Forbidden', 403);
		}
	}
}
