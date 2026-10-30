<?php

declare(strict_types=1);

use App\Helpers\Paginator;
use Nette\Application\UI\ComponentReflection;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// Persistent params are declared via #[Persistent]; the @persistent annotation is deprecated since nette/application 3.3
Assert::noError(function () {
	Assert::same(['page'], array_keys((new ComponentReflection(Paginator::class))->getPersistentParams()));
});
