<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\FilterForm;
use App\Helpers\Paginator;
use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\CollectStamps;
use App\Presenters\Traits\InjectFormFactory;
use App\Presenters\Traits\InjectMenu;
use App\Presenters\Traits\InjectTranslator;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Control;
use Nette\Application\UI\Presenter;

final class HomepagePresenter extends Presenter
{

	use InjectMenu;
	use InjectFormFactory;
	use InjectTranslator;
	use InjectStampsModel;
	use InjectCollectionModel;
	use CollectStamps;

	private const StampsPerPage = 200;

	#[Persistent]
	public ?string $search = '';
	#[Persistent]
	public ?string $sort = null;
	#[Persistent]
	public bool $onlyCollected = false;

	public function actionDefault()
	{
		$this->userCollection = $this->collectionModel->fetchByUser($this->user->getId());
	}

	public function renderDefault(?bool $onlyCollected = null): void
	{
		$filter = $this->getFilter();

		if ($onlyCollected) {
			$filter['ids'] = array_keys($this->userCollection);
		}
		$this['filterForm']->setDefaults($filter);

		/** @var Paginator $paginator */
		$paginator = $this['paginator'];
		$paginator->setItemsPerPage(self::StampsPerPage);
		$paginator->setItemCount($this->stampsModel->count($filter));

		$stamps = $this->stampsModel->search(
			$filter,
			$paginator->getLength(),
			$paginator->getOffset()
		) ?? [];
		if ($filter['sort'] === 'date' && $onlyCollected) {
			usort($stamps, fn($a, $b) => $this->userCollection[$a->id]->date <=> $this->userCollection[$b->id]->date);
		}
		if ($filter['sort'] === '-date' && $onlyCollected) {
			usort($stamps, fn($b, $a) => $this->userCollection[$a->id]->date <=> $this->userCollection[$b->id]->date);
		}
		$this->template->stamps = $stamps;

		$this->template->totalStampsCount = $this->stampsModel->count($filter);
		$this->template->collection = $this->userCollection;
		$this->template->filter = $filter;
	}


	public function createComponentFilterForm()
	{
		$sortBy = [
			'id' => 'form.filter.sortBy.id.asc',
			'-id' => 'form.filter.sortBy.id.desc',
		];
		if ($this->onlyCollected) {
			$sortBy['date'] = 'form.filter.sortBy.date.asc';
			$sortBy['-date'] = 'form.filter.sortBy.date.desc';
		}
		return $this->formFactory->create(FilterForm::class, $sortBy);
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
			'sort' => $this->getParameter('sort'),
		];
	}
}
