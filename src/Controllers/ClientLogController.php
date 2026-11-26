<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Security\Jwt;
use App\Storage\Storage;

class ClientLogController
{
	public function create(): void
	{
		$data = Request::body();

		Validator::required($data, ['message']);
		Validator::string($data, 'message');

		$principal = Jwt::principal();

		$message = mb_substr($data['message'], 0, 2000);

		$log = [
			'principal_id' => $principal['id'] ?? null,
			'type'         => 'MobileError',
			'message'      => $message,
			'severity'     => 0,
			'file'         => mb_substr((string)($data['context'] ?? ''), 0, 200),
			'line'         => 0,
			'app_version'  => mb_substr((string)($data['app_version'] ?? ''), 0, 50),
			'stack'        => mb_substr((string)($data['stack'] ?? ''), 0, 8000),
		];

		Storage::create(LogController::COLLECTION, $log);

		$this->notify($log, $principal['username'] ?? 'unknown');

		Response::success([], 201);
	}

	private function notify(array $log, string $username): void
	{
		$to = Config::get('error_mail_to', '');

		if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
			return;
		}

		$subject = 'RestForge mobile error: ' .
			str_replace(["\r", "\n"], ' ', mb_substr($log['message'], 0, 80));

		$body = "User: {$username}\n" .
			"App: {$log['app_version']}\n" .
			"Context: {$log['file']}\n\n" .
			"{$log['message']}\n\n{$log['stack']}";

		@mail($to, $subject, $body, 'Content-Type: text/plain; charset=UTF-8');
	}
}