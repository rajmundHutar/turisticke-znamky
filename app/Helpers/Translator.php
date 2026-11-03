<?php

declare(strict_types=1);

namespace App\Helpers;

class Translator implements \Nette\Localization\Translator
{

	public const Translations = [
		'general.turistStampsTitle' => 'Turistické známky',
		'general.reset' => 'Zrušit',
		'general.resetFilter' => 'Zrušit filtr',
		'general.noItemsFound' => 'Nic nenalezeno.',
		'general.edit' => 'Upravit',
		'general.prev' => 'Předchozí',
		'general.next' => 'Další',
		'general.submit' => 'Uložit',
		'general.chooseSomething' => 'Vyberte…',
		'general.close' => '✘',
		'general.closeDialog' => 'Zavřít',
		'general.pagination' => 'Stránkování',
		'general.goToPage{page}' => 'Přejít na stránku {page}',
		'general.page{page}' => 'Stránka {page}',

		'nearby.forLocation{lat}{lng}' => 'Známky poblíž {lat}, {lng}.',
		'nearby.geolocationNotAllowed' => 'Prohlížeč nepovolil zjištění polohy.',

		'login.email' => 'E-mail',
		'login.password' => 'Heslo',
		'login.submit' => 'Přihlásit se',
		'login.loginSuccessful' => 'Přihlášení proběhlo úspěšně.',
		'login.loginNotSuccessful' => 'Nesprávný e-mail nebo heslo.',
		'login.tooManyAttempts' => 'Příliš mnoho pokusů o přihlášení, zkuste to později.',

		'menu.mainNavigation' => 'Hlavní navigace',
		'menu.toggle' => 'Menu',
		'menu.allStamps' => 'Známky',
		'menu.nearby' => 'Poblíž',
		'menu.myCollection' => 'Moje sbírka',
		'menu.myCollectionStats' => 'Statistiky',
		'menu.login' => 'Přihlásit se',
		'menu.logout' => 'Odhlásit se',
		'menu.administration' => 'Administrace',
		'detail.detailsOn' => 'Více na',
		'detail.nearby' => 'Známky poblíž:',
		'detail.website' => 'Web',
		'detail.salespoints' => 'Prodejní místa:',

		'stats.numberOfCollected' => 'sebráno',
		'stats.percentage' => 'procent',
		'stats.collectedThisYear' => 'letos sebráno',
		'stats.mostConsecutiveItems' => 'nejvíc čísel za sebou',
		'stats.lowestNumber' => 'nejnižší číslo',
		'stats.highestNumber' => 'nejvyšší číslo',

		'stamp.collected' => 'Mám',
		'stamp.collect' => 'Přidat do sbírky',
		'stamp.uncollect' => 'Odebrat ze sbírky',
		'stamp.noImage' => 'Zatím bez obrázku',

		'admin.{count}loadedImages' => 'Nové obrázky: {count}.',
		'admin.{new}stampsOutOf{total}' => 'Nové známky: {new} z {total}.',
		'admin.importFailed{error}' => 'Import selhal: {error}',

		'form.filter.search' => 'Hledat',
		'form.filter.submit' => 'Hledat',
		'form.filter.sortBy' => 'Řadit podle',
		'form.filter.sortBy.id.asc' => '123 ↑',
		'form.filter.sortBy.id.desc' => '123 ↓',
		'form.filter.sortBy.date.asc' => 'Datum ↑',
		'form.filter.sortBy.date.desc' => 'Datum ↓',
		'form.editStamp.date' => 'Datum',
		'form.editStamp.description' => 'Poznámka',
		'form.editStamp.invalidDate' => 'Neplatné datum, zadejte D. M. RRRR.',

	];

	public function translate($message, ...$parameters): string
	{

		$translation = self::Translations[$message] ?? null;

		if ($translation === null) {
			return $message;
		}

		// only an array of named placeholders is supported (form validators may pass e.g. a null count)
		if (!is_array($parameters[0] ?? null)) {
			return $translation;
		}

		$parameters = $parameters[0];

		$keys = array_map(fn($item) => "{" . $item . "}", array_keys($parameters));

		return strtr(
			$translation,
			array_combine($keys, $parameters),
		);
	}

}
