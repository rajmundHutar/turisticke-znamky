<?php

declare(strict_types=1);

namespace Tests\Presenters;

use App\Helpers\StampThumbnails;
use App\Router\RouterFactory;
use Latte;
use Nette;
use Nette\Application\UI\Control;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\Bridges\ApplicationLatte\UIExtension;
use Nette\Bridges\FormsLatte\FormsExtension;
use Nette\Http\UrlScript;
use Nette\Http\Session;
use Nette\Localization\Translator;
use Nette\Security\Authenticator;
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
	public static function createUser(?array $roles, ?Authenticator $authenticator = null): User
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

		return new User($storage, $authenticator);
	}


	/**
	 * @param  array<string, string>  $headers
	 * @param  ?Session  $session  pass the same session to share stored requests between presenters
	 */
	public static function injectPrimary(Presenter $presenter, User $user, array $headers = [], ?Session $session = null, ?string $remoteAddress = null): void
	{
		$httpRequest = new Nette\Http\Request(new UrlScript('http://localhost/'), headers: $headers, remoteAddress: $remoteAddress);
		$httpResponse = new Nette\Http\Response;
		$presenterFactory = new Nette\Application\PresenterFactory;
		$presenterFactory->setMapping(['*' => 'App\*Module\Presenters\*Presenter']);
		$latteFactory = new class implements LatteFactory {
			public function create(?Control $control = null): Latte\Engine
			{
				$latte = new Latte\Engine;
				$latte->addExtension(new UIExtension($control)); // layout lookup, as in the app's LatteExtension
				$latte->addExtension(new FormsExtension); // {form} tags, as in the app's FormsExtension
				$latte->addExtension(new StampThumbnails(sys_get_temp_dir() . '/no-stamp-images', supported: true)); // |stampThumb, no thumbnail exists yet
				return $latte;
			}
		};

		$presenter->injectPrimary(
			$httpRequest,
			$httpResponse,
			$presenterFactory,
			RouterFactory::createRouter(),
			$session ?? new Session($httpRequest, $httpResponse),
			$user,
			new TemplateFactory($latteFactory, $httpRequest, $user),
		);
		$presenter->autoCanonicalize = false;
	}
}
