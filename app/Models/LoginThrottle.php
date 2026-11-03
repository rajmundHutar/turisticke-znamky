<?php

declare(strict_types=1);

namespace App\Models;

use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Security\AuthenticationException;
use Nette\Utils\Strings;

/**
 * Counts failed logins per account and per IP; blocks further attempts after too many failures in a sliding time window.
 */
class LoginThrottle
{
	private const MaxFailures = 5;
	private const WindowSeconds = 15 * 60;

	private Cache $cache;

	/** @var \Closure(): int */
	private \Closure $clock;


	/**
	 * @param  string  $lockFile  serializes attempts across concurrent requests
	 * @param  (\Closure(): int)|null  $clock  current Unix time, injectable for tests
	 */
	public function __construct(
		private string $lockFile,
		Storage $storage,
		private UserModel $userModel,
		?\Closure $clock = null,
	) {
		$this->cache = new Cache($storage, 'loginThrottle');
		$this->clock = $clock ?? time(...);
	}


	/**
	 * Runs the authentication unless the account or IP is blocked, and records its outcome.
	 * The whole check-authenticate-record sequence holds an exclusive lock, so parallel requests can't exceed the limit.
	 * @param  callable(): void  $authenticate  throws AuthenticationException on wrong credentials
	 * @throws LoginThrottledException  too many recent failures; $authenticate was not called
	 * @throws AuthenticationException
	 */
	public function attempt(string $email, ?string $ip, callable $authenticate): void
	{
		$lock = fopen($this->lockFile, 'c') ?: throw new \RuntimeException("Cannot open lock file '$this->lockFile'.");
		try {
			if (!flock($lock, LOCK_EX)) {
				throw new \RuntimeException("Cannot lock '$this->lockFile'."); // never authenticate without the lock
			}
			$accountKey = $this->accountKey($email);
			$keys = $ip !== null && $ip !== '' ? [$accountKey, 'ip:' . $ip] : [$accountKey];
			if (array_any($keys, fn($key) => count($this->recentFailures($key)) >= self::MaxFailures)) {
				throw new LoginThrottledException('Too many failed login attempts.');
			}

			try {
				$authenticate();
			} catch (AuthenticationException $e) {
				foreach ($keys as $key) {
					$failures = $this->recentFailures($key);
					$failures[] = ($this->clock)();
					$this->cache->save($key, $failures, [Cache::Expire => self::WindowSeconds]);
				}
				throw $e;
			}

			// The IP counter is shared by all accounts and only expires, so logging into an own account can't hide password spraying
			$this->cache->remove($accountKey);

		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}


	/** Existing accounts are keyed by id, as the DB collation matches more spellings than any normalization here. */
	private function accountKey(string $email): string
	{
		$row = $this->userModel->fetchByEmail($email);
		return $row
			? 'user:' . $row['id']
			: 'email:' . strtolower(Strings::toAscii(trim($email)));
	}


	/** @return list<int> timestamps of failures within the window */
	private function recentFailures(string $key): array
	{
		$since = ($this->clock)() - self::WindowSeconds;
		$failures = $this->cache->load($key) ?? [];
		return array_values(array_filter($failures, fn(int $time) => $time > $since));
	}
}
