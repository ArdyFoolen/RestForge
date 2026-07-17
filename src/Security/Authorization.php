<?php

declare(strict_types=1);

namespace App\Security;

use App\Security\Permissions;
use App\Security\Roles;

final class Authorization
{
	private const MAP = [
		Roles::OWNER => [
			Permissions::AUTHENTICATED,

			Permissions::USER_CREATE,
			Permissions::USER_READ,
			Permissions::USER_UPDATE,
			Permissions::USER_DELETE,
			Permissions::USER_PASSWORD_CHANGE,
			Permissions::USER_RESET_PASSWORD,
			
			permissions::SESSION_READ,
			permissions::SESSION_UPDATE,
			permissions::SESSION_DELETE,

			Permissions::ITEM_CREATE,
			Permissions::ITEM_READ,
			Permissions::ITEM_UPDATE,
			Permissions::ITEM_DELETE,
			
			permissions::LOG_READ,
			permissions::LOG_DELETE
		],

		Roles::ADMIN => [
			Permissions::AUTHENTICATED,

			Permissions::USER_CREATE,
			Permissions::USER_READ,
			Permissions::USER_UPDATE,
			Permissions::USER_DELETE,
			permissions::USER_PASSWORD_CHANGE,
			Permissions::USER_RESET_PASSWORD,
			
			permissions::SESSION_READ,
			permissions::SESSION_UPDATE,
			permissions::SESSION_DELETE,

			Permissions::ITEM_CREATE,
			Permissions::ITEM_READ,
			Permissions::ITEM_UPDATE,
			Permissions::ITEM_DELETE
		],

		Roles::USER => [
			Permissions::AUTHENTICATED,

			permissions::USER_PASSWORD_CHANGE,

			Permissions::ITEM_CREATE,
			Permissions::ITEM_READ,
			Permissions::ITEM_UPDATE,
			Permissions::ITEM_DELETE
		],
		
		Roles::LOGREADER => [
			permissions::LOG_READ
		],
		
		Roles::LOGDELETER => [
			permissions::LOG_READ,
			permissions::LOG_DELETE
		]
	];
	
	public static function allows(
		array $principal,
		array $permissions
	): bool
	{
		
		if (!$principal['enabled']) {
			return false;
		}
		
		foreach ($permissions as $permission) {

			if ($permission === Permissions::AUTHENTICATED) {
				continue;
			}
			
			$allowed = false;
			
			foreach ($principal['roles'] as $role) {

				if (
					in_array(
						$permission,
						self::MAP[$role] ?? [],
						true
					)
				) {
					$allowed = true;
					break;
				}
				
				if (!$allowed) {
					return false;
				}
			}
		}
		
		return true;
	}
}
