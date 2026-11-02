<?php

/**
 * Test: UserModel is a Nette Authenticator (#36)
 */

declare(strict_types=1);

use App\Models\UserModel;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/** UserModel without a database, knowing a single user. */
final class FakeUserModel extends UserModel
{
	/** @param  array<string, mixed>  $row */
	public function __construct(
		Passwords $passwords,
		private array $row,
	) {
		$this->passwords = $passwords;
	}


	public function fetchByEmail(string $email)
	{
		return $email === $this->row['email'] ? $this->row : null;
	}
}


$passwords = new Passwords;
$model = new FakeUserModel($passwords, [
	'id' => 3,
	'email' => 'jana@example.com',
	'password' => $passwords->hash('secret'),
	'role' => 'admin',
]);

Assert::type(Authenticator::class, $model);


// Correct credentials give an identity with the role and the email
$identity = $model->authenticate('jana@example.com', 'secret');
Assert::type(SimpleIdentity::class, $identity);
Assert::same(3, $identity->getId());
Assert::same(['admin'], $identity->getRoles());
Assert::same('jana@example.com', $identity->getData()['email']);


// Wrong password and unknown user are rejected
Assert::exception(
	fn() => $model->authenticate('jana@example.com', 'wrong'),
	AuthenticationException::class,
);
Assert::exception(
	fn() => $model->authenticate('petr@example.com', 'secret'),
	AuthenticationException::class,
);
