<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\FilterForm;
use App\Helpers\Paginator;
use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\CollectStamps;
use App\Presenters\Traits\InjectMenu;
use App\Presenters\Traits\InjectTranslator;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;

final class HomepagePresenter extends Presenter
{

	use InjectMenu;
	use InjectTranslator;
	use InjectStampsModel;
	use InjectCollectionModel;
	use CollectStamps {
		redrawAfterCollectionChange as private redrawStampAfterCollectionChange;
	}

	private const StampsPerPage = 60;

	#[Persistent]
	public ?string $search = '';
	#[Persistent]
	public ?string $sort = null;
	#[Persistent]
	public bool $onlyCollected = false;
	/** "Nemám": stamps not in the collection of a logged-in user; onlyCollected wins if both are set */
	#[Persistent]
	public bool $notCollected = false;

	private FilterForm $filterForm;


	public function injectFilterForm(FilterForm $filterForm): void
	{
		$this->filterForm = $filterForm;
	}


	public function actionDefault()
	{
		$this->userCollection = $this->collectionModel->fetchByUser($this->user->getId());
	}

	public function renderDefault(): void
	{
		$filter = $this->getFilter();
		$this['filterForm']->setDefaults($filter);

		$collectedIds = array_keys($this->userCollection);
		$listFilter = $filter;
		if ($this->onlyCollected) {
			$listFilter['ids'] = $collectedIds;
		} elseif ($this->isNotCollectedView()) {
			$listFilter['excludeIds'] = $collectedIds;
		}
		$count = $this->stampsModel->count($listFilter);

		/** @var Paginator $paginator */
		$paginator = $this['paginator'];
		$paginator->setItemsPerPage(self::StampsPerPage);
		$paginator->setItemCount($count);

		$stamps = $this->stampsModel->search(
			$listFilter,
			$paginator->getLength(),
			$paginator->getOffset()
		) ?? [];
		$this->template->stamps = $stamps;

		$this->template->totalStampsCount = $count;
		$this->template->collection = $this->userCollection;
		$this->template->filter = $filter;
		$this->template->notCollectedView = $this->isNotCollectedView();

		// counts of the switch Vše / Mám / Nemám, all of the stamps matching the search
		if ($this->user->isLoggedIn()) {
			$all = isset($listFilter['ids']) || isset($listFilter['excludeIds']) ? $this->stampsModel->count($filter) : $count;
			$collected = match (true) {
				$this->onlyCollected => $count,
				$this->isNotCollectedView() => $all - $count,
				default => $this->stampsModel->count(['ids' => $collectedIds] + $filter),
			};
			$this->template->switchCounts = ['all' => $all, 'collected' => $collected, 'notCollected' => $all - $collected];
		}

		// a filter change by AJAX (live filter, the switch); the search input stays as the user is typing in it
		if ($this->isAjax() && !$this->isControlInvalid()) { // nothing redrawn by a signal (the collect form)
			$this->redrawControl('sort'); // date items only in "Mám"
			$this->redrawControl('list');
			$this->redrawControl('collectSheet'); // its form action keeps the filter for redirects
		}
	}


	/** The counts of the switch Vše / Mám / Nemám change with the collection too (#50). */
	protected function redrawAfterCollectionChange(int $id): void
	{
		$this->redrawStampAfterCollectionChange($id);
		$this->redrawControl('collectionSwitch');
	}


	private function isNotCollectedView(): bool
	{
		return $this->notCollected && !$this->onlyCollected && $this->user->isLoggedIn();
	}


	public function createComponentFilterForm()
	{
		$form = $this->filterForm->create($this->getSortOptions());
		$form->onSubmit[] = function (Form $form): void {
			// the exact number of a stamp (leading zeros dropped by the int cast) goes straight to its detail;
			// in onSubmit, which runs before validation, fine here as FilterForm has no rules to wait for
			$search = trim((string) $form->getUntrustedValues('array')['search']);
			if (preg_match('~^\d+$~', $search) && $this->stampsModel->fetch((int) $search)) {
				$this->redirect('Stamp:', ['id' => (int) $search]);
			}
		};
		return $form;
	}

	/**
	 * Sort values offered by the filter; sorting by collection date only in "My collection".
	 * @return array<string, string>
	 */
	private function getSortOptions(): array
	{
		$sortBy = [
			'id' => 'form.filter.sortBy.id.asc',
			'-id' => 'form.filter.sortBy.id.desc',
		];
		if ($this->onlyCollected) {
			$sortBy['date'] = 'form.filter.sortBy.date.asc';
			$sortBy['-date'] = 'form.filter.sortBy.date.desc';
		}
		return $sortBy;
	}

	public function createComponentPaginator(): Control
	{
		$paginator = new Paginator;
		$paginator->setTranslator($this->translator);
		return $paginator;
	}

	private function getFilter()
	{
		return [
			'search' => $this->getParameter('search'),
			'sort' => isset($this->getSortOptions()[$this->sort ?? '']) ? $this->sort : null, // unknown values are ignored
			'userId' => $this->user->getId(), // sorting by collection date
		];
	}
}
