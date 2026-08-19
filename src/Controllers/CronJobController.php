<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\ItemController;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Storage\Storage;
use App\Core\ListOptions;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class CronJobController
{
	const LOCK_FILE = __DIR__ . '/../../Storage/airbnb-sync.lock';
	const SOURCE_AIRBNB = 'AirBnb';
	const SOURCE_BLOCKED = 'Blocked';
	const SOURCE_DIRECT = 'Direct';

	public function airbnb_sync(): void
	{
		$lockHandle = $this->aquireLock();
		
		try {

			$ics = $this->download_airbnb_calendar();

			$events = $this->parse_icalendar($ics);

			$tenants = $this->get_current_tenants();

			$this->validate_airbnb_events($events, $tenants);

			$result = $this->synchronize_airbnb_tenants(
				$tenants,
				$events
			);

			Response::success($result);

		} finally {
			flock($lockHandle, LOCK_UN);
			fclose($lockHandle);
		}
	}

	public function get_calendar(): void
	{
		$providedToken = $_GET['token'] ?? '';
		$calendarToken = Config::get('calendar_export_token', '');
		
		if ($providedToken === '' ||
			$calendarToken === '' ||
			!hash_equals(
				$calendarToken,
				$providedToken
			)) {
			Response::error(
				'Token not valid',
				401
			);
		}

		$tenants = $this->get_current_tenants();
		
		Response::ics($this->generate_icalendar($tenants));
	}

	private function get_current_tenants(): array
	{
		$filters = [
				'typeid' => 'Tenant',
				'till' => [
				'gt' => gmdate('Y-m-d')
				]
		];

		return Storage::list(
			ItemController::COLLECTION,
			$filters,
			'till',
			false
		);
	}

	private function aquireLock()
	{
		/*
		 * Prevent concurrent syncs.
		 */
		$lockHandle = fopen(self::LOCK_FILE, 'c');

		if ($lockHandle === false) {
			throw new RuntimeException('Unable to create sync lock file.');
		}

		if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
			fclose($lockHandle);
			
			Response::success([
				'job' => 'airbnb-sync',
				'status' => 'already_running',
			]);
		}
		
		return $lockHandle;
	}

	function download_airbnb_calendar(): string
	{
		$url = Config::get('airbnb_ical_url');

		if (!str_starts_with(strtolower($url), 'https://')) {
			throw new RuntimeException(
				'Airbnb calendar URL must use HTTPS.'
			);
		}

		$ch = curl_init($url);

		if ($ch === false) {
			throw new RuntimeException(
				'Unable to initialize cURL.'
			);
		}

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 3,

			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 30,

			CURLOPT_HTTPHEADER => [
				'Accept: text/calendar,text/plain;q=0.9,*/*;q=0.8',
				'User-Agent: AsprovaltaSeasideHomeCalendarSync/1.0'
			],
		]);

		$body = curl_exec($ch);

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);

		curl_close($ch);

		if ($body === false) {
			throw new RuntimeException(
				'Unable to download Airbnb calendar: ' . $error
			);
		}

		if ($httpCode < 200 || $httpCode >= 300) {
			throw new RuntimeException(
				"Airbnb calendar returned HTTP {$httpCode}."
			);
		}

		if (trim($body) === '') {
			throw new RuntimeException(
				'Airbnb calendar returned an empty response.'
			);
		}

		/*
		 * Basic sanity check.
		 *
		 * This is important because we don't want an HTML error page
		 * accidentally interpreted as an empty calendar.
		 */
		if (
			stripos($body, 'BEGIN:VCALENDAR') === false ||
			stripos($body, 'END:VCALENDAR') === false
		) {
			throw new RuntimeException(
				'Downloaded content does not appear to be a valid iCalendar file.'
			);
		}

		return $body;
	}

	function parse_icalendar(string $ics): array
	{
		/*
		 * iCalendar allows long lines to be folded.
		 * A continuation line starts with a space or tab.
		 */
		$ics = str_replace(["\r\n", "\r"], "\n", $ics);

		$lines = explode("\n", $ics);

		$unfolded = [];

		foreach ($lines as $line) {
			if (
				($line !== '') &&
				(
					str_starts_with($line, ' ') ||
					str_starts_with($line, "\t")
				)
			) {
				if (!empty($unfolded)) {
					$unfolded[count($unfolded) - 1] .= substr($line, 1);
				}

				continue;
			}

			$unfolded[] = $line;
		}

		$events = [];

		$current = null;

		foreach ($unfolded as $line) {

			$line = trim($line);

			if ($line === 'BEGIN:VEVENT') {
				$current = [];
				continue;
			}

			if ($line === 'END:VEVENT') {

				if (
					$current !== null &&
					isset($current['UID']) &&
					isset($current['DTSTART']) &&
					isset($current['DTEND'])
				) {
					$event = $this->convert_ical_event($current);

					if ($event !== null) {
						$events[] = $event;
					}
				}

				$current = null;
				continue;
			}

			if ($current === null) {
				continue;
			}

			/*
			 * Property format:
			 *
			 * DTSTART;VALUE=DATE:20260820
			 *
			 * or:
			 *
			 * UID:abc123
			 */
			$parts = explode(':', $line, 2);

			if (count($parts) !== 2) {
				continue;
			}

			[$property, $value] = $parts;

			/*
			 * Property name is before ; parameters.
			 */
			$propertyParts = explode(';', $property);

			$name = strtoupper($propertyParts[0]);

			$parameters = [];

			foreach (array_slice($propertyParts, 1) as $parameter) {

				$parameterParts = explode('=', $parameter, 2);

				if (count($parameterParts) === 2) {
					$parameters[
						strtoupper($parameterParts[0])
					] = trim($parameterParts[1], '"');
				}
			}

			$current[$name] = [
				'value' => $value,
				'parameters' => $parameters,
			];
		}

		return $events;
	}

	function convert_ical_event(array $event): ?array
	{
		$uid = trim((string) $event['UID']['value']);

		if ($uid === '') {
			return null;
		}

		$start = $this->parse_ical_date($event['DTSTART']);

		$end = $this->parse_ical_date($event['DTEND']);

		if ($start === null || $end === null) {
			return null;
		}

		if ($end <= $start) {
			return null;
		}

		$summary = '';

		if (isset($event['SUMMARY'])) {
			$summary = trim(
				$this->ical_unescape(
					(string) $event['SUMMARY']['value']
				)
			);
		}

		return [
			'external_id' => $uid,
			'source' => $this->get_airbnbSource($summary),

			'from' => $start,
			'till' => $end,

			'summary' => $summary,
		];
	}

	function parse_ical_date(array $property): ?string
	{
		$value = trim((string) $property['value']);

		$isDateOnly =
			(($property['parameters']['VALUE'] ?? '') === 'DATE') ||
			preg_match('/^\d{8}$/', $value);

		try {

			if ($isDateOnly) {

				$date = DateTimeImmutable::createFromFormat(
					'!Ymd',
					$value,
					new DateTimeZone('UTC')
				);

				if ($date === false) {
					return null;
				}

				return $date->format('Y-m-d');
			}

			/*
			 * UTC timestamp:
			 *
			 * 20260820T140000Z
			 */
			if (str_ends_with($value, 'Z')) {

				$date = DateTimeImmutable::createFromFormat(
					'!Ymd\THis\Z',
					$value,
					new DateTimeZone('UTC')
				);

				if ($date === false) {
					return null;
				}

				return $date->format('Y-m-d');
			}

			/*
			 * Local iCalendar timestamp.
			 *
			 * For Airbnb accommodation calendars, we primarily expect
			 * all-day dates, but handling this makes the parser safer.
			 */
			$date = DateTimeImmutable::createFromFormat(
				'!Ymd\THis',
				$value,
				new DateTimeZone('UTC')
			);

			if ($date === false) {
				return null;
			}

			return $date->format('Y-m-d');

		} catch (Throwable) {
			return null;
		}
	}

	private function validate_airbnb_events(
		array $events,
		array $tenants
	): void
	{

		foreach ($events as $event) {
			$this->validate_airbnb_event($event, $tenants);
		}

	}

	private function validate_airbnb_event(
		array $event,
		array $tenants
	): void
	{

		foreach ($tenants as $tenant) {

			if (
				!isset($tenant['from']) ||
				!isset($tenant['till'])
			) {
				continue;
			}

			if (
				(
					($tenant['source'] ?? null) === self::SOURCE_AIRBNB ||
					($tenant['source'] ?? null) === self::SOURCE_BLOCKED
				) &&
				isset($tenant['external_id']) &&
				isset($event['external_id']) &&
				(string) $tenant['external_id'] === (string) $event['external_id']
			) {
				continue;
			}

			/*
			 * Airbnb events may overlap each other only if Airbnb
			 * itself sends them that way. We don't reject those here.
			 */
			if (
				($tenant['source'] ?? null) === self::SOURCE_AIRBNB ||
				($tenant['source'] ?? null) === self::SOURCE_BLOCKED
			) {
				continue;
			}

			/*
			 * Only locally managed bookings are conflicts.
			 * Direct = a real booking made through RestForge.
			 */
			if (
				($tenant['source'] ?? null) !== self::SOURCE_DIRECT
			) {
				continue;
			}

			/*
			 * Check date-range overlap.
			 */
			if (
				$tenant['from'] < $event['till'] &&
				$tenant['till'] > $event['from']
			) {
				throw new RuntimeException(
					sprintf(
						'Airbnb reservation %s (%s to %s) overlaps ' .
						'Direct booking %s (%s to %s).',
						$event['external_id'],
						$event['from'],
						$event['till'],
						$tenant['id'] ?? '?',
						$tenant['from'],
						$tenant['till']
					)
				);
			}
		}
	}

	function synchronize_airbnb_tenants(
		array $tenants,
		array $airbnbEvents
	): array {

		$added = 0;
		$updated = 0;
		$removed = 0;

		/*
		 * Index Airbnb events by Airbnb UID.
		 */
		$airbnbByUid = [];

		foreach ($airbnbEvents as $event) {
			$airbnbByUid[$event['external_id']] = $event;
		}

		/*
		 * Index existing Airbnb tenants.
		 */
		$existingAirbnb = [];

		foreach ($tenants as $index => $tenant) {

			if (
				($tenant['source'] ?? null) !== self::SOURCE_AIRBNB && ($tenant['source'] ?? null) !== self::SOURCE_BLOCKED
			) {
				continue;
			}

			$externalId =
				$tenant['external_id']
				?? null;

			if ($externalId === null) {
				continue;
			}

			$existingAirbnb[$externalId] = $index;
		}

		/*
		 * Add or update events received from Airbnb.
		 */
		foreach ($airbnbByUid as $uid => $event) {

			if (isset($existingAirbnb[$uid])) {

				$index = $existingAirbnb[$uid];

				$existing = $tenants[$index];

				$changed = false;

				foreach ([
					'from',
					'till'
				] as $field) {

					$newValue = $event[$field] ?? null;
					$oldValue = $existing[$field] ?? null;

					if ($newValue !== $oldValue) {
						$tenants[$index][$field] = $newValue;
						$changed = true;
					}
				}

				if ($changed) {
					$updated++;
					
					Storage::update(ItemController::COLLECTION,
						$tenants[$index]['id'],
						$tenants[$index]
					);
				}

			} else {

				/*
				 * New Airbnb tenant.
				 */
				$tenant = [
					'typeid' => 'Tenant',
					'tenantname' => 'AirBnb',
					
					'external_id' => $event['external_id'],

					'source' => $this->get_airbnbSource($event['summary']),

					'from' => $event['from'],
					'till' => $event['till'],
				];

				$tenants[] = $tenant;

				$added++;

				Storage::create(ItemController::COLLECTION, $tenant);
			}
		}

		/*
		 * Remove Airbnb tenants which no longer exist
		 * in the current Airbnb feed.
		 *
		 * IMPORTANT:
		 *
		 * Direct tenants are never touched here.
		 */
		foreach ($tenants as $index => $tenant) {

			if (
				($tenant['source'] ?? null) !== self::SOURCE_AIRBNB && ($tenant['source'] ?? null) !== self::SOURCE_BLOCKED
			) {
				continue;
			}

			$uid =
				$tenant['external_id']
				?? null;

			if ($uid === null) {
				continue;
			}

			if (!isset($airbnbByUid[$uid])) {

				$removed++;
				
				Storage::delete(ItemController::COLLECTION, $tenant['id']);
			}
		}

		return [
			'changed' => ($added + $updated + $removed) > 0,

			'events_found' => count($airbnbEvents),

			'events_added' => $added,
			'events_updated' => $updated,
			'events_removed' => $removed,
		];
	}

	private function generate_icalendar(array $tenants): string
	{
		$lines = [];

		/*
		 * Calendar-level properties.
		 */
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:' . Config::get('calendar_prodid', '');
		$lines[] = 'CALSCALE:GREGORIAN';
		$lines[] = 'METHOD:PUBLISH';

		/*
		 * Optional but useful.
		 */
		$lines[] =
			'X-WR-CALNAME:' .
			$this->ical_escape(Config::get('calendar_name', ''));

		/*
		 * Add tenants.
		 */
		foreach ($tenants as $tenant) {

			$event = $this->tenant_to_ical_event(
				$tenant
			);

			foreach ($event as $line) {
				$lines[] = $line;
			}
		}

		$lines[] = 'END:VCALENDAR';

		/*
		 * RFC 5545 requires CRLF line endings.
		 */
		return implode("\r\n", $lines) . "\r\n";
	}

	/*
	|--------------------------------------------------------------------------
	| Convert tenant to VEVENT
	|--------------------------------------------------------------------------
	*/
	private function tenant_to_ical_event(
		array $tenant
	): array {

		$id = $tenant['id'];
		$source = $tenant['source'];

		$uid = $source . '-' . $id . '@asprovaltaseasidehome';

		$dtstamp = gmdate('Ymd\THis\Z');
		$dtstamp = gmdate(
			'Ymd\THis\Z',
			strtotime($tenant['updated_at'])
		);

		/*
		 * Validate dates.
		 */
		$checkIn =
			DateTimeImmutable::createFromFormat(
				'!Y-m-d',
				(string) $tenant['from'],
				new DateTimeZone('UTC')
			);

		$checkOut =
			DateTimeImmutable::createFromFormat(
				'!Y-m-d',
				(string) $tenant['till'],
				new DateTimeZone('UTC')
			);

		if (
			$checkIn === false ||
			$checkOut === false
		) {
			throw new RuntimeException(
				'Invalid tenant dates for tenant ' .
				$id
			);
		}

		/*
		 * Airbnb/calendar systems expect DTEND to be the
		 * checkout date, not the last occupied night.
		 *
		 * Example:
		 *
		 * check-in  = Aug 20
		 * check-out = Aug 25
		 *
		 * DTSTART = Aug 20
		 * DTEND   = Aug 25
		 */
		$start = $checkIn->format('Ymd');
		$end = $checkOut->format('Ymd');

		/*
		 * Don't expose guest names or personal information.
		 * Airbnb only needs to know that the period is occupied.
		 */
		$summary = 'Reserved';

		return [
			'BEGIN:VEVENT',

			'UID:' . $this->ical_escape($uid),

			'DTSTAMP:' . $dtstamp,

			'DTSTART;VALUE=DATE:' . $start,

			'DTEND;VALUE=DATE:' . $end,

			'SUMMARY:' . $this->ical_escape($summary),

			/*
			 * OPAQUE means this event occupies/busy-blocks the
			 * calendar.
			 */
			'TRANSP:OPAQUE',

			'END:VEVENT',
		];
	}
	
	private function get_airbnbSource(string $summary): string
	{
		$airbnbSource = '';
		
		switch (strtoupper($summary)) {
			case 'RESERVED':
				$airbnbSource = self::SOURCE_AIRBNB;
				break;
			case 'AIRBNB (NOT AVAILABLE)':
				$airbnbSource = self::SOURCE_BLOCKED;
				break;
		}
		
		return $airbnbSource;
	}
	
	/*
	|--------------------------------------------------------------------------
	| iCalendar escaping
	|--------------------------------------------------------------------------
	|
	| RFC 5545 text values require escaping of:
	|
	|   backslash
	|   semicolon
	|   comma
	|   newline
	|
	*/
	private function ical_escape(string $value): string
	{
		$value = str_replace(
			'\\',
			'\\\\',
			$value
		);

		$value = str_replace(
			';',
			'\\;',
			$value
		);

		$value = str_replace(
			',',
			'\\,',
			$value
		);

		$value = str_replace(
			["\r\n", "\r", "\n"],
			'\\n',
			$value
		);

		return $value;
	}

	function ical_unescape(string $value): string
	{
		return str_replace(
			[
				'\\n',
				'\\N',
				'\\,',
				'\\;',
				'\\\\',
			],
			[
				"\n",
				"\n",
				',',
				';',
				'\\',
			],
			$value
		);
	}

}

