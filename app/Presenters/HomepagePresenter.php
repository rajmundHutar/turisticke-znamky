<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\EditStampForm;
use App\Forms\FilterForm;
use App\Helpers\Paginator;
use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\InjectFormFactory;
use App\Presenters\Traits\InjectMenu;
use App\Presenters\Traits\InjectTranslator;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Nette\Application\UI\Presenter;

final class HomepagePresenter extends Presenter
{

	use InjectMenu,
		InjectFormFactory,
		InjectTranslator,
		InjectStampsModel,
		InjectCollectionModel;

	private const StampsPerPage = 200;

	/** @persistent */
	public ?string $search = '';
	/** @persistent */
	public ?string $sort = null;
	/** @persistent */
	public ?string $label = null;
	/** @persistent */
	public bool $onlyCollected = false;

	protected array $userCollection = [];

	public function actionDefault()
	{
		$this->userCollection = $this->collectionModel->fetchByUser($this->user->getId());
	}

	public function handleToggleCollect(int $id)
	{
		if ($this->user->isLoggedIn()) {
			$this->collectionModel->toggleCollect($id, $this->user->getId());
			$this->redirect("this#stamp-{$id}", $this->getFilter());
		}
	}

	public function renderDefault(bool $onlyCollected = null): void
	{
		$filter = $this->getFilter();

		if ($onlyCollected && $this->userCollection) {
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
		);
		if ($filter['sort'] === 'date' && $onlyCollected) {
			usort($stamps, function ($a, $b) {
				return $this->userCollection[$a->id]->date <=> $this->userCollection[$b->id]->date;
			});
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

	public function createComponentEditStampForm()
	{
		return new Multiplier(function (string $stampId) {

			$defaults = [];
			if ($this->userCollection[$stampId] ?? null) {
				$defaults['date'] = $this->userCollection[$stampId]->date;
				$defaults['comment'] = $this->userCollection[$stampId]->comment;
			}

			return $this->formFactory->create(
				EditStampForm::class,
				(int)$stampId,
				$defaults,
				function (
					Form $form,
					array $values
				) {

					$stampId = $values['id'];
					unset($values['id']);
					$this->collectionModel->update(
						(int)$stampId,
						$this->user->getId(),
						$values
					);

				});

		});
	}

	public function createComponentPaginator(): Control
	{
		$paginator = new Paginator();
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
