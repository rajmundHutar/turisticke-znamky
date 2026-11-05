<?php

/**
 * Test: the site layout loads the dark theme (#53): versioned static files, the font, theme-color; the stats without Bulma's removed tile
 */

declare(strict_types=1);

use App\Models\CollectionModel;
use App\Models\StampsModel;
use App\Presenters\CollectionStatsPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\TextResponse;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';


$presenter = new CollectionStatsPresenter(PresenterTestHelpers::createTranslator());
PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(['user']));
$presenter->injectCollectionModel(new class extends CollectionModel {
	public function __construct()
	{
	}


	public function fetchByUser(?int $userId): array
	{
		return [];
	}
});
$presenter->injectStampsModel(new class extends StampsModel {
	public function __construct()
	{
	}


	public function count(array $filter = []): int
	{
		return 0;
	}
});
$response = $presenter->run(new Request('CollectionStats', 'GET', ['action' => 'default']));
assert($response instanceof TextResponse);
$html = (string) $response->getSource();
$www = __DIR__ . '/../../www';


// The dark theme is forced; the browser bar of a phone has the page colour
Assert::match('%A%<html lang="cs" data-theme="dark">%A%', $html);
Assert::contains('<meta name="theme-color" content="#161819">', $html);


// The local CSS and JS have ?v= of their modification time
foreach (['css/bulma.min.css', 'css/theme.css', 'css/datepicker-bulma.min.css', 'css/styles.css'] as $file) {
	Assert::contains('<link rel="stylesheet" href="http://localhost/' . $file . '?v=' . filemtime("$www/$file") . '">', $html, $file);
}
foreach (['js/fslightbox.js', 'js/datepicker-full.min.js', 'js/naja-3.3.3.min.js', 'js/bundle.js'] as $file) {
	Assert::contains('<script src="http://localhost/' . $file . '?v=' . filemtime("$www/$file") . '"></script>', $html, $file);
}
Assert::notMatch('~(href|src)="http://localhost/(css|js)/[^"?]+"~', $html); // no local CSS or JS without a version


// IBM Plex Sans from Google Fonts
Assert::contains('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>', $html);
Assert::contains('family=IBM+Plex+Sans:wght@400;500;600;700&display=swap', $html);


// The stats are cards, Bulma 1 has no tile
Assert::notMatch('~class="[^"]*\btile\b~', $html);
Assert::contains('stats.numberOfCollected', $html);


// The web app manifest has the name and the theme colours
$manifest = json_decode((string) file_get_contents("$www/site.webmanifest"), true, flags: JSON_THROW_ON_ERROR);
Assert::same(['Turistické známky', 'Známky', '#161819', '#161819'], [$manifest['name'], $manifest['short_name'], $manifest['theme_color'], $manifest['background_color']]);
