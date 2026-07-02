<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;
use ErrorException;
use App\Controllers\LogController;
use App\Core\Config;
use App\Core\Response;
use App\Storage\Storage;
use App\Security\Jwt;

final class Bootstrap
{
	public static function initialize(): void
	{
		Config::load();
		self::initializePhp();

		self::registerErrorHandler();
		self::registerExceptionHandler();
		self::registerShutdownHandler();
	}

	private static function initializePhp(): void
	{
		error_reporting(E_ALL);
		
		ini_set(
			'display_errors',
			Config::get('DEBUG') ? '1' : '0'
		);
		
		date_default_timezone_set(
			Config::get('timezone')
		);
	}


	private static function registerErrorHandler(): void
	{
		set_error_handler(function (
			int $severity,
			string $message,
			string $file,
			int $line
		):bool {

			$principal = Jwt::principal();

			$log = [
				'principal_id' => $principal === null ? null : $principal['id'],
				'message' => $message,
				'severity' => $severity,
				'file' => $file,
				'line' => $line
			];

			Storage::create(LogController::COLLECTION, $log);

			throw new ErrorException(
				$message,
				0,
				$severity,
				$file,
				$line
			);
		
		});

	}
	
	private static function registerExceptionHandler(): void
	{
		
		set_exception_handler(function (Throwable $exception): void
		{

			$principal = Jwt::principal();

			$log = [
				'principal_id' => $principal === null ? null : $principal['id'],
				'type' => get_class($exception),
				'message' => $exception->getMessage(),
				'file' => $exception->getFile(),
				'line' => $exception->getLine()
			];

			Storage::create(LogController::COLLECTION, $log);

			if (Config::get('DEBUG')) {
				Response::error(
						[
							'type' => get_class($exception),
							'message' => $exception->getMessage(),
							'file' => $exception->getFile(),
							'line' => $exception->getLine()
						],
					500
				);
			}
			
			Response::error('Internal server error', 500);
			
		});
		
	}
	
	private static function registerShutdownHandler(): void
	{
		
		register_shutdown_function(function (): void {
			
			$error = error_get_last();
			
			if ($error === null) {
				return;
			}

			$principal = Jwt::principal();

			$log = [
				'principal_id' => $principal === null ? null : $principal['id'],
				'error' => $error
			];

			Storage::create(LogController::COLLECTION, $log);
			
			Response::error('Internal server error', 500);
			
		});
		
	}
}
