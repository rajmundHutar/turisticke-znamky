<?php

/**
 * Test: every translation key used in templates and PHP code exists in the Translator
 */

declare(strict_types=1);

use App\Helpers\Translator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$appDir = __DIR__ . '/../../app';
$used = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)) as $file) {
	$path = (string) $file;
	$source = (string) file_get_contents($path);
	if (str_ends_with($path, '.latte')) {
		// {_'key'}, {_"key"}, {_key} and {{_'key'}} (double syntax)
		preg_match_all('~\{\{?_\s*(?:\'([^\']+)\'|"([^"]+)"|([\w.{}]+))~', $source, $m);
		$keys = array_filter([...$m[1], ...$m[2], ...$m[3]]);
	} elseif (str_ends_with($path, '.php')) {
		// string literals in a translation namespace, e.g. ->t('login.email'), form labels, select items, validator messages
		preg_match_all('~[\'"]((?:general|nearby|login|menu|detail|stats|stamp|admin|form)\.[\w.{}]+)[\'"]~', $source, $m);
		$keys = $m[1];
	} else {
		continue;
	}
	foreach ($keys as $key) {
		$used[$key][] = substr($path, strlen($appDir) + 1);
	}
}

Assert::true(count($used) > 30, 'scanner finds the keys'); // guards against a broken regex passing vacuously
Assert::true(isset($used['general.prev']));
Assert::true(isset($used['form.editStamp.invalidDate']));
Assert::true(isset($used['form.filter.sortBy.date.asc']));

$missing = array_diff_key($used, Translator::Translations);
Assert::same([], $missing);
