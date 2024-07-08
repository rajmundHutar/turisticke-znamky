<?php

namespace App\Presenters;

use App\Presenters\Traits\InjectMenu;
use App\Presenters\Traits\InjectTranslator;
use Nette\Application\UI\Presenter;

class CollectionStatsPresenter extends Presenter
{
	use InjectMenu;
	use InjectTranslator;

}
