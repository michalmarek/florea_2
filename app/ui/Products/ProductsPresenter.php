<?php

declare(strict_types=1);

namespace App\UI\Products;

use App\Presenters\BasePresenter;
use Core\Database;
use Core\Config;

/**
 * ProductsPresenter - Správa produktových stránek
 *
 * Obsluhuje:
 * - /produkty - seznam produktů
 * - /produkty/<slug> - detail produktu podle slugu
 *
 * Struktura souborů:
 * /app/ui/Products/ProductsPresenter.php (tento soubor)
 * /app/ui/Products/default.latte (seznam produktů)
 * /app/ui/Products/detail.latte (detail produktu)
 */
class ProductsPresenter extends BasePresenter
{
    /**
     * Akce - seznam produktů
     */
    public function actionDefault(): void
    {
        bdump($this);
    }

    /**
     * Render - seznam produktů
     */
    public function renderDefault(): void
    {
        $this->render();
    }

    /**
     * Akce - detail produktu
     */
    public function actionDetail(): void
    {
        // Získání slugu z URL parametru
        $slug = $this->getParam('slug');

        if (!$slug) {
            // Pokud slug chybí, redirect na seznam
            $this->redirect('Products:default');
        }

        $this->assign("slug", $slug);
    }

    /**
     * Render - detail produktu
     */
    public function renderDetail(): void
    {
        $this->render();
    }
}
