<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Security\Roles;

final class Validator
{
	public static function required(array $data, array $fields): void
	{
		foreach ($fields as $field) {
			
			if (
				!array_key_exists($field, $data) ||
				$data[$field] === null ||
				(is_string($data[$field]) && trim($data[$field]) === '')
			) {
				Response::error("Field '{$field}' is required.");
			}
			
		}
	}

	public static function string(array $data, string $field): void
	{
			
		if (
			array_key_exists($field, $data) &&
			!is_string($data[$field])
		) {
			Response::error("Field '{$field}' must be a string.");
		}
			
	}

	public static function boolean(array $data, string $field): void
	{
			
		if (
			array_key_exists($field, $data) &&
			!is_bool($data[$field])
		) {
			Response::error("Field '{$field}' must be a boolean.");
		}
			
	}

	public static function array(array $data, string $field): void
	{
			
		if (
			array_key_exists($field, $data) &&
			!is_array($data[$field])
		) {
			Response::error("Field '{$field}' must be an array.");
		}
			
	}

	public static function minLength(array $data, string $field, int $length): void
	{
			
		if (
			array_key_exists($field, $data) &&
			is_string($data[$field]) &&
			mb_strlen(trim($data[$field])) < $length
		) {
		Response::error("Field '{$field}' must be at least {$length} characters.");
		}
			
	}

	public static function maxLength(array $data, string $field, int $length): void
	{
			
		if (
			array_key_exists($field, $data) &&
			is_string($data[$field]) &&
			mb_strlen(trim($data[$field])) > $length
		) {
			Response::error("Field '{$field}' must be at most {$length} characters.");
		}
			
	}

	public static function enum(array $data, string $field, array $values): void
	{
			
		if (
			array_key_exists($field, $data) &&
			!in_array($data[$field], $values, true)
		) {
			Response::error("Field '{$field}' must be one of: {implode(', ', $values)}.");
		}
			
	}

	public static function pattern(array $data, string $field, string $pattern): void
	{
			
		if (
			array_key_exists($field, $data) &&
			is_string($data[$field]) &&
			preg_match($pattern, $data[$field]) !== 1
		) {
			Response::error("Field '{$field}' has an invalid format.");
		}
			
	}

	public static function roles(array $data): void
	{
		
		if (!array_key_exists('roles', $data)) {
			return;
		}
			
		if (!is_array($data['roles'])) {
			Response::error('Field \'roles\' must be an array.');
		}
		
		foreach($data['roles'] as $role) {
			
			if (!in_array($role, Roles::VALID, true)) {
				Response::error("Invalid role '{$role}'");
			}
			
		}
		
	}
}
