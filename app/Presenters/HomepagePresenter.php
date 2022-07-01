<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\EditStampFormFactory;
use App\Forms\SearchForm;
use App\Helpers\Paginator;
use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use Nette;

final class HomepagePresenter extends Nette\Application\UI\Presenter {

	use InjectMenu,
		InjectStampsModel,
		InjectCollectionModel;

	private const StampsPerPage = 200;

	protected ?string $search = '';
	protected array $userCollection = [];

	public function actionDefault() {

		$this->userCollection = $this->collectionModel->fetchByUser($this->user->getId());

	}

	public function renderDefault(bool $onlyCollected = null) {

		$filter = $this->getFilter();

		if ($onlyCollected && $this->userCollection) {
			$filter['ids'] = array_keys($this->userCollection);
		}
		if ($filter['search']) {
			$this['searchForm']->setDefaults([
				'search' => $filter['search'],
				'sort' => $filter['sort'],
			]);
		}

		/** @var Paginator $paginator */
		$paginator = $this['paginator'];
		$paginator->setItemsPerPage(self::StampsPerPage);
		$paginator->setItemCount($this->stampsModel->count($filter));

		$this->template->allStamps = $this->stampsModel->search(
			$filter,
			$paginator->getLength(),
			$paginator->getOffset()
		);
		$this->template->collection = $this->userCollection;

	}

	public function handleToggleCollect(int $id) {

		if ($this->user->isLoggedIn()) {
			$this->collectionModel->toggleCollect($id, $this->user->getId());
		}

	}

	public function createComponentSearchForm() {

		return SearchForm::create();

	}

	public function createComponentEditStampForm() {

		return new Nette\Application\UI\Multiplier(function(string $stampId) {

				$defaults = [];
				if ($this->userCollection[$stampId] ?? null) {
					$defaults['date'] = $this->userCollection[$stampId]['date'];
					$defaults['comment'] = $this->userCollection[$stampId]['comment'];
				}

				return EditStampFormFactory::create(
					(int) $stampId, $defaults, function(
					Nette\Application\UI\Form $form,
					array $values
				) {


				}, function(int $stampId) {

						// Delete resp. toggle
						$this->collectionModel->toggleCollect($stampId, $this->user->getId());
						$this->flashMessage('Známka odebrána.', \Flash::INFO);
						$this->redirect('default');

				});

		});


	}

	public function createComponentPaginator(): Nette\Application\UI\Control {
		return new Paginator();
	}

	private function getFilter() {

		return [
			'search' => $this->getParameter('search', null),
		];

	}

}
