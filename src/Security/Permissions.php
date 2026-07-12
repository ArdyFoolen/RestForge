<?php

declare(strict_types=1);

namespace App\Security;

final class Permissions
{
	public const AUTHENTICATED = 'authenticated';

	public const USER_CREATE = 'user.create';
	public const USER_READ = 'user.read';
	public const USER_UPDATE = 'user.update';
	public const USER_DELETE = 'user.delete';

	public const SESSION_READ = 'session.read';
	public const SESSION_UPDATE = 'session.update';
	public const SESSION_DELETE = 'session.delete';

	public const ITEM_CREATE = 'item.create';
	public const ITEM_READ = 'item.read';
	public const ITEM_UPDATE = 'item.update';
	public const ITEM_DELETE = 'item.delete';

	public const LOG_READ = 'log.read';
	public const LOG_DELETE = 'log.delete';
}
