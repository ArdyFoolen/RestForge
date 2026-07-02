<?php

declare(strict_types=1);

use App\Core\Bootstrap;
use App\Core\Config;
use App\Core\Router;

require_once __DIR__ . '/../src/Autoload.php';

Bootstrap::initialize();

header('Access-Control-Allow-Origin: ' . Config::get('allow_origin'));
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	
	http_response_code(204);
	
	exit;
}

Router::dispatch();
