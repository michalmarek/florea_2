<?php

declare(strict_types=1);

namespace UI\Florea\Home;

use UI\Base\Home\HomePresenter as BaseHomePresenter;

class HomePresenter extends BaseHomePresenter
{
    /**
     * Florea-specific override
     * Přidá extra data jen pro Florea shop
     */
    public function actionDefault(): void
    {
        // Zavolej base implementaci (načte základní data)
        parent::actionDefault();

        // Přidej Florea-specific data
        $this->assign('floreaMessage', 'Toto je speciální zpráva jen pro Florea shop! 🌸');
        $this->assign('shopOverride', true);
    }
}