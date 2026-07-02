<?php

declare(strict_types=1);

namespace App\Security;

final class Roles
{
	public const OWNER = 'owner';
	public const ADMIN = 'admin';
	public const USER = 'user';
	public const LOGREADER = 'logreader';
	public const LOGDELETER = 'logdeleter';
	
	public const VALID = [
		self::OWNER,
		self::ADMIN,
		self::USER,
		self::LOGREADER,
		self::LOGDELETER
	];
}
