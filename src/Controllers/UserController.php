<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Validator;
use App\Controllers\AuthController;
use App\Core\Request;
use App\Core\Response;
use App\Storage\Storage;
use App\Core\ListOptions;
use App\Security\Jwt;
use App\Security\Roles;
use App\Security\Restrictions;

class UserController
{
	public const COLLECTION = 'users';
	
	public function create(): void
	{
		$data = Request::body();
		
		Validator::required($data, [
			'username',
			'password'
		]);

		Validator::string($data, 'username');
		Validator::string($data, 'password');

		Validator::minLength($data, 'username', 3);
		Validator::minLength($data, 'password', 8);

		Validator::roles($data);
		Validator::boolean($data, 'enabled');

		if (Storage::findFirst(self::COLLECTION, [
			'username' => $data['username']
		])) {
			Response::error('Username already exists.');
		}
		
		$data['password'] = AuthController::hashPassword($data['password']);
		$data['roles'] ??= ['user'];
		$data['enabled'] = true;
		$data['restrictions'] = array_unique(array_merge(
			$data['restrictions'] ?? [],
			[Restrictions::USER_PASSWORD_CHANGE_REQUIRED]
		));
		
		$id = Storage::create(
			self::COLLECTION,
			$data
		);
		
		Response::success([
			'id' => $id
		], 201);
	}
	
	public function list(): void
	{
		$users = Storage::list(
			self::COLLECTION,
			ListOptions::filters(),
			ListOptions::orderBy(),
			ListOptions::descending(),
			ListOptions::offset(),
			ListOptions::limit()
		);

		$users = array_map(
			[$this, 'sanitize'],
			$users
		);
		
		Response::success(
			$users
		);
	}
	
	public function read(string $id): void
	{
		$user = Storage::read(
			self::COLLECTION,
			$id
		);
		
		if ($user === null) {
			Response::error(
				'Item not found',
				404
			);
		}
		
		Response::success($this->sanitize($user));
	}
	
	public function update(string $id): void
	{
		$data = Request::body();

		Validator::string($data, 'username');
		Validator::string($data, 'password');

		Validator::minLength($data, 'username', 3);
		Validator::minLength($data, 'password', 8);

		Validator::roles($data);
		Validator::boolean($data, 'enabled');
		
		if(isset($data['password'])) {
			Response::error('Not allowed to change password', 403);
		}
		
		$user = Storage::read(
			self::COLLECTION,
			$id
		);

		if ($user === null) {
			Response::error('User does not exist.');
		}
		
		if (isset($data['username'])) {
			
			$existing = Storage::findFirst(
				self::COLLECTION,
				['username' => $data['username']]
			);
			
			if ($existing !== null &&
				$existing['id'] !== $id) {
				
				Response::error('Username already exists', 409);
				
			}
			
		}
		
		self::assertOwner($id, $data);
		if (isset($data['enabled']) && $data['enabled'] === false && $id === Jwt::principal()['id']) {
			Response::error('Not allowed to disable one self.', 403);
		}
		
		$updated = Storage::update(
			self::COLLECTION,
			$id,
			$data
		);
		
		if (!$updated) {
			Response::error(
				'Item not found',
				404
			);
		}
		
		Response::success([
			'updated' => $updated
		]);
	}
	
	public function whoAmI(): void
	{
		$principal = Jwt::principal();

		Response::success($this->sanitize($principal));
	}
	
	public function changePassword(string $id): void
	{
		$data = Request::body();
		
		Validator::required($data, [
			'oldpassword',
			'newpassword'
		]);

		Validator::string($data, 'oldpassword');
		Validator::string($data, 'newpassword');

		Validator::minLength($data, 'password', 8);

		$user = Storage::read(
			self::COLLECTION,
			$id
		);

		if ($user === null) {
			Response::error('User does not exist.');
		}

		if ($id !== Jwt::principal()['id']) {
			Response::error('Not allowed to change someone elses password.', 403);
		}

		if (!AuthController::verifyPassword($data['oldpassword'], $user['password'])) {
			Response::error('Old password is incorrect.');
		}
		
		$user['password'] = AuthController::hashPassword($data['newpassword']);
		$user['restrictions'] = array_values(array_filter(
			$user['restrictions'] ?? [],
			fn($restriction) => $restriction !== Restrictions::USER_PASSWORD_CHANGE_REQUIRED
		));

		$updated = Storage::update(
			self::COLLECTION,
			$id,
			$user
		);
		
		if (!$updated) {
			Response::error(
				'Item not found',
				404
			);
		}
		
		Response::success([
			'updated' => $updated
		]);
	}
	
	public function resetPassword(string $id): void
	{
		$data = Request::body();
		
		Validator::required($data, [
			'newpassword'
		]);

		Validator::string($data, 'newpassword');

		Validator::minLength($data, 'password', 8);

		$user = Storage::read(
			self::COLLECTION,
			$id
		);

		if ($user === null) {
			Response::error('User does not exist');
		}
		
		$user['password'] = AuthController::hashPassword($data['newpassword']);
		
		$updated = Storage::update(
			self::COLLECTION,
			$id,
			$user
		);
		
		if (!$updated) {
			Response::error(
				'Item not found',
				404
			);
		}
		
		Response::success([
			'updated' => $updated
		]);
	}

	public function delete(string $id): void
	{
		self::assertOwner($id);

		$deleted = Storage::delete(
			self::COLLECTION,
			$id
		);
		
		if (!$deleted) {
			Response::error(
				'Item not found',
				404
			);
		}
		
		Response::success([], 204);
	}
	
	private static function assertOwner(string $id, ?array $data = null): void
	{
		$existing = Storage::read(
			self::COLLECTION,
			$id
		);
		
		$wasOwner = in_array(
			Roles::OWNER,
			$existing['roles'],
			true
		);
		
		$willBeOwner = $data === null
			? false
			: in_array(
				Roles::OWNER,
				$data['roles'] ?? $existing['roles'],
				true
		);
		
		if (
			$wasOwner &&
			!$willBeOwner &&
			self::countOwners() === 1
		) {
			Response::error('At least one owner must exist.');
		}
	}
	
	private static function countOwners(): int
	{
		return Storage::count(
			'users',
			[
				'roles' => [Roles::OWNER]
			]
		);
	}
	
	private static function sanitize(array $user): array
	{
		unset($user['password']);
		return $user;
	}
}

