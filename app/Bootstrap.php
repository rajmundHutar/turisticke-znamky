<?php

declare(strict_types=1);

namespace App;

use App\Enums\EnvironmentEnum;
use Nette\Bootstrap\Configurator;


class Bootstrap
{
	public static function boot(): Configurator
	{
		$configurator = new Configurator;

		$env = EnvironmentEnum::tryFrom((string)getenv('ENVIRONMENT'));
		$configurator->setDebugMode($env === EnvironmentEnum::Dev);
		$configurator->enableTracy(__DIR__ . '/../log');

		$configurator->setTimeZone('Europe/Prague');
		$configurator->setTempDirectory(__DIR__ . '/../temp');

		$configurator->createRobotLoader()
			->addDirectory(__DIR__)
			->register();

		$configurator->addConfig(__DIR__ . '/config/common.neon');
		$configurator->addConfig(__DIR__ . '/config/local.neon');

		return $configurator;
	}
}
