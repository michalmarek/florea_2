<?php

declare(strict_types=1);

namespace App\UI\Order;

use App\Presenters\BasePresenter;
use Core\Database;
use Core\Config;

class OrderPresenter extends BasePresenter
{
    /**
     * Akce
     */
    public function actionDefault(): void
    {
    }

    /**
     * Render homepage
     */
    public function renderDefault(): void
    {
        $this->render();
    }
}
