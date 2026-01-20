<?php declare(strict_types=1);

namespace UI\Base\Category;

use UI\Base\BasePresenter;
use Shop\ShopContext;
use Models\Category\MenuCategoryRepository;
use Models\Product\ProductRepository;

/**
 * CategoryPresenter
 *
 * Displays category detail with products
 */
class CategoryPresenter extends BasePresenter
{
    public function __construct(
        ShopContext $shopContext,
        private MenuCategoryRepository $menuCategoryRepository,
        private ProductRepository $productRepository
    ) {
        parent::__construct($shopContext);
    }

    public function actionDefault(string $slug, int $p = 1): void
    {
        // Find category by URL slug
        $category = $this->menuCategoryRepository->findByUrl(
            $this->shopContext->getId(),
            $slug
        );

        if (!$category) {
            http_response_code(404);
            echo "Kategorie nebyla nalezena";
            exit;
        }

        // Get direct child categories (subcategories)
        $childrenSelection = $this->menuCategoryRepository->getChildrenSelection($category->id);
        $childCategories = $this->menuCategoryRepository->mapRowsToEntities($childrenSelection);

        // Get all descendant menu category IDs
        $menuCategoryIds = $this->menuCategoryRepository->getAllDescendantIds($category->id);

        // Get products for this category
        $selection = $this->productRepository->getProductsByMenuCategorySelection(
            $this->shopContext->getId(),
            $menuCategoryIds
        );

        // Apply pagination (presenter logic)
        $perPage = 12;
        $selection->page($p, $perPage, $lastPage);

        // Map to entities
        $products = $this->productRepository->mapRowsToEntities($selection);

        // Get breadcrumbs (parent categories)
        $breadcrumbs = $this->buildBreadcrumbs($category);

        // Assign to template
        $this->assign('category', $category);
        $this->assign('childCategories', $childCategories);
        $this->assign('products', $products);
        $this->assign('breadcrumbs', $breadcrumbs);
        $this->assign('pagination', [
            'page' => $p,
            'lastPage' => $lastPage,
        ]);
    }

    /**
     * Render default template
     */
    public function renderDefault(): void
    {
        $this->render();
    }

    /**
     * Build breadcrumbs from category hierarchy
     *
     * Walks up the category tree from current to root
     * Returns array ordered from root to current
     */
    private function buildBreadcrumbs($category): array
    {
        $breadcrumbs = [];
        $current = $category;

        // Walk up the tree
        while ($current !== null) {
            // Add current category to beginning of array
            array_unshift($breadcrumbs, [
                'text' => $current->name,
                'link' => 'Category:default',
                'params' => ['slug' => $current->url]
            ]);

            // Move to parent
            if ($current->parentId !== null) {
                $current = $this->menuCategoryRepository->findById($current->parentId);
            } else {
                // Reached root
                $current = null;
            }
        }

        return $breadcrumbs;
    }
}