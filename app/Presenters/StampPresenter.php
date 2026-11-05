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
		// nearest first, the ones without a distance keep the feed order at the end; the template shows the first one
		// and collapses the others (#80)
		$items = array_values(array_map(fn(ActiveRow $salespoint) => ['salespoint' => $salespoint] + $this->salespointLocation($salespoint), $salespoints));
		usort($items, fn(array $a, array $b) => [$a['meters'] === null, $a['meters']] <=> [$b['meters'] === null, $b['meters']]);
		$this->template->salespointItems = $items;
		$this->template->collection = $this->userCollection;
	}

	/**
	 * Distance from the stamp and the navigation link of a sales point; a system row (e.g. "sale suspended") is no place.
	 * @return array{distance: ?string, meters: ?float, navigate: ?string}
	 */
	private function salespointLocation(ActiveRow $salespoint): array
	{
		if ($salespoint['system']) {
			return ['distance' => null, 'meters' => null, 'navigate' => null];
		}

		$lat = GPS::coordinate($salespoint['lat']);
		$lng = GPS::coordinate($salespoint['lng']);
		if ($lat === null || $lng === null) {
			return ['distance' => null, 'meters' => null, 'navigate' => MapyLinks::search($salespoint['name'])];
		}

		$stampLat = GPS::coordinate($this->stamp['lat']);
		$stampLng = GPS::coordinate($this->stamp['lng']);
		$meters = $stampLat === null || $stampLng === null ? null : GPS::gpsDistance($stampLat, $stampLng, $lat, $lng);
		return [
			'distance' => $meters === null ? null : GPS::prettyDistance($meters),
			'meters' => $meters,
			'navigate' => MapyLinks::route($lat, $lng),
		];
	}

}
