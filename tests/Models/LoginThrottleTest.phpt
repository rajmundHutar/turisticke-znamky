<?php

/**
 * Test: LoginThrottle blocks an e-mail or IP after too many failed logins in a time window (#38)
 */

declare(strict_types=1);

use App\Models\LoginThrottle;
use Nette\Caching\Storages\MemoryStorage;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$now = 1_000_000;
$clock = function () use (&$now): int {
	return $now;
};

function failTimes(LoginThrottle $throttle, int $count, string $email, ?string $ip): void
{
	for ($i = 0; $i < $count; $i++) {
		$throttle->recordFailure($email, $ip);
	}
}


// Four failures are tolerated, the fifth blocks the same e-mail and the same IP
$throttle = new LoginThrottle(new MemoryStorage, $clock);
failTimes($throttle, 4, 'jana@example.com', '10.0.0.1');
Assert::false($throttle->isBlocked('jana@example.com', '10.0.0.1'));
$throttle->recordFailure('jana@example.com', '10.0.0.1');
Assert::true($throttle->isBlocked('jana@example.com', '10.0.0.1'));


// A different e-mail from a different IP is not blocked
Assert::false($throttle->isBlocked('petr@example.com', '10.0.0.2'));


// The same e-mail from another IP is blocked; e-mail is normalized (case, whitespace)
Assert::true($throttle->isBlocked('  Jana@Example.COM ', '10.0.0.2'));


// The same IP with another e-mail is blocked
Assert::true($throttle->isBlocked('petr@example.com', '10.0.0.1'));


// Failures with differently written e-mails count together
$throttle = new LoginThrottle(new MemoryStorage, $clock);
failTimes($throttle, 3, 'jana@example.com', '10.0.0.1');
failTimes($throttle, 2, 'JANA@example.com ', '10.0.0.2');
Assert::true($throttle->isBlocked('jana@example.com', '10.0.0.3'));


// Failures from one IP with different e-mails count together
$throttle = new LoginThrottle(new MemoryStorage, $clock);
foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
	$throttle->recordFailure("$name@example.com", '10.0.0.1');
}
Assert::true($throttle->isBlocked('f@example.com', '10.0.0.1'));
Assert::false($throttle->isBlocked('f@example.com', '10.0.0.2'));


// Without a known IP only the e-mail is counted
$throttle = new LoginThrottle(new MemoryStorage, $clock);
failTimes($throttle, 5, 'jana@example.com', null);
Assert::true($throttle->isBlocked('jana@example.com', null));
Assert::false($throttle->isBlocked('petr@example.com', null));


// The window expires: failures older than 15 minutes no longer count
$now = 1_000_000;
$throttle = new LoginThrottle(new MemoryStorage, $clock);
failTimes($throttle, 5, 'jana@example.com', '10.0.0.1');
$now += 15 * 60 - 1;
Assert::true($throttle->isBlocked('jana@example.com', '10.0.0.1'));
$now += 1;
Assert::false($throttle->isBlocked('jana@example.com', '10.0.0.1'));


// The window slides: only failures within the last 15 minutes count
$now = 1_000_000;
$throttle = new LoginThrottle(new MemoryStorage, $clock);
failTimes($throttle, 3, 'jana@example.com', '10.0.0.1');
$now += 10 * 60;
failTimes($throttle, 2, 'jana@example.com', '10.0.0.1');
Assert::true($throttle->isBlocked('jana@example.com', '10.0.0.1'));
$now += 5 * 60; // the first three are now 15 minutes old
Assert::false($throttle->isBlocked('jana@example.com', '10.0.0.1'));


// A successful login resets the counter for the e-mail and the IP
$now = 1_000_000;
$throttle = new LoginThrottle(new MemoryStorage, $clock);
failTimes($throttle, 4, 'jana@example.com', '10.0.0.1');
$throttle->reset('Jana@example.com', '10.0.0.1');
failTimes($throttle, 4, 'jana@example.com', '10.0.0.1');
Assert::false($throttle->isBlocked('jana@example.com', '10.0.0.1'));
