<?php

declare(strict_types=1);

namespace App\Controls;

use App\Presenters\HomepagePresenter;
use Nette\Application\UI\Control;
use Nette\Localization\Translator;

class Menu extends Control
{

	public function __construct(protected Translator $translator)
	{
	}

	public function handleLogout(): void
	{

		$presenter = $this->getPresenter();
		$presenter->getUser()->logout(true);
		// the homepage root, so persistent filters (e.g. "My collection") don't survive the logout
		$presenter->redirectUrl($presenter->getHttpRequest()->getUrl()->getBaseUrl());
	}

	/** The item of the bottom navigation for the current page: nearby, stamps (also the detail), collection, stats, or null (#53) */
	public function getActiveItem(): ?string
	{
		$presenter = $this->getPresenter();
		return match ($presenter->getName()) {
			'Nearby' => 'nearby',
			'Homepage' => $presenter instanceof HomepagePresenter && $presenter->onlyCollected ? 'collection' : 'stamps',
			'Stamp' => 'stamps', // the detail of a stamp
			'CollectionStats' => 'stats',
			default => null,
		};
	}

	public function render()
	{

		$this->template->setTranslator($this->translator);
		$this->template->active = $this->getActiveItem();
		$this->template->setFile(__DIR__ . '/Menu.latte');
		$this->template->render();
	}

}
