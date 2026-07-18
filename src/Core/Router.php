<?php

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\ItemController;
use App\Controllers\UserController;
use App\Controllers\SessionController;
use App\Controllers\DashboardController;
use App\Controllers\LogController;
use App\Security\AuthMiddleware;
use App\Security\Permissions;
use App\Core\Response;
use App\Core\Version;

final class Router
{
	private array $routes = [];
	private string $prefix = '';
	
	public function __construct()
	{
		$this->registerRoutes();
	}
	
	public static function dispatch(): void
	{
		$router = new self();
		
		$router->dispatchRequest();
	}
	
	public function get(
		string $path,
		callable $handler,
		array $middleware = [],
		?array $permissions = null
	): void
	{
		$this->addRoute('GET', $path, $handler, $middleware, $permissions);
	}
	
	public function post(
		string $path,
		callable $handler,
		array $middleware = [],
		?array $permissions = null
	): void

	{
		$this->addRoute('POST', $path, $handler, $middleware, $permissions);
	}
	
	public function put(
		string $path,
		callable $handler,
		array $middleware = [],
		?array $permissions = null
	): void
	{
		$this->addRoute('PUT', $path, $handler, $middleware, $permissions);
	}
	
	public function delete(
		string $path,
		callable $handler,
		array $middleware = [],
		?array $permissions = null
	): void
	{
		$this->addRoute('DELETE', $path, $handler, $middleware, $permissions);
	}
	
	private function group(string $prefix, callable $callback): void
	{
		$previous = $this->prefix;
		
		$this->prefix .= rtrim($prefix, '/');

		$callback($this);
		
		$this->prefix = $previous;
	}
	
	private function addRoute(
		string $method,
		string $path,
		callable $handler,
		array $middleware = [],
		?array $permissions = null
	): void
	{
		$this->routes[] = [
			'method' => strtoupper($method),
			'path' => $this->prefix . $path,
			'handler' => $handler,
			'middleware' => $middleware,
			'permissions' => $permissions
		];
	}
	
	private function registerRoutesVersion1(Router $router): void
	{
		$authController = new AuthController();
		$itemController = new ItemController();
		$userController = new UserController();
		$sessionController = new SessionController();
		$dashboardController = new DashboardController();
		$logController = new LogController();

		$router->post('/login', [$authController, 'login']);
		$router->post('/logout', [$authController, 'logout'], [AuthMiddleware::class], [Permissions::AUTHENTICATED]);
		$router->post('/refresh', [$authController, 'refresh']);

		$router->post('/item', [$itemController, 'create'], [AuthMiddleware::class], [Permissions::ITEM_CREATE]);
		$router->get('/items', [$itemController, 'list'], [AuthMiddleware::class], [Permissions::ITEM_READ]);
		$router->get('/item/{id}', [$itemController, 'read'], [AuthMiddleware::class], [Permissions::ITEM_READ]);
		$router->put('/item/{id}', [$itemController, 'update'], [AuthMiddleware::class], [Permissions::ITEM_UPDATE]);
		$router->delete('/item/{id}', [$itemController, 'delete'], [AuthMiddleware::class], [Permissions::ITEM_DELETE]);

		$router->post('/user', [$userController, 'create'], [AuthMiddleware::class], [Permissions::USER_CREATE]);
		$router->get('/users', [$userController, 'list'], [AuthMiddleware::class], [Permissions::USER_READ]);
		$router->get('/user/{id}', [$userController, 'read'], [AuthMiddleware::class], [Permissions::USER_READ]);
		$router->get('/whoami', [$userController, 'whoAmI'], [AuthMiddleware::class], [Permissions::AUTHENTICATED]);
		$router->put('/user/{id}', [$userController, 'update'], [AuthMiddleware::class], [Permissions::USER_UPDATE]);
		$router->put('/user/password/{id}', [$userController, 'changePassword'], [AuthMiddleware::class], [Permissions::USER_PASSWORD_CHANGE]);
		$router->put('/user/resetpassword/{id}', [$userController, 'resetPassword'], [AuthMiddleware::class], [Permissions::USER_RESET_PASSWORD]);
		$router->delete('/user/{id}', [$userController, 'delete'], [AuthMiddleware::class], [Permissions::USER_DELETE]);

		$router->get('/sessions', [$sessionController, 'list'], [AuthMiddleware::class], [Permissions::SESSION_READ]);
		$router->get('/session/{id}', [$sessionController, 'read'], [AuthMiddleware::class], [Permissions::SESSION_READ]);
		$router->put('/session/{id}', [$sessionController, 'update'], [AuthMiddleware::class], [Permissions::SESSION_UPDATE]);
		$router->delete('/session/{id}', [$sessionController, 'delete'], [AuthMiddleware::class], [Permissions::SESSION_DELETE]);
		$router->delete('/sessions', [$sessionController, 'deleteArray'], [AuthMiddleware::class], [Permissions::SESSION_DELETE]);

		$router->get('/dashboard', [$dashboardController, 'read'], [AuthMiddleware::class], [Permissions::AUTHENTICATED]);

		$router->get('/logs', [$logController, 'list'], [AuthMiddleware::class], [Permissions::SESSION_READ]);
		$router->get('/log/{id}', [$logController, 'read'], [AuthMiddleware::class], [Permissions::SESSION_READ]);
		$router->delete('/log/{id}', [$logController, 'delete'], [AuthMiddleware::class], [Permissions::SESSION_DELETE]);
	}
	
	private function registerRoutes(): void
	{
		$this->group('/api/v1', function (Router $router) {
			
			$router->registerRoutesVersion1($router);
		});

		$this->get('/api/v1/version', function() {
			Response::success([
				'name' => Version::NAME,
				'version' => Version::VERSION,
				'api' => Version::API
			]);
		});

		$this->get('', function() {
			Response::file(
				__DIR__ . '/../Resources/index.html',
				'text/html'
			);
		});

	}
	
	private function dispatchRequest(): void
	{
		$method = Request::method();
		$path = trim(Request::path(), '/');

		$requestParts = $path === ''
			? []
			: explode('/', $path);
		
		foreach($this->routes as $route) {
			
			if ($route['method'] !== $method) {
				continue;
			}

			$routePath = trim($route['path'], '/');
			$routeParts = $routePath === ''
				? []
				: explode('/', $routePath);
		
			if (count($routeParts) !== count($requestParts)) {
				continue;
			}

			$params = [];
			$matched = true;
			
			foreach ($routeParts as $index => $part) {

				if (
					str_starts_with($part, '{') &&
					str_ends_with($part, '}')
				) {
					$name = trim($part, '{}');
					$params[$name] = $requestParts[$index];
					continue;
				}
					
				if ($part !== $requestParts[$index]) {
					$matched = false;
					break;
				}
				
			}
			
			if (!$matched) {
				continue;
			}
			
			foreach ($route['middleware'] as $middleware) {

				$middleware::handle($route);
			}
			
			$route['handler'](...array_values($params));
			return;
		}
		
		Response::error('Route not found', 404);
	}
}
