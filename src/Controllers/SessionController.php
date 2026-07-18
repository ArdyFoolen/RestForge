<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\UserController;
use App\Core\Request;
use App\Core\Response;
use App\Storage\Storage;
use App\Core\ListOptions;

class SessionController
{
	public const COLLECTION = 'sessions';
	
	public function list(): void
	{
		$sessions = Storage::list(
			self::COLLECTION,
			ListOptions::filters(),
			ListOptions::orderBy(),
			ListOptions::descending(),
			ListOptions::offset(),
			ListOptions::limit()
		);
		
		foreach ($sessions as &$session) {
			if ($session['user_id'] !== null) {
				$user = Storage::read(
					UserController::COLLECTION,
					$session['user_id']
				);
				
				unset($user['password']);
				
				$session['user'] = $user;
			}
		}
		
		Response::success(
			$sessions
		);
	}
	
	public function read(string $id): void
	{
		$session = Storage::read(
			self::COLLECTION,
			$id
		);
		
		if ($session === null) {
			Response::error(
				'Item not found',
				404
			);
		}

		if ($session['user_id'] !== null) {
			$user = Storage::read(
				UserController::COLLECTION,
				$session['user_id']
			);

			unset($user['password']);

			$session['user'] = $user;
		}
		
		Response::success($session);
	}

	public function update(string $id): void
	{
		$updated = Storage::update(
			self::COLLECTION,
			$id,
			Request::body()
		);
		
		if (!$updated) {
			Response::error(
				'Item not found',
				404
			);
		}
		
		Response::success([], 204);
	}
	
	public function delete(string $id): void
	{
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
	
	public function deleteArray(): void
	{
		$data = Request::body();

		$deleted = Storage::deleteArray(
			self::COLLECTION,
			$data
		);
		
		if (!$deleted) {
			Response::error(
				'Item not found',
				404
			);
		}
		
		Response::success([], 204);
	}
}
