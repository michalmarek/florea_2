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
        private MenuCategoryRepository $menuCategoryRepository,
        // Další repositories se přidají postupně podle potřeby:
        // private CustomerRepository $customerRepository,
        // private NewsletterRepository $newsletterRepository,
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
     * Get user menu data (login status, customer info)
     *
     * TODO: Implement when CustomerRepository is ready
     * Will provide: login status, customer name, account links
     *
     * @return array User menu data
     */
    public function getUserMenuData(): array
    {
        // Placeholder pro budoucnost
        return [
            'isLoggedIn' => false,
            'customerName' => null,
            'loginUrl' => '#', // TODO: proper route
            'accountUrl' => '#',
        ];
    }

    /**
     * Get footer data (footer menu, contact info, etc.)
     *
     * TODO: Implement based on requirements
     *
     * @return array Footer data
     */
    public function getFooterData(): array
    {
        // Placeholder
        return [
            'companyName' => $this->shopContext->getWebsiteName(),
            'year' => date('Y'),
        ];
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