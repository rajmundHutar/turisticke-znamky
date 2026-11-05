<?php

declare(strict_types=1);

use Nette\Neon\Neon;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// Pages differ per user, so they must stay out of the hosting's shared proxy cache
// (it cached them for 5 minutes and served one user's page to another).
$config = Neon::decodeFile(__DIR__ . '/../../app/config/common.neon');
Assert::same('private, no-cache', $config['http']['headers']['Cache-Control']);
