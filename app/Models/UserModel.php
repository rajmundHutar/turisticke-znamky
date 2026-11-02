<?php

namespace App\Models;

use Nette\Database\Explorer;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;

class UserModel implements Authenticator {

	/** @var Explorer */
	protected $db;

	/** @var Passwords */
	protected $passwords;

	public function __construct(
		Explorer $db,
		Passwords $passwords
	) {
		$this->db = $db;
		$this->passwords = $passwords;
	}

	public function authenticate(string $email, string $password): IIdentity {

		$row = $this->fetchByEmail($email);

		if (!$row) {
			throw new AuthenticationException('User not found.');
		}

		if (!$this->passwords->verify($password, $row['password'])) {
			throw new AuthenticationException('Invalid password.');
		}

		return new SimpleIdentity(
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
