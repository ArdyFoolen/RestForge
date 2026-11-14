<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Config;
use App\Core\Response;
use App\Storage\Storage;

final class LoginThrottle
{
	public const COLLECTION = 'throttle';
	private const LIMITS = ['user' => 5, 'ip' => 20]; // failures before a lock
	private const WINDOW = 86400;                     // failures count for 24 h; a clean day resets them
	private const LOCK_BASE = 60;                     // first lock in seconds, doubles per extra failure
	private const LOCK_MAX = 3600;                    // longest lock: 1 hour
	private const PRUNE_AFTER = 172800;               // delete counters untouched for 2 days

	/** Counters for this request's username and IP, loaded once per request. */
	private static ?array $loaded = null;

	/*
	 * Call first in login(). Responds 429 if the username or the IP is locked.
	 */
	public static function assertAllowed(string $username): void
	{
		self::prune();

		$records = self::load($username);

		foreach (self::keys($username) as $key) {
			$record = $records[$key] ?? null;

			if ($record !== null && $record['locked_until'] > time()) {
				header('Retry-After: ' . ($record['locked_until'] - time()));
				Response::error('Too many attempts. Try again later.', 429);
			}
		}
	}

	/*
	 * Call after a failed login (wrong password, unknown user or disabled user).
	 */
	public static function recordFailure(string $username): void
	{
		$records = self::load($username);
		$now = time();

		foreach (self::keys($username) as $kind => $key) {
			$record = $records[$key] ?? null;

			if ($record === null || $now - (int) $record['first'] > self::WINDOW) {
				$state = ['count' => 0, 'first' => $now, 'locked_until' => 0];
			} else {
				$state = [
					'count'        => (int) $record['count'],
					'first'        => (int) $record['first'],
					'locked_until' => (int) $record['locked_until'],
				];
			}

			$state['count']++;

			if ($state['count'] >= self::LIMITS[$kind]) {
				$over = min($state['count'] - self::LIMITS[$kind], 6);
				$state['locked_until'] = $now + min(self::LOCK_MAX, self::LOCK_BASE * (2 ** $over));
			}

			if ($record === null) {
				Storage::create(self::COLLECTION, ['key' => $key] + $state);
			} else {
				Storage::update(self::COLLECTION, $record['id'], $state);
			}
		}
	}

	/*
	 * Call after a successful login. Clears the username counter only;
	 * the IP counter is left to expire on its own.
	 */
	public static function recordSuccess(string $username): void
	{
		$record = self::load($username)[self::keys($username)['user']] ?? null;

		if ($record !== null) {
			Storage::delete(self::COLLECTION, $record['id']);
		}
	}

	/*
	 * Opaque lookup keys, so the stored records reveal no usernames or IPs.
	 */
	private static function keys(string $username): array
	{
		$secret = (string) Config::get('app_secret');

		return [
			'user' => hash_hmac('sha256', 'user|' . strtolower($username), $secret),
			'ip'   => hash_hmac('sha256', 'ip|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), $secret),
		];
	}

	/*
	 * Storage generates random record ids, so counters are found by their 'key' field.
	 * One list() call per request, matched in PHP with strict comparison.
	 */
	private static function load(string $username): array
	{
		if (self::$loaded !== null) {
			return self::$loaded;
		}

		$wanted = array_values(self::keys($username));
		$found = [];

		foreach (Storage::list(self::COLLECTION) as $record) {
			if (in_array($record['key'] ?? null, $wanted, true)) {
				$found[$record['key']] = $record;
			}
		}

		return self::$loaded = $found;
	}

	/*
	 * Stops made-up usernames from filling the disk (runs on ~1% of requests).
	 */
	private static function prune(): void
	{
		if (random_int(1, 100) !== 1) {
			return;
		}

		$old = Storage::list(self::COLLECTION, [
			'updated_at' => ['lt' => gmdate('c', time() - self::PRUNE_AFTER)]
		]);

		foreach ($old as $record) {
			Storage::delete(self::COLLECTION, $record['id']);
		}
	}
}