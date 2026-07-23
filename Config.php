<?php

return [

	'DEBUG' => true,

	'app_name' => 'SecureJsonApi',
	
	'allow_origin' => '*',
	
	'jwt_secret' => 'CHANGE_ME',
	
	'app_secret' => 'CHANGE_ME',
	
	'encryption_key' => hex2bin('75150a7ac135b7cf922c51a16c6d7f150ec048c8f4ae45e7558543b5a31a2e69'),
	
	'token_lifetime' => 3600,
	
	'default_owner_username' => 'admin',
	
	'default_owner_password' => 'secret123',
	
	'default_password' => 'P@ssword',
	
	'storage_path' => __DIR__ . '/storage',
	
	'timezone' => 'UTC',
	
	'storage_quota_bytes' => 1000000000,
	
	'refresh_expiration' => '+30 days',
	
	'refresh_absolute_expiration' => '+90 days'
];
