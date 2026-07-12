<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Storage\Storage;
use App\Core\ListOptions;

class ItemController
{
	public const COLLECTION = 'items';
	
	public function create(): void
	{
		$data = Request::body();
		
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
		$records = Storage::list(
			self::COLLECTION,
			ListOptions::filters(),
			ListOptions::orderBy(),
			ListOptions::descending(),
			ListOptions::offset(),
			ListOptions::limit()
		);

		Response::success(
			$records
		);
	}
	
	public function read(string $id): void
	{
		$item = Storage::read(
			self::COLLECTION,
			$id
		);
		
		if ($item === null) {
			Response::error(
				'Item not found',
				404
			);
		}
		
		Response::success($item);
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
}

