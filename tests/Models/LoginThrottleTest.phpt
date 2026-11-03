<?php

/**
 * Test: LoginThrottle blocks an account or IP after too many failed logins in a time window (#38)
 */

declare(strict_types=1);

use App\Models\LoginThrottle;
use App\Models\LoginThrottledException;
use App\Models\UserModel;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Security\AuthenticationException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/** UserModel without a database; resolves e-mails like the DB collation would, from a fixed map. */
final class StubUserModel extends UserModel
{
	/** @param  array<string, int>  $ids  e-mail => user id */
	public function __construct(
		private array $ids = [],
	) {
	}


	public function fetchByEmail(string $email)
	{
		return isset($this->ids[$email]) ? ['id' => $this->ids[$email]] : null;
	}
}


$now = 1_000_000;
$clock = function () use (&$now): int {
	return $now;
};

$lockFile = (string) tempnam(sys_get_temp_dir(), 'login-throttle');

function createThrottle(array $ids = []): LoginThrottle
{
	global $lockFile, $clock;
	return new LoginThrottle($lockFile, new MemoryStorage, new StubUserModel($ids), $clock);
}


function failTimes(LoginThrottle $throttle, int $count, string $email, ?string $ip): void
{
	for ($i = 0; $i < $count; $i++) {
		Assert::exception(
			fn() => $throttle->attempt($email, $ip, fn() => throw new AuthenticationException('Invalid password.')),
			AuthenticationException::class,
		);
	}
}


function succeed(LoginThrottle $throttle, string $email, ?string $ip): void
{
	$throttle->attempt($email, $ip, fn() => null);
}


/** Probes the throttle without recording anything: a non-authentication exception passes through untouched. */
function isBlocked(LoginThrottle $throttle, string $email, ?string $ip): bool
{
	try {
		$throttle->attempt($email, $ip, fn() => throw new LogicException('Not blocked.'));
	} catch (LoginThrottledException) {
		return true;
	} catch (Throwable $e) {
		Assert::same('Not blocked.', $e->getMessage());
	}
	return false;
}


// Four failures are tolerated, the fifth blocks the same e-mail and the same IP
$throttle = createThrottle();
failTimes($throttle, 4, 'jana@example.com', '10.0.0.1');
Assert::false(isBlocked($throttle, 'jana@example.com', '10.0.0.1'));
failTimes($throttle, 1, 'jana@example.com', '10.0.0.1');
Assert::true(isBlocked($throttle, 'jana@example.com', '10.0.0.1'));


// A blocked attempt never calls the authentication, even with a correct password
$called = false;
Assert::exception(
	fn() => $throttle->attempt('jana@example.com', '10.0.0.1', function () use (&$called) {
		$called = true;
	}),
	LoginThrottledException::class,
);
Assert::false($called);


// A different e-mail from a different IP is not blocked
Assert::false(isBlocked($throttle, 'petr@example.com', '10.0.0.2'));


// The same unknown e-mail from another IP is blocked; it is normalized (case, whitespace, accents)
Assert::true(isBlocked($throttle, '  Jána@Example.COM ', '10.0.0.2'));


// The same IP with another e-mail is blocked
Assert::true(isBlocked($throttle, 'petr@example.com', '10.0.0.1'));


// Failures with differently written unknown e-mails count together
$throttle = createThrottle();
failTimes($throttle, 3, 'jana@example.com', '10.0.0.1');
failTimes($throttle, 2, 'JANA@example.com ', '10.0.0.2');
Assert::true(isBlocked($throttle, 'jana@example.com', '10.0.0.3'));


// Spellings the database resolves to the same account share its counter, even ones the normalization keeps apart
$throttle = createThrottle(['jana@example.com' => 7, "jana@example.com\u{00A0}" => 7, 'petr@example.com' => 8]);
failTimes($throttle, 3, 'jana@example.com', '10.0.0.1');
failTimes($throttle, 2, "jana@example.com\u{00A0}", '10.0.0.2');
Assert::true(isBlocked($throttle, 'jana@example.com', '10.0.0.3'));
Assert::false(isBlocked($throttle, 'petr@example.com', '10.0.0.3'));


// Failures from one IP with different e-mails count together
$throttle = createThrottle();
foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
	failTimes($throttle, 1, "$name@example.com", '10.0.0.1');
}
Assert::true(isBlocked($throttle, 'f@example.com', '10.0.0.1'));
Assert::false(isBlocked($throttle, 'f@example.com', '10.0.0.2'));


// Without a known IP only the e-mail is counted
$throttle = createThrottle();
failTimes($throttle, 5, 'jana@example.com', null);
Assert::true(isBlocked($throttle, 'jana@example.com', null));
Assert::false(isBlocked($throttle, 'petr@example.com', null));


// The window expires: failures older than 15 minutes no longer count
$now = 1_000_000;
$throttle = createThrottle();
failTimes($throttle, 5, 'jana@example.com', '10.0.0.1');
$now += 15 * 60 - 1;
Assert::true(isBlocked($throttle, 'jana@example.com', '10.0.0.1'));
$now += 1;
Assert::false(isBlocked($throttle, 'jana@example.com', '10.0.0.1'));


// The window slides: only failures within the last 15 minutes count
$now = 1_000_000;
$throttle = createThrottle();
failTimes($throttle, 3, 'jana@example.com', '10.0.0.1');
$now += 10 * 60;
failTimes($throttle, 2, 'jana@example.com', '10.0.0.1');
Assert::true(isBlocked($throttle, 'jana@example.com', '10.0.0.1'));
$now += 5 * 60; // the first three are now 15 minutes old
Assert::false(isBlocked($throttle, 'jana@example.com', '10.0.0.1'));


// A successful login resets the account counter
$now = 1_000_000;
$throttle = createThrottle(['jana@example.com' => 7]);
failTimes($throttle, 4, 'jana@example.com', '10.0.0.1');
succeed($throttle, 'jana@example.com', '10.0.0.2');
failTimes($throttle, 4, 'jana@example.com', '10.0.0.3');
Assert::false(isBlocked($throttle, 'jana@example.com', '10.0.0.4'));


// A successful login keeps the IP counter: logging into an own account must not hide password spraying
$throttle = createThrottle(['jana@example.com' => 7, 'petr@example.com' => 8]);
failTimes($throttle, 4, 'jana@example.com', '10.0.0.1');
succeed($throttle, 'petr@example.com', '10.0.0.1');
failTimes($throttle, 1, 'eva@example.com', '10.0.0.1');
Assert::true(isBlocked($throttle, 'eva@example.com', '10.0.0.1'));


// The lock is held while authenticating, so concurrent attempts are serialized
$throttle = createThrottle();
$throttle->attempt('jana@example.com', '10.0.0.1', function () use ($lockFile) {
	$other = fopen($lockFile, 'c');
	assert($other !== false);
	Assert::false(flock($other, LOCK_EX | LOCK_NB));
	fclose($other);
});


// The lock is released after a failure, so further attempts don't hang
$throttle = createThrottle();
failTimes($throttle, 2, 'jana@example.com', '10.0.0.1');
$other = fopen($lockFile, 'c');
assert($other !== false);
Assert::true(flock($other, LOCK_EX | LOCK_NB));
fclose($other);
