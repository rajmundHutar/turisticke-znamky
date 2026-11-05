<?php

/**
 * Test: Error4xxPresenter renders 4xx error pages in the error layout (same look as 500.phtml)
 */

declare(strict_types=1);

use App\Presenters\Error4xxPresenter;
use Nette\Application\BadRequestException;
use Nette\Application\Request;
use Nette\Application\Responses\TextResponse;
use Tester\Assert;
use Tests\Presenters\PresenterTestHelpers;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/PresenterTestHelpers.php';


function renderError(int $code): string
{
	$presenter = new Error4xxPresenter(PresenterTestHelpers::createTranslator());
	PresenterTestHelpers::injectPrimary($presenter, PresenterTestHelpers::createUser(null));
	$response = $presenter->run(new Request('Error4xx', Request::FORWARD, [
		'exception' => new BadRequestException('', $code),
	]));
	Assert::type(TextResponse::class, $response);
	assert($response instanceof TextResponse); // narrows the type for PHPStan
	return (string) $response->getSource();
}


foreach ([404 => 'Stránka nenalezena', 403 => 'Přístup odepřen'] as $code => $heading) {
	$html = renderError($code);
	Assert::contains("<h1>$heading</h1>", $html);
	Assert::contains('general.turistStampsTitle', $html); // layout title, translated by the identity translator
	Assert::contains('<div id="nette-error">', $html); // error layout, not the site layout
	Assert::notContains('bulma.min.css', $html);
	// the dark theme (#53): the colour tokens, versioned
	Assert::contains('<link rel="stylesheet" href="http://localhost/css/theme.css?v=' . filemtime(__DIR__ . '/../../www/css/theme.css') . '">', $html);
	Assert::contains('<meta name="theme-color" content="#161819">', $html);
}
