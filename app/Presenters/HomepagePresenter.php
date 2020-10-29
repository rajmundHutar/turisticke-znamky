<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\EditStampFormFactory;
use App\Forms\SearchForm;
use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use Nette;


final class HomepagePresenter extends Nette\Application\UI\Presenter {

	use InjectMenu,
		InjectStampsModel,
		InjectCollectionModel;

	/** @var @persistent */
	public $search;

	/** @var array */
	protected $userCollection;

	public function actionDefault() {

		$this->userCollection = $this->collectionModel->fetchByUser($this->user->getId());

	}

	public function renderDefault(bool $onlyCollected = null) {

		$filter = $this->getFilter();

		if ($onlyCollected && $this->userCollection) {
			$filter['ids'] = array_keys($this->userCollection);
		}
		$this->template->allStamps = $this->stampsModel->fetchAll($filter, 100);
		$this->template->collection = $this->userCollection;

	}

	public function actionFetchClosest(string $lat, string $lng) {

		dump($this->stampsModel->fetchClosest((float)$lat, (float)$lng));
		die;

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

	private function getFilter() {

		return [
			'search' => $this->getParameter('search', null),
		];

	}

}
