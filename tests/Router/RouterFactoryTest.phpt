<?php

/**
 * Test: the stamp detail route accepts only numeric ids (#7)
 */

declare(strict_types=1);

use App\Router\RouterFactory;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$router = RouterFactory::createRouter();
$match = fn(string $path) => $router->match(new Request(new UrlScript('http://localhost' . $path, '/')));

$params = $match('/stamp/12');
Assert::same(['Stamp', 'default', '12'], [$params['presenter'] ?? null, $params['action'] ?? null, $params['id'] ?? null]);

$params = $match('/stamp/abc');
Assert::notSame(['Stamp', 'default'], [$params['presenter'] ?? null, $params['action'] ?? null]);
