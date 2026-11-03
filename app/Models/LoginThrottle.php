<?php

declare(strict_types=1);

namespace App\Models;

use Nette\Caching\Cache;
use Nette\Caching\Storage;

/**
 * Counts failed logins per e-mail and per IP; blocks further attempts after too many failures in a sliding time window.
 */
class LoginThrottle
{
	private const MaxFailures = 5;
	private const WindowSeconds = 15 * 60;

	private Cache $cache;

	/** @var \Closure(): int */
	private \Closure $clock;


	/** @param  (\Closure(): int)|null  $clock  current Unix time, injectable for tests */
	public function __construct(Storage $storage, ?\Closure $clock = null)
	{
		$this->cache = new Cache($storage, 'loginThrottle');
		$this->clock = $clock ?? time(...);
	}


	public function isBlocked(string $email, ?string $ip): bool
	{
		return array_any($this->keys($email, $ip), fn($key) => count($this->recentFailures($key)) >= self::MaxFailures);
	}


	public function recordFailure(string $email, ?string $ip): void
	{
		foreach ($this->keys($email, $ip) as $key) {
			$failures = $this->recentFailures($key);
			$failures[] = ($this->clock)();
			$this->cache->save($key, $failures, [Cache::Expire => self::WindowSeconds]);
		}
	}


	public function reset(string $email, ?string $ip): void
	{
		foreach ($this->keys($email, $ip) as $key) {
			$this->cache->remove($key);
		}
	}


	/** @return list<string> */
	private function keys(string $email, ?string $ip): array
	{
		$keys = ['email:' . strtolower(trim($email))];
		if ($ip !== null && $ip !== '') {
			$keys[] = 'ip:' . $ip;
		}
		return $keys;
	}


	/** @return list<int> timestamps of failures within the window */
	private function recentFailures(string $key): array
	{
		$since = ($this->clock)() - self::WindowSeconds;
		$failures = $this->cache->load($key) ?? [];
		return array_values(array_filter($failures, fn(int $time) => $time > $since));
	}
}
