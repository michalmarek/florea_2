<?php declare(strict_types=1);

namespace UI\Base\Product;

use UI\Base\BasePresenter;
use Models\Product\ProductRepository;
use Shop\ShopContext;

/**
 * ProductPresenter
 *
 * Handles product display (detail, listing)
 */
class ProductPresenter extends BasePresenter
{
    public function __construct(
        ShopContext $shopContext,
        private ProductRepository $productRepository
    ) {
        parent::__construct($shopContext);
    }

    /**
     * Product detail action
     *
     * URL: /product/detail/123
     *
     * @param int $id Product ID
     */
    public function actionDetail(int $id): void
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            // Produkt neexistuje → 404
            http_response_code(404);
            echo "Produkt nebyl nalezen";
            exit;
        }

        // Předání dat do template
        $this->assign('product', $product);
        $this->assign('pageTitle', $product->name);
    }

    /**
     * Render detail template
     */
    public function renderDetail(): void
    {
        $this->render();
    }


    /**
     * Product listing action
     *
     * URL: /produkty nebo /Product/default
     */
    public function actionDefault(int $p = 1): void
    {
        $shopId = $this->shopContext->getId();
        $perPage = 20;

        // Get Selection from repository
        $selection = $this->productRepository->getVisibleProductsSelection($shopId);

        // Apply pagination (presenter logic)
        $selection->page($p, $perPage, $lastPage);

        // Map to entities
        $products = $this->productRepository->mapRowsToEntities($selection);

        // Assign to template
        $this->assign('products', $products);
        $this->assign('pagination', [
            'page' => $p,
            'lastPage' => $lastPage,
        ]);
        $this->assign('pageTitle', 'Produkty');
    }

    /**
     * Render listing template
     */
    public function renderDefault(): void
    {
        $this->render();
    }
}