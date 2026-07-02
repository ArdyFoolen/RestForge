<?php

namespace App\Core;

class ListOptions
{
	private const RESERVED_QUERY_PARAMETERS = [
		'orderby',
		'descending',
		'offset',
		'limit'
	];

	public static function filters(): array
	{
		$filters = [];

		foreach (Request::query() as $key => $value) {

			if (in_array(strtolower($key), self::RESERVED_QUERY_PARAMETERS, true)) {
				continue;
			}
			
			$filters[$key] = $value;
		}
		
		return $filters;
	}
	
	public static function orderBy(): string
	{
		return Request::query('orderBy', 'created_at');
	}
	
	public static function descending(): bool
	{
		return filter_var(
			Request::query('descending', true),
			FILTER_VALIDATE_BOOLEAN
		);
	}
	
	public static function offset(): int
	{
		return (int)Request::query('offset', 0);
	}
	
	public static function limit(): ?int
	{
		$limit = Request::query('limit');
		
		return $limit !== null
			? (int) $limit
			: null;
	}
}
