<?php

declare(strict_types=1);

namespace App\Helpers;

use Nette\Application\UI\Control;

class Paginator extends Control {

	/** @persistent  */
	public int $page = 1;

	protected \Nette\Utils\Paginator $paginator;

	public function __construct() {
		$this->paginator = new \Nette\Utils\Paginator();
	}

	public function getPage(): int {
		return $this->page;
	}

	public function getItemsPerPage(): int {
		return $this->paginator->getItemsPerPage();
	}

	public function setItemsPerPage(int $num): void {
		$this->paginator->setItemsPerPage($num);
	}

	public function setItemCount(int $num): void {
		$this->paginator->setItemCount($num);
	}

	public function getLength(): int {
		$this->paginator->setPage($this->page);
		return $this->paginator->getLength();
	}
	public function getOffset(): int {
		$this->paginator->setPage($this->page);
		return $this->paginator->getOffset();
	}

	public function render(): void {

		$this->paginator->setPage($this->page);

		$this->template->paginator = $this->paginator;

		$this->template->setFile(__DIR__ . '/paginator.latte');
		$this->template->render();

	}


}
