<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Helpers\GPS;
use App\Helpers\MapyConfig;
use App\Helpers\MapyLinks;
use App\Models\Traits\InjectCollectionModel;
use App\Models\Traits\InjectStampsModel;
use App\Presenters\Traits\CollectStamps;
use App\Presenters\Traits\InjectMenu;
use App\Presenters\Traits\InjectTranslator;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;

class StampPresenter extends Presenter
{

	use InjectTranslator;
	use InjectMenu;
	use InjectStampsModel;
	use InjectCollectionModel;
	use CollectStamps;

	public const NearbyStamps = 20;

	protected ?int $stampId = null;
	protected ?ActiveRow $stamp;

	private string $mapyApiKey = '';

	public function injectMapyConfig(MapyConfig $mapyConfig): void
	{
		$this->mapyApiKey = $mapyConfig->apiKey;
	}

	public function actionDefault(?string $id = null): void
	{
		$this->stampId = (int) $id;
		$this->stamp = $this->stampsModel->fetch($this->stampId);
		if (!$this->stamp) {
			$this->error();
		}
		$this->userCollection = $this->collectionModel->fetchByUser($this->user->getId());
	}

	protected function redirectAfterCollectionChange(int $id): void
	{
		$this->redirect($id === $this->stampId ? 'this' : "this#stamp-{$id}");
	}

	protected function redrawAfterCollectionChange(int $id): void
	{
		if ($id === $this->stampId) {
			$this->redrawControl('detailCollection');
		} else {
			$this->redrawControl('stamps');
			$this->redrawControl("stamp-{$id}");
		}
	}

	public function renderDefault(): void
	{

		$this->template->stamp = $this->stamp;
		$this->template->mapyApiKey = $this->mapyApiKey;
		$this->template->closest = $this->stampsModel->fetchClosest(
			(float) $this->stamp['lat'],
			(float) $this->stamp['lng'],
			self::NearbyStamps,
			[$this->stampId]
		);
		$this->template->salespoints = $salespoints = $this->stampsModel->fetchSalespoints($this->stampId);
		$this->template->salespointLocations = array_map($this->salespointLocation(...), $salespoints);
		$this->template->collection = $this->userCollection;
	}

	/**
	 * Distance from the stamp and the navigation link of a sales point; a system row (e.g. "sale suspended") is no place.
	 * @return array{distance: ?string, navigate: ?string}
	 */
	private function salespointLocation(ActiveRow $salespoint): array
	{
		if ($salespoint['system']) {
			return ['distance' => null, 'navigate' => null];
		}

		$lat = GPS::coordinate($salespoint['lat']);
		$lng = GPS::coordinate($salespoint['lng']);
		if ($lat === null || $lng === null) {
			return ['distance' => null, 'navigate' => MapyLinks::search($salespoint['name'])];
		}

		$stampLat = GPS::coordinate($this->stamp['lat']);
		$stampLng = GPS::coordinate($this->stamp['lng']);
		return [
			'distance' => $stampLat === null || $stampLng === null ? null : GPS::prettyDistance(GPS::gpsDistance($stampLat, $stampLng, $lat, $lng)),
			'navigate' => MapyLinks::route($lat, $lng),
		];
	}

}
