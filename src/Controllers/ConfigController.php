<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Storage\Storage;
use App\Security\Roles;

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

	public function mobile(): void
	{
		$records = Storage::list(
			self::COLLECTION);

		Response::success([
			'MobileAppVersion' => $records[0]['MobileAppVersion'] ?? '1.0.0',
			'MobileAppBuild' => $records[0]['MobileAppBuild'] ?? 1,
			'ApkFileSize' => $records[0]['ApkFileSize'] ?? 0,
			'AvailableRoles' => Roles::VALID
		]);
	}
}
