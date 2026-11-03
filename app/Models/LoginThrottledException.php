<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Too many failed logins recently; the attempt was rejected without checking the password.
 */
class LoginThrottledException extends \RuntimeException {}
