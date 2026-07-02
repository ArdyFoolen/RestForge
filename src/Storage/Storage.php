<?php

declare(strict_types=1);

namespace App\Storage;

use App\Security\Crypto;

class Storage
{
		private const RESERVED_OPERATORS = [
		'contains',
		'startswith',
		'endswith',
		'ge',
		'gt',
		'le',
		'lt'
	];

	public static function create(
		string $collection,
		array $data
	): string
	{
		$id = self::generateId();
		
		$now = gmdate('c');
		
		$data['id'] = $id;
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		
		$encrypted = Crypto::encryptArray($data);
		
		self::writeFile(
			self::filename($collection, $id),
			$encrypted
		);
		
		return $id;
	}
	
	public static function read(
		string $collection,
		string $id
	): ?array
	{
		if (!self::exists($collection, $id)) {
			return null;
		}
		
		$encrypted = self::readFile(
			self::filename($collection, $id)
		);
		
		return Crypto::decryptArray($encrypted);
	}
	
	public static function update(
		string $collection,
		string $id,
		array $data
	): bool
	{
		$existing = self::read($collection, $id);

		if ($existing === null) {
			return false;
		}

		unset(
			$data['id'],
			$data['created_at'],
			$data['updated_at']
		);

		$updated = array_merge($existing, $data);
		
		/*
		 * Never let the following values be modified by the client
		*/
		$updated['id'] = $id;
		$updated['created_at'] = $existing['created_at'];
		$updated['updated_at'] = gmdate('c');
		
		$encrypted = Crypto::encryptArray($updated);
		
		self::writeFile(
			self::filename($collection, $id),
			$encrypted
		);
		
		return true;
	}
	
	public static function delete(
		string $collection,
		string $id,
	): bool
	{
		$filename = self::filename($collection, $id);
		
		if (!file_exists($filename)) {
			return false;
		}
		
		return unlink($filename);
	}
	
	public static function list(
		string $collection,
		array $filters = [],
		string $orderBy = 'created_at',
		bool $descending = true,
		int $offset = 0,
		?int $limit = null
	): array
	{
		$records = [];
		
		foreach (glob(self::collectionPath($collection). DIRECTORY_SEPARATOR . '*.json') as $file) {
			
			$data = Crypto::decryptArray(
				self::readFile($file)
			);
			
			if ($data === null) {
				continue;
			}


			if (!self::matchesFilter($data, $filters)) {
				continue;
			}
			
			$records[] = $data;
		}
		
		usort(
			$records,
			function (array $a, array $b) use ($orderBy, $descending): int {

				$valueA = self::getValue($a, $orderBy);
				$valueB = self::getValue($b, $orderBy);
				
				if ($valueA === null && $valueB === null) {
					return 0;
				}

				if ($valueA === null) {
					return 1;
				}

				if ($valueB === null) {
					return -1;
				}

				$result = $valueA <=> $valueB;
				
				return $descending ? -$result : $result;
				
			}
		);
		
		if ($limit !== null) {
			return array_slice($records, $offset, $limit);
		}
		
		return array_slice($records, $offset);
	}

	public static function findFirst(
		string $collection,
		array $filters
	): ?array {
		
		foreach(self::list($collection) as $record) {
			
			if (self::matchesFilter($record, $filters)) {
				return $record;
			}
			
		}

		return null;
	}

	public static function count(string $collection, array $filters = []): int
	{
		return count(
			self::list(
				$collection,
				$filters
			));
	}

	private static function matchesFilter(
		array $record,
		array $filters
	): bool
	{

		foreach ($filters as $field => $filter) {

			$found = false;
			$recordValue = null;

			foreach ($record as $key => $value) {
				
				if (strcasecmp($key, $field) === 0) {
					$recordValue = $value;
					$found = true;
					break;
				}
			}

			// Handle non-associative arrays
			if (is_array($recordValue) && array_is_list($recordValue) && is_array($filter) && array_is_list($filter)) {
				
				foreach ($filter as $value) {
					
					if (!in_array($value, $recordValue, true)) {
						return false;
					}
					
				}
				
				continue;
			}

			if (!$found) {
				return false;
			}

			if (is_array($filter)) {
				
				// Operator synctax
				if (self::isOperatorArray($filter)) {

					foreach ($filter as $operator => $expected) {
						
						$operator = strtolower($operator);
						
						if (!self::matchesOperator(
							$recordValue,
							$operator,
							$expected
						)) {
							return false;
						}

					}

					continue;

				}

				// Nested object
				if (!is_array($recordValue) ||
					!self::matchesFilter($recordValue, $filter)) {
						return false;
				}
				
				continue;
			}

			// IN operator values separated by ,
			if (is_string($filter) && str_contains($filter, ',')) {
				
				$matched = false;
				
				foreach (array_map('trim', explode(',', $filter)) as $expected) {
					
					if ($recordValue == $expected) {
						$matched = true;
						break;
					}
					
				}

				if (!$matched) {
					return false;
				}
				
				continue;
			}

			// Existing equals behavior
			if (self::compare($recordValue, $filter) !== 0) {
				return false;
			}

		}
		
		return true;

	}
	
	private static function matchesOperator(
		mixed $recordValue,
		string $operator,
		mixed $expected): bool
	{
		switch($operator) {
			case 'contains':
			
				return stripos(
					(string)$recordValue,
					(string)$expected
				) !== false;
			
			case 'gt':

				return self::compare($recordValue, $expected) > 0;
			
			case 'ge':
			
				return self::compare($recordValue, $expected) >= 0;
			
			case 'lt':
			
				return self::compare($recordValue, $expected) < 0;
			
			case 'le':
			
				return self::compare($recordValue, $expected) <= 0;

			case 'not':
				
				return self::compare($recordValue, $expected) != 0;

			case 'startswith':
				
				return stripos((string)$recordValue, (string)$expected) === 0;

			case 'endswith':
				
				return strcasecmp(substr((string)$recordValue, -strlen((string)$expected)), (string)$expected) === 0;
			
			default:
				return false;
		}
	}
	
	private static function compare(mixed $left, mixed $right): int
	{
		if (is_numeric($left) && is_numeric($right)) {
			return (float)$left <=> (float)$right;
		}

		// var_dump(['is_string($left): ' => is_string($left), "is_string($right): " => is_string($right),
		 // "strtotime($left): " => strtotime($left), "strtotime($right): " => strtotime($right)]);

		if (is_string($left) && is_string($right)) {

			$leftTime = strtotime($left);
			$rightTime = strtotime($right);

			// var_dump(['Left: ' => $left, 'Right: ' => $right]);

			// ISO-8601 date comparison
			if ($leftTime !== false && $rightTime !== false) {
				return $leftTime <=> $rightTime;
			}

			return strcasecmp($left, $right);
		}
		
		// if (is_string($left) && 
			// is_string($right) && 
			// strtotime($left) !== false && 
			// strtotime($right) !== false) {
				// return strtotime($left) <=> strtotime($right);
		// }
		
		// if (is_string($left) && is_string($right)) {
			// return strcasecmp($left, $right);
		// }
		
		return $left <=> $right;
	}
	
	private static function readFile(
		string $filename
	):string
	{
		if (!file_exists($filename)) {
			throw new \RuntimeException("File not found: {$filename}");
		}
		
		$contents = file_get_contents($filename);
		
		if ($contents === false) {
			throw new \RuntimeException("Unable to read file: {$filename}");
		}
		
		return $contents;
	}

	private static function writeFile(
		string $filename,
		string $contents
	):void
	{
		$temp = $filename . '.tmp';
		
		if (file_put_contents($temp, $contents, LOCK_EX) === false) {
			throw new \RuntimeException("Unable to write file: {$filename}");
		}
		
		if (!rename($temp, $filename)) {
			@unlink($temp);

			throw new \RuntimeException("Unable to replace file: {$filename}");
		}
	}
	
	private static function exists(
		string $collection,
		string $id
	): bool
	{
		return file_exists(self::filename($collection, $id));
	}
	
	private static function validateCollection(string $collection): void
	{
		if (!preg_match('/^[A-Za-z0-9_-]+$/', $collection)) {
			throw new \InvalidArgumentException('Invalid collection name.');
		}
	}
	
	private static function collectionPath(string $collection): string
	{
		$path = dirname(__DIR__, 2)
			. DIRECTORY_SEPARATOR
			. 'Storage'
			. DIRECTORY_SEPARATOR
			. $collection;
		
		if (!is_dir($path)) {
			if (!mkdir($path, 0755, true) && !is_dir($path)) {
				throw new \RuntimeException("Unable to create storage directory: {$path}");
			}
		}
		
		return $path;
	}
	
	private static function generateId(): string
	{
		return bin2hex(random_bytes(16));
	}
	
	private static function filename(
		string $collection,
		string $id
	): string
	{
		return self::collectionPath($collection)
			. DIRECTORY_SEPARATOR
			. $id
			. '.json';
	}
	
	private static function isOperatorArray(array $filter): bool
	{
		foreach ($filter as $key => $_) {
			if (in_array(strtolower($key), self::RESERVED_OPERATORS, true)) {
				return true;
			}
		}
		
		return false;
	}
	
	private static function getValue(array $record, string $field): mixed
	{
		foreach ($record as $key => $value) {

			if (strcasecmp($key, $field) === 0) {
				return $value;
			}
			
		}
		
		return null;
	}
}
