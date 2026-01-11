<?php

declare(strict_types=1);

namespace UI\Florea\ShopInfo;

use UI\Base\ShopInfo\ShopInfoPresenter as BaseShopInfoPresenter;

class ShopInfoPresenter extends BaseShopInfoPresenter
{
    /**
     * Florea-specific override
     * Přidá extra shop informace jen pro Florea
     */
    public function actionDefault(): void
    {
        // Zavolej base implementaci
        parent::actionDefault();

        // Přidej Florea-specific data
        $this->assign('extraInfo', 'Florea má speciální otevírací dobu o víkendech!');
        $this->assign('presenterOverride', 'Florea ShopInfo Presenter');
    }
}