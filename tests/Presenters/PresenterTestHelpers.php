<?php

declare(strict_types=1);

namespace Tests\Presenters;

use App\Router\RouterFactory;
use Latte;
use Nette;
use Nette\Application\UI\Control;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\Http\UrlScript;
use Nette\Localization\Translator;
use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Nette\Security\UserStorage;


/**
 * Helpers for running presenters in tests without a database or a DI container.
 */
final class PresenterTestHelpers
{
	public static function createTranslator(): Translator
	{
		return new class implements Translator {
			public function translate(string|\Stringable $message, mixed ...$parameters): string
			{
				return (string) $message;
			}
		};
	}


	/**
	 * @param  list<string>|null  $roles  null = anonymous user
	 */
	public static function createUser(?array $roles): User
	{
		$identity = $roles === null ? null : new SimpleIdentity(1, $roles);
		$storage = new class ($identity) implements UserStorage {
			public function __construct(
				private ?IIdentity $identity,
			) {
			}


			public function saveAuthentication(IIdentity $identity): void
			{
				$this->identity = $identity;
			}


			public function clearAuthentication(bool $clearIdentity): void
			{
				$this->identity = null;
			}


			public function getState(): array
			{
				return [$this->identity !== null, $this->identity, null];
			}


			public function setExpiration(?string $expire, bool $clearIdentity): void
			{
			}
		};

		return new User($storage);
	}


	/**
	 * @param  array<string, string>  $headers
	 */
	public static function injectPrimary(Presenter $presenter, User $user, array $headers = []): void
	{
		$httpRequest = new Nette\Http\Request(new UrlScript('http://localhost/'), headers: $headers);
		$httpResponse = new Nette\Http\Response;
		$presenterFactory = new Nette\Application\PresenterFactory;
		$presenterFactory->setMapping(['*' => 'App\*Module\Presenters\*Presenter']);
		$latteFactory = new class implements LatteFactory {
			public function create(?Control $control = null): Latte\Engine
			{
				return new Latte\Engine;
			}
		};

		$presenter->injectPrimary(
			$httpRequest,
			$httpResponse,
			$presenterFactory,
			RouterFactory::createRouter(),
			new Nette\Http\Session($httpRequest, $httpResponse),
			$user,
			new TemplateFactory($latteFactory),
		);
		$presenter->autoCanonicalize = false;
	}
}
