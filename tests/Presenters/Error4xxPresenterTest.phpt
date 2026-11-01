<?php

/**
 * Test: Error4xxPresenter renders 4xx error pages in the site layout
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


foreach ([404 => 'Page Not Found', 403 => 'Access Denied'] as $code => $heading) {
	$html = renderError($code);
	Assert::contains("<h1>$heading</h1>", $html);
	Assert::contains('general.turistStampsTitle', $html); // layout title, translated by the identity translator
}
