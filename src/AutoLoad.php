<?php

spl_autoload_register(function ($class) {

	$prefix = 'App\\';
	
	if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
		return;
	}
	
	$relative = substr($class, strlen($prefix));
	
	$file = __DIR__ . DIRECTORY_SEPARATOR .
			str_replace('\\', DIRECTORY_SEPARATOR, $relative) .
			'.php';
	
	if (file_exists($file)) {
		require_once $file;
	}
});
