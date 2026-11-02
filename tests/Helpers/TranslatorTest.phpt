<?php

declare(strict_types=1);

use App\Helpers\Translator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$translator = new Translator;

// Known key
Assert::same('Submit', $translator->translate('general.submit'));

// Unknown key falls back to the key itself
Assert::same('unknown.key', $translator->translate('unknown.key'));

// Placeholders
Assert::same(
	'Loaded 3 new stamps out of 10.',
	$translator->translate('admin.{new}stampsOutOf{total}', ['total' => 10, 'new' => 3]),
);

// Non-array parameter (form validator passes e.g. null as a count) returns the plain translation
Assert::same('Submit', $translator->translate('general.submit', null));
Assert::same('Submit', $translator->translate('general.submit', 3));
