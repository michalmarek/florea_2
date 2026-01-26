<?php declare(strict_types=1);

namespace UI\Base;

use Shop\ShopContext;
use Models\Category\MenuCategoryRepository;

/**
 * LayoutDataProvider
 *
 * Provides data for layout templates (menu, user info, newsletter, etc.)
 * Handles all common layout concerns separately from BasePresenter.
 * Can be extended by shop-specific providers for custom needs.
 */
class LayoutDataProvider
{
    /**
     * Cache for expensive operations (menu loading, etc.)
     */
    private array $cache = [];

    public function __construct(
        private ShopContext $shopContext,
        private MenuCategoryRepository $menuCategoryRepository
    ) {}

    /**
     * Get main menu data with hierarchical structure
     *
     * Returns root categories with their direct children (1 level deep).
     * Cached to avoid multiple database queries.
     *
     * @return array Array of ['category' => MenuCategory, 'children' => MenuCategory[]]
     */
    public function getMainMenuData(): array
    {
        if (!isset($this->cache['mainMenu'])) {
            $shopId = $this->shopContext->getId();
            $rootCategories = $this->menuCategoryRepository
                ->getRootCategoriesSelection($shopId);

            $menu = [];
            foreach ($rootCategories as $rootRow) {
                $root = $this->menuCategoryRepository->mapToEntity($rootRow);

                // Get direct children (1 level only for dropdown)
                $childrenRows = $this->menuCategoryRepository
                    ->getChildrenSelection($root->id);

                $menu[] = [
                    'category' => $root,
                    'children' => $this->menuCategoryRepository
                        ->mapRowsToEntities($childrenRows)
                ];
            }

            $this->cache['mainMenu'] = $menu;
        }

        return $this->cache['mainMenu'];
    }

    /**
     * Get footer data (footer menu, contact info, etc.)
     *
     * TODO: Implement based on requirements
     *
     * @return object Footer data
     */
    public function getFooterData(): object
    {
        $data = new \stdClass();

        $data->companyName = $this->shopContext->getWebsiteName();
        $data->year = date('Y');

        // Placeholder
        return $data;
    }

    /**
     * Get newsletter form data
     *
     * TODO: Implement when Newsletter functionality is ready
     *
     * @return array Newsletter data
     */
    public function getNewsletterData(): array
    {
        // Placeholder
        return [
            'enabled' => false,
        ];
    }
}