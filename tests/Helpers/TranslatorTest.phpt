<?php

declare(strict_types=1);

use App\Helpers\Translator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$translator = new Translator;

// Known key
Assert::same('Zrušit', $translator->translate('general.reset'));

// Unknown key falls back to the key itself
Assert::same('unknown.key', $translator->translate('unknown.key'));

// Placeholders
Assert::same(
	'Nové známky: 3 z 10.',
	$translator->translate('admin.{new}stampsOutOf{total}', ['total' => 10, 'new' => 3]),
);

// Non-array parameter (form validator passes e.g. null as a count) returns the plain translation
Assert::same('Zrušit', $translator->translate('general.reset', null));
Assert::same('Zrušit', $translator->translate('general.reset', 3));

// The UI is in Czech (#47)
Assert::same('Předchozí', $translator->translate('general.prev'));
Assert::same('Další', $translator->translate('general.next'));
Assert::same('Přidat do sbírky', $translator->translate('stamp.collect'));
Assert::same('Odebrat ze sbírky', $translator->translate('stamp.uncollect'));
Assert::same('Uložení se nepovedlo, zkuste to znovu.', $translator->translate('stamp.sheet.saveFailed'));
Assert::same('Přihlášení proběhlo úspěšně.', $translator->translate('login.loginSuccessful'));
Assert::same('Neplatné datum, zadejte D. M. RRRR.', $translator->translate('form.editStamp.invalidDate'));
Assert::same('sebráno', $translator->translate('stats.numberOfCollected'));
