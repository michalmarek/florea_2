<?php declare(strict_types=1);

namespace Models\Category;

use Nette\Database\Explorer;
use Nette\Database\Table\Selection;

class MenuCategoryRepository
{
    private const TABLE_NAME = 'es_menu_categories';

    public function __construct(
        private Explorer $database,
    ) {}

    /**
     * Get all menu categories for specific shop
     */
    public function getMenuCategoriesForShopSelection(int $shopId): Selection
    {
        return $this->database->table(self::TABLE_NAME)
            ->where('shop_id', $shopId)
            ->where('visible', 1)
            ->order('sort_order ASC');
    }

    /**
     * Get menu category by ID
     */
    public function getMenuCategoryById(int $id): ?MenuCategory
    {
        $row = $this->database->table(self::TABLE_NAME)
            ->get($id);

        return $row ? $this->mapRowToEntity($row) : null;
    }

    /**
     * Get menu category by slug for specific shop
     */
    public function getMenuCategoryBySlug(int $shopId, string $slug): ?MenuCategory
    {
        $row = $this->database->table(self::TABLE_NAME)
            ->where('shop_id', $shopId)
            ->where('slug', $slug)
            ->fetch();

        return $row ? $this->mapRowToEntity($row) : null;
    }

    /**
     * Get child menu categories
     */
    public function getChildMenuCategoriesSelection(int $shopId, int $parentId): Selection
    {
        return $this->database->table(self::TABLE_NAME)
            ->where('shop_id', $shopId)
            ->where('parent_id', $parentId)
            ->where('visible', 1)
            ->order('sort_order ASC');
    }

    /**
     * Get root menu categories (parent_id IS NULL)
     */
    public function getRootMenuCategoriesSelection(int $shopId): Selection
    {
        return $this->database->table(self::TABLE_NAME)
            ->where('shop_id', $shopId)
            ->where('parent_id IS NULL')
            ->where('visible', 1)
            ->order('sort_order ASC');
    }

    /**
     * Get breadcrumbs from root to current category
     * Returns array [root, parent, ..., current]
     */
    public function getBreadcrumbs(int $categoryId): array
    {
        $breadcrumbs = [];
        $currentId = $categoryId;

        while ($currentId !== null) {
            $category = $this->getMenuCategoryById($currentId);

            if (!$category) {
                break;
            }

            // Přidáme na začátek pole (od rootu k current)
            array_unshift($breadcrumbs, $category);

            $currentId = $category->parent_id;
        }

        return $breadcrumbs;
    }

    /**
     * Get all descendant base category IDs recursively (for product filtering)
     * Returns array of base_category_ids including the category itself
     */
    public function getAllDescendantBaseCategoryIds(int $menuCategoryId): array
    {
        $menuIds = $this->getAllDescendantIds($menuCategoryId);

        $baseCategoryIds = $this->database->table(self::TABLE_NAME)
            ->where('id', $menuIds)
            ->fetchPairs('id', 'base_category_id');

        return array_unique(array_values($baseCategoryIds));
    }

    /**
     * Get all descendant menu IDs recursively (helper method)
     * Returns array of menu category IDs including the category itself
     */
    private function getAllDescendantIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $childIds = $this->getChildIds($categoryId);

        foreach ($childIds as $childId) {
            $ids = array_merge($ids, $this->getAllDescendantIds($childId));
        }

        return array_unique($ids);
    }

    /**
     * Get direct child IDs (helper for getAllDescendantIds)
     */
    private function getChildIds(int $parentId): array
    {
        return $this->database->table(self::TABLE_NAME)
            ->where('parent_id', $parentId)
            ->fetchPairs('id', 'id');
    }

    /**
     * Map database row to MenuCategory entity
     */
    private function mapRowToEntity($row): MenuCategory
    {
        return new MenuCategory(
            id: $row->id,
            shop_id: $row->shop_id,
            parent_id: $row->parent_id,
            base_category_id: $row->base_category_id,
            name: $row->name,
            slug: $row->slug,
            description: $row->description,
            sortOrder: $row->sort_order,
            visible: (bool) $row->visible,
        );
    }

    /**
     * Map multiple rows to entities
     */
    public function mapRowsToEntities(Selection $selection): array
    {
        $categories = [];
        foreach ($selection as $row) {
            $categories[] = $this->mapRowToEntity($row);
        }
        return $categories;
    }
}