<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\RouteList;

final class RouterFactory
{
	use Nette\StaticClass;

	public static function createRouter(): RouteList
	{
		$router = new RouteList;
		$router->addRoute('stamp/<id \d+>', 'Stamp:default');
		$router->addRoute('photo/<stampId \d+>/<v [a-f0-9]{32}>', 'Photo:default'); // the photo's name as the version, no extension (#19)
		$router->addRoute('thumb/<name [a-z0-9-]+>', 'Thumbnail:default'); // no extension: the built-in dev server would not route it
		$router->addRoute('<presenter>/<action>[/<id>]', 'Homepage:default');
		return $router;
	}
}
