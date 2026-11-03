<?php

declare(strict_types=1);

use App\Helpers\Translator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$translator = new Translator;

// Known key
Assert::same('Uložit', $translator->translate('general.submit'));

// Unknown key falls back to the key itself
Assert::same('unknown.key', $translator->translate('unknown.key'));

// Placeholders
Assert::same(
	'Nové známky: 3 z 10.',
	$translator->translate('admin.{new}stampsOutOf{total}', ['total' => 10, 'new' => 3]),
);

// Non-array parameter (form validator passes e.g. null as a count) returns the plain translation
Assert::same('Uložit', $translator->translate('general.submit', null));
Assert::same('Uložit', $translator->translate('general.submit', 3));

// The UI is in Czech (#47)
Assert::same('Předchozí', $translator->translate('general.prev'));
Assert::same('Další', $translator->translate('general.next'));
Assert::same('Mám', $translator->translate('stamp.collected'));
Assert::same('Přidat do sbírky', $translator->translate('stamp.collect'));
Assert::same('Odebrat ze sbírky', $translator->translate('stamp.uncollect'));
Assert::same('Přihlášení proběhlo úspěšně.', $translator->translate('login.loginSuccessful'));
Assert::same('Neplatné datum, zadejte D. M. RRRR.', $translator->translate('form.editStamp.invalidDate'));
Assert::same('sebráno', $translator->translate('stats.numberOfCollected'));
