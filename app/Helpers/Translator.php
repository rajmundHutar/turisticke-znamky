<?php

declare(strict_types=1);

namespace App\Helpers;

class Translator implements \Nette\Localization\Translator {

	const Translations = [
		'general.turistStampsTitle' => 'Turistické známky',
		'general.reset' => 'Reset',
		'general.resetFilter' => 'Reset filter',
		'general.noItemsFound' => 'No items found.',
		'general.edit' => 'Edit',
		'general.prev' => 'Previous',
		'general.next' => 'Next',
		'general.submit' => 'Submit',
		'general.chooseSomething' => 'Select option...',
		'general.close' => '✘',

		'nearby.forLocation{lat}{lng}' => 'Nearby stamps for {lat}, {lng}.',

		'login.email' => 'E-mail',
		'login.password' => 'Password',
		'login.loginSuccessful' => 'User successfully logged in.',
		'login.loginNotSuccessful' => 'Incorrect password or user e-mail.',

		'menu.allStamps' => 'All stamps',
		'menu.nearby' => 'Nearby',
		'menu.myCollection' => 'My collection',
		'menu.myCollectionStats' => 'My stats',
		'menu.login' => 'Login',
		'menu.logout' => 'Logout',
		'menu.administration' => 'Admin',
		'detail.detailsOn' => 'More details on',
		'detail.nearby' => 'Stamps nearby:',

		'stats.numberOfCollected' => 'collected',
		'stats.percentage' => 'percentage',
		'stats.collectedThisYear' => 'collected this year',
		'stats.mostConsecutiveItems' => 'most consecutive items',

		'stamp.collected' => 'Have it!',
		'stamp.collect' => 'Collect',
		'stamp.uncollect' => 'Disperse',

		'admin.{count}loadedImages' => 'Loaded {count} new images.',
		'admin.{new}stampsOutOf{total}' => 'Loaded {new} new stamps out of {total}.',

		'form.filter.search' => 'Search',
		'form.filter.sortBy' => 'Sort by',
		'form.filter.sortBy.id.asc' => '123 ↑',
		'form.filter.sortBy.id.desc' => '123 ↓',
		'form.filter.sortBy.date.asc' => 'Date ↑',
		'form.filter.sortBy.date.desc' => 'Date ↓',
		'form.filter.label' => 'Label',
		'form.editStamp.date' => 'Date',
		'form.editStamp.description' => 'Description',

	];

	function translate($message, ...$parameters): string {

		$translation = self::Translations[$message] ?? null;

		if ($translation === null) {
			return $message;
		}

		if (!$parameters) {
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
