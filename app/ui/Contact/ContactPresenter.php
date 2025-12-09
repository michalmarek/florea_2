<?php

declare(strict_types=1);

namespace App\UI\Contact;

use App\Presenters\BasePresenter;
use Core\Database;
use Core\Config;

class ContactPresenter extends BasePresenter
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
