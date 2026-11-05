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
		'general.prev' => 'Předchozí',
		'general.next' => 'Další',
		'general.chooseSomething' => 'Vyberte…',
		'general.closeDialog' => 'Zavřít',
		'general.pagination' => 'Stránkování',
		'general.goToPage{page}' => 'Přejít na stránku {page}',
		'general.page{page}' => 'Stránka {page}',

		'nearby.title' => 'Poblíž',
		'nearby.locateAgain' => 'Znovu zjistit polohu',
		'nearby.noScript' => 'Ke zjištění polohy je potřeba zapnutý JavaScript.',
		'nearby.status.locating' => 'Zjišťuji polohu…',
		'nearby.status.located' => 'Poloha zjištěna · přesnost ±{accuracy} m', // {accuracy} filled in by bundle.js
		'nearby.status.denied' => 'Přístup k poloze je zakázaný. Povolte ho tomuto webu v nastavení prohlížeče a zkuste to znovu.',
		'nearby.status.unavailable' => 'Polohu se nepodařilo zjistit. Zkontrolujte, že máte zapnuté určování polohy, a zkuste to znovu.',
		'nearby.status.timeout' => 'Zjišťování polohy trvalo příliš dlouho. Zkuste to znovu.',
		'nearby.status.unsupported' => 'Tento prohlížeč neumí zjistit polohu.',
		'nearby.status.loadFailed' => 'Známky se nepodařilo načíst. Zkuste to znovu.',

		'login.email' => 'E-mail',
		'login.password' => 'Heslo',
		'login.submit' => 'Přihlásit se',
		'login.loginSuccessful' => 'Přihlášení proběhlo úspěšně.',
		'login.loginNotSuccessful' => 'Nesprávný e-mail nebo heslo.',
		'login.tooManyAttempts' => 'Příliš mnoho pokusů o přihlášení, zkuste to později.',

		'menu.mainNavigation' => 'Hlavní navigace',
		'menu.allStamps' => 'Známky',
		'menu.nearby' => 'Poblíž',
		'menu.myCollection' => 'Sbírka',
		'menu.myCollectionStats' => 'Statistiky',
		'menu.login' => 'Přihlásit se',
		'menu.logout' => 'Odhlásit se',
		'menu.administration' => 'Administrace',
		'menu.search' => 'Hledat',
		'detail.detailsOn' => 'Více na',
		'detail.nearby' => 'Známky poblíž',
		'detail.website' => 'Web',
		'detail.salespoints' => 'Kde koupit',
		'detail.stampNumber{number}' => 'Známka {number}',
		'detail.inCollection' => 'Ve sbírce',
		'detail.inCollectionSince{date}' => 'Ve sbírce od {date}',
		'detail.about' => 'O místě',
		'detail.showFullDescription' => 'Zobrazit celý popis',
		'detail.showLessDescription' => 'Zobrazit méně',

		'stats.numberOfCollected' => 'sebráno',
		'stats.percentage' => 'procent',
		'stats.collectedThisYear' => 'letos sebráno',
		'stats.mostConsecutiveItems' => 'nejvíc čísel za sebou',
		'stats.lowestNumber' => 'nejnižší číslo',
		'stats.highestNumber' => 'nejvyšší číslo',

		'stamp.collect' => 'Přidat do sbírky',
		'stamp.uncollect' => 'Odebrat ze sbírky',
		'stamp.noImage' => 'Zatím bez obrázku',
		'stamp.collectedOn' => 'Sebráno',
		'stamp.editInCollection' => 'Upravit ve sbírce',
		'stamp.saveToCollection' => 'Uložit do sbírky',
		'stamp.uncollectConfirm' => 'Opravdu odebrat známku ze sbírky?',
		'stamp.sheet.add' => 'Přidat',
		'stamp.sheet.edit' => 'Upravit',
		'stamp.sheet.saveFailed' => 'Uložení se nepovedlo, zkuste to znovu.',
		'stamp.flash.added' => 'Známka přidána do sbírky.',
		'stamp.flash.saved' => 'Uloženo.',
		'stamp.flash.removed' => 'Známka odebrána ze sbírky.',

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
		'form.filter.collection' => 'Sbírka',
		'form.filter.all' => 'Vše',
		'form.filter.mine' => 'Mám',
		'form.filter.missing' => 'Nemám',
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
