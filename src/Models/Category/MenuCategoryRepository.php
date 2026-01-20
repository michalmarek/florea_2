<?php declare(strict_types=1);

namespace Models\Category;

use Core\Database;
use Nette\Database\Table\Selection;

/**
 * MenuCategoryRepository
 *
 * Handles database access for MenuCategory entities.
 * Shop-specific menu structure with hierarchical organization.
 *
 * Database table: es_menu_categories
 */
class MenuCategoryRepository
{
    /**
     * Standard columns for MenuCategory entity
     */
    private const COLUMNS = 'id, shop_id, parent_id, base_category_id, url, name, name_inflected, description, products_description, meta_title, meta_description, image, visible, position';

    /**
     * Find menu category by ID
     *
     * @param int $id Menu category ID
     * @return MenuCategory|null MenuCategory entity or null if not found
     */
    public function findById(int $id): ?MenuCategory
    {
        $query = "
            SELECT " . self::COLUMNS . "
            FROM es_menu_categories
            WHERE id = ?
        ";

        $row = Database::query($query, $id)->fetch();

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Find menu category by URL slug for specific shop
     *
     * @param int $shopId Shop ID
     * @param string $url URL slug
     * @return MenuCategory|null MenuCategory entity or null if not found
     */
    public function findByUrl(int $shopId, string $url): ?MenuCategory
    {
        $query = "
            SELECT " . self::COLUMNS . "
            FROM es_menu_categories
            WHERE shop_id = ? AND url = ?
        ";

        $row = Database::query($query, $shopId, $url)->fetch();

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Get Selection for all visible menu categories in shop
     * Returns Selection for further manipulation (filtering, sorting)
     *
     * @param int $shopId Shop ID
     * @return Selection
     */
    public function getVisibleCategoriesSelection(int $shopId): Selection
    {
        return Database::table('es_menu_categories')
            ->where('shop_id', $shopId)
            ->where('visible', 1)
            ->order('position ASC');
    }

    /**
     * Get Selection for root menu categories (top-level menu items)
     *
     * @param int $shopId Shop ID
     * @return Selection
     */
    public function getRootCategoriesSelection(int $shopId): Selection
    {
        return Database::table('es_menu_categories')
            ->where('shop_id', $shopId)
            ->where('parent_id IS NULL OR parent_id = ?', 0)
            ->where('visible', 1)
            ->order('position ASC');
    }

    /**
     * Get Selection for child categories of given parent
     *
     * @param int $parentId Parent category ID
     * @return Selection
     */
    public function getChildrenSelection(int $parentId): Selection
    {
        return Database::table('es_menu_categories')
            ->where('parent_id', $parentId)
            ->where('visible', 1)
            ->order('position ASC');
    }

    /**
     * Get all descendant category IDs (recursive)
     * Returns array of menu category IDs including the parent
     *
     * @param int $menuCategoryId Parent menu category ID
     * @return int[] Array of menu category IDs
     */
    public function getAllDescendantIds(int $menuCategoryId): array
    {
        $ids = [$menuCategoryId]; // Start with parent

        // Get direct children
        $children = $this->getChildrenSelection($menuCategoryId);

        foreach ($children as $child) {
            // Recursively get all descendants of this child
            $childIds = $this->getAllDescendantIds($child->id);
            $ids = array_merge($ids, $childIds);
        }

        return array_unique($ids);
    }

    /**
     * Get complete category tree for shop as nested array
     *
     * Returns hierarchical structure with children nested inside parents.
     * Useful for rendering complete menu trees.
     *
     * @param int $shopId Shop ID
     * @return array Nested array of MenuCategory entities with 'children' key
     */
    public function getCategoryTreeForShop(int $shopId): array
    {
        // Fetch all visible categories for shop
        $selection = $this->getVisibleCategoriesSelection($shopId);
        $categories = $this->mapRowsToEntities($selection);

        // Build lookup array by ID
        $lookup = [];
        $tree = [];

        foreach ($categories as $category) {
            $lookup[$category->id] = [
                'category' => $category,
                'children' => []
            ];
        }

        // Build tree structure
        foreach ($categories as $category) {
            if ($category->isRoot()) {
                // Root category - add to top level
                $tree[] = &$lookup[$category->id];
            } else {
                // Child category - add to parent's children
                if (isset($lookup[$category->parentId])) {
                    $lookup[$category->parentId]['children'][] = &$lookup[$category->id];
                }
            }
        }

        return $tree;
    }

    /**
     * Map database rows to MenuCategory entities
     *
     * @param iterable $rows Database rows from Selection
     * @return MenuCategory[]
     */
    public function mapRowsToEntities(iterable $rows): array
    {
        $categories = [];
        foreach ($rows as $row) {
            $categories[] = $this->mapToEntity($row);
        }
        return $categories;
    }

    /**
     * Map database row to MenuCategory entity
     *
     * @param object $row Database row from Nette Database
     * @return MenuCategory MenuCategory entity
     */
    private function mapToEntity(object $row): MenuCategory
    {
        return new MenuCategory(
            id: (int) $row->id,
            shopId: (int) $row->shop_id,
            parentId: $row->parent_id > 0 ? (int) $row->parent_id : null,
            baseCategoryId: (int) $row->base_category_id,
            url: $row->url ?? '',
            name: $row->name ?? '',
            nameInflected: $row->name_inflected ?? '',
            description: $row->description ?? '',
            productsDescription: $row->products_description ?? '',
            metaTitle: $row->meta_title ?? '',
            metaDescription: $row->meta_description ?? '',
            image: $row->image ?? '',
            visible: (bool) $row->visible,
            position: (int) $row->position,
        );
    }
}