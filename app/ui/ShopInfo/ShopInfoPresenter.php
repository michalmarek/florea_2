<?php

declare(strict_types=1);

namespace App\UI\ShopInfo;

use App\Presenters\BasePresenter;

class ShopInfoPresenter extends BasePresenter
{
    public function actionDefault(): void
    {
        // Shop data
        $this->assign('shopId', $this->shopContext->getId());
        $this->assign('shopTextId', $this->shopContext->getTextId());
        $this->assign('shopDomain', $this->shopContext->getDomain());
        $this->assign('shopEmail', $this->shopContext->getEmail());
        $this->assign('shopPhone', $this->shopContext->getPhoneNumber());

        // Seller data
        $seller = $this->shopContext->getSeller();
        $this->assign('companyName', $seller->getCompanyName());
        $this->assign('companyAddress', $seller->getFullAddress());
        $this->assign('vatNumber', $seller->getVatNumber());
    }

    public function renderDefault(): void
    {
        $this->render();
    }
}