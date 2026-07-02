<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\UserController;
use App\Core\Response;
use App\Storage\Storage;
use App\Core\ListOptions;

class LogController
{
	public const COLLECTION = 'logs';
	
	public function list(): void
	{
		$logs = Storage::list(
			self::COLLECTION,
			ListOptions::filters(),
			ListOptions::orderBy(),
			ListOptions::descending(),
			ListOptions::offset(),
			ListOptions::limit()
		);
		
		foreach ($logs as &$log) {
			if ($log['principal_id'] !== null) {
				$user = Storage::read(
					UserController::COLLECTION,
					$log['principal_id']
				);
				
				unset($user['password']);
				
				$log['principal'] = $user;
			}
		}
		
		Response::success(
			$logs
		);
	}
	
	public function read(string $id): void
	{
		$log = Storage::read(
			self::COLLECTION,
			$id
		);
		
		if ($log === null) {
			Response::error(
				'Item not found',
				404
			);
		}

		if ($log['principal_id'] !== null) {
			$user = Storage::read(
				UserController::COLLECTION,
				$log['principal_id']
			);

			unset($user['password']);

			$log['principal'] = $user;
		}
		
		Response::success($log);
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
		
		Response::success([
			'deleted' => $deleted,
			204
		]);
	}
}
