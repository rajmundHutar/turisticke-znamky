<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\LoginThrottle;

trait InjectLoginThrottle
{
	protected LoginThrottle $loginThrottle;


	public function injectLoginThrottle(LoginThrottle $loginThrottle): void
	{
		$this->loginThrottle = $loginThrottle;
	}
}
