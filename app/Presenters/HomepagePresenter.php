<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\EditStampForm;
use App\Forms\FilterForm;
use App\Helpers\Paginator;
use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\InjectFormFactory;
use App\Presenters\Traits\InjectTranslator;
use Nette;

final class HomepagePresenter extends Nette\Application\UI\Presenter {

	use InjectMenu,
		InjectFormFactory,
		InjectTranslator,
		InjectStampsModel,
		InjectCollectionModel;

	private const StampsPerPage = 200;

	/** @persistent  */
	public ?string $search = '';
	/** @persistent  */
	public ?string $sort = null;
	/** @persistent  */
	public ?string $label = null;

	protected array $userCollection = [];
	protected array $closest = [];

	public function actionDefault() {

		$this->userCollection = $this->collectionModel->fetchByUser($this->user->getId());

	}

	public function actionNearby(?string $lat = null, ?string $lng = null) {

		if ($lat && $lng) {
			$this->closest = $this->stampsModel->fetchClosest((float) $lat, (float) $lng, 20);
		}

	}


	public function handleToggleCollect(int $id) {

		if ($this->user->isLoggedIn()) {
			$this->collectionModel->toggleCollect($id, $this->user->getId());
			$this->redirect("this#stamp-{$id}", $this->getFilter());
		}

	}

	public function renderDefault(bool $onlyCollected = null): void {

		$filter = $this->getFilter();

		if ($onlyCollected && $this->userCollection) {
			$filter['ids'] = array_keys($this->userCollection);
		}
		$this['filterForm']->setDefaults($filter);

		/** @var Paginator $paginator */
		$paginator = $this['paginator'];
		$paginator->setItemsPerPage(self::StampsPerPage);
		$paginator->setItemCount($this->stampsModel->count($filter));

		$this->template->stamps = $this->stampsModel->search(
			$filter,
			$paginator->getLength(),
			$paginator->getOffset()
		);
		$this->template->collection = $this->userCollection;
		$this->template->filter = $filter;

	}

	public function renderNearby(): void {

		$this->template->stamps = $this->closest;
		$this->template->collection = $this->collectionModel->fetchByUser($this->user->getId());

		if ($this->isAjax()) {
			$this->redrawControl('stamps');
		}

	}

	public function renderDetail(int $id): void {

		$this->template->stamp = $stamp =  $this->stampsModel->fetch($id);
		$this->template->closest = $this->stampsModel->fetchClosest(
			(float) $stamp['lat'],
			(float) $stamp['lng'],
			8,
			[$stamp['id']]
		);
		$this->template->collection = $this->collectionModel->fetchByUser($this->user->getId());;

	}


	public function createComponentFilterForm() {

		$labels = $this->stampsModel->fetchLabels();
		return $this->formFactory->create(FilterForm::class, $labels);

	}

	public function createComponentEditStampForm() {

		return new Nette\Application\UI\Multiplier(function(string $stampId) {

				$defaults = [];
				if ($this->userCollection[$stampId] ?? null) {
					$defaults['date'] = $this->userCollection[$stampId]['date'];
					$defaults['comment'] = $this->userCollection[$stampId]['comment'];
				}

				return $this->formFactory->create(
					EditStampForm::class,
					(int) $stampId,
					$defaults,
					function(
						Nette\Application\UI\Form $form,
						array $values
					) {

						$stampId = $values['id'];
						unset($values['id']);
						$this->collectionModel->update(
							(int) $stampId,
							$this->user->getId(),
							$values
						);

				});

		});


	}

	public function createComponentPaginator(): Nette\Application\UI\Control {
		$paginator = new Paginator();
		$paginator->setTranslator($this->translator);
		return $paginator;
	}

	private function getFilter() {

		return [
			'search' => $this->getParameter('search'),
			'sort' => $this->getParameter('sort'),
			'label' => $this->getParameter('label'),
		];

	}

}
