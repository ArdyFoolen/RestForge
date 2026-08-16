<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Storage\Storage;

class ConfigController
{
	public const COLLECTION = 'config';
	
	public function list(): void
	{
		$records = Storage::list(
			self::COLLECTION);

		Response::success(
			$records
		);
	}
	
	public function update(): void
	{
		$records = Storage::list(
			self::COLLECTION);

		$updated = Storage::update(
			self::COLLECTION,
			$records[0]['id'],
			Request::body()
		);
		
		if (!$updated) {
			Response::error(
				'Config not updated',
				404
			);
		}
		
		Response::success([], 204);
	}
}

