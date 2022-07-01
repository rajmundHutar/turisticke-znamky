<?php

namespace App\Models;

use Nette\Database\Context;
use Nette\Security\AuthenticationException;
use Nette\Security\IAuthenticator;
use Nette\Security\Identity;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;

class UserModel implements IAuthenticator {

	/** @var Context */
	protected $db;

	/** @var Passwords */
	protected $passwords;

	public function __construct(
		Context $db,
		Passwords $passwords
	) {
		$this->db = $db;
		$this->passwords = $passwords;
	}

	public function authenticate(array $credentials): IIdentity {

		[$email, $password] = $credentials;

		$row = $this->fetchByEmail($email);

		if (!$row) {
			throw new AuthenticationException('User not found.');
		}

		if (!$this->passwords->verify($password, $row['password'])) {
			throw new AuthenticationException('Invalid password.');
		}

		return new Identity(
			$row['id'],
			$row['role'],
			[
				'email' => $row['email'],
			]
		);

	}

	public function fetchByEmail(string $email) {

		return $this->db
			->table(\Table::Users)
			->where('email', $email)
			->fetch();

	}

}
