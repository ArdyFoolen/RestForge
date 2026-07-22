<?php

declare(strict_types=1);

namespace App\Security;

final class Restrictions
{
	public const USER_PASSWORD_CHANGE_REQUIRED = 'user.password.change.required';
	
	public static function restrictionMessage(string $restriction): ?string
	{
		switch ($restriction) {
			case Restrictions::USER_PASSWORD_CHANGE_REQUIRED:
				return 'Password change required.';
		}

		return null;
	}
}
