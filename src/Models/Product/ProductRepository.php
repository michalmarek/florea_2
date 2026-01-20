<?php declare(strict_types=1);

namespace Models\Product;

use Core\Database;
use Nette\Database\Table\Selection;

/**
 * ProductRepository
 *
 * Handles database access for Product entities.
 * Maps Czech database columns to English PHP properties.
 *
 * Database table: es_zbozi
 */
class ProductRepository
{
    /**
     * Standard columns for Product entity
     */
    private const COLUMNS = 'id, shop, seller_id, fl_kategorie, nazev, cenaFlorea, sklad, fl_zobrazovat';

    /**
     * Find product by ID
     *
     * @param int $id Product ID
     * @return Product|null Product entity or null if not found
     */
    public function findById(int $id): ?Product
    {
        $query = "
            SELECT " . self::COLUMNS . "
            FROM es_zbozi
            WHERE id = ?
        ";

        $row = Database::query($query, $id)->fetch();

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Get Selection for visible products in shop
     * Returns Selection for further manipulation (pagination, filtering, sorting)
     *
     * @param int $shopId Shop ID
     * @return Selection
     */
    public function getVisibleProductsSelection(int $shopId): Selection
    {
        return Database::table('es_zbozi')
            ->where('shop', $shopId)
            ->where('fl_zobrazovat', '1')
            ->order('nazev ASC');
    }

    /**
     * Get products for menu category
     * Returns products from base category + manually assigned products
     *
     * @param int $shopId Shop ID
     * @param array $menuCategoryIds Array of menu category IDs (parent + all descendants)
     * @return Selection
     */
    public function getProductsByMenuCategorySelection(
        int $shopId,
        array $menuCategoryIds
    ): Selection
    {
        // Collect all base_category_ids from menu categories
        $baseCategoryIds = Database::table('es_menu_categories')
            ->select('base_category_id')
            ->where('id', $menuCategoryIds)
            ->fetchPairs(null, 'base_category_id');

        // Collect all manually assigned product IDs from pivot
        $manualProductIds = Database::table('es_menu_category_products')
            ->select('product_id')
            ->where('menu_category_id', $menuCategoryIds)
            ->fetchPairs(null, 'product_id');

        // Merge all product criteria
        $productIds = [];

        // Products from base categories
        if (!empty($baseCategoryIds)) {
            $categoryProducts = Database::table('es_zbozi')
                ->select('id')
                ->where('shop', $shopId)
                ->where('fl_kategorie', $baseCategoryIds)
                ->where('fl_zobrazovat', '1')
                ->fetchPairs(null, 'id');

            $productIds = array_merge($productIds, $categoryProducts);
        }

        // Add manually assigned products
        if (!empty($manualProductIds)) {
            $productIds = array_merge($productIds, $manualProductIds);
        }

        // Remove duplicates
        $productIds = array_unique($productIds);

        // Final Selection with unique product IDs
        if (empty($productIds)) {
            // No products found - return empty selection
            return Database::table('es_zbozi')
                ->where('id', null);
        }

        return Database::table('es_zbozi')
            ->where('id', $productIds)
            ->where('shop', $shopId)
            ->where('fl_zobrazovat', '1')
            ->order('nazev ASC');
    }

    /**
     * Map database rows to Product entities
     *
     * @param iterable $rows Database rows from Selection
     * @return Product[]
     */
    public function mapRowsToEntities(iterable $rows): array
    {
        $products = [];
        foreach ($rows as $row) {
            $products[] = $this->mapToEntity($row);
        }
        return $products;
    }

    /**
     * Map database row to Product entity
     *
     * Converts Czech column names to English properties:
     * - nazev → name
     * - cenaFlorea → price
     * - sklad → stock
     * - fl_zobrazovat → visible
     *
     * @param object $row Database row from Nette Database
     * @return Product Product entity
     */
    private function mapToEntity(object $row): Product
    {
        return new Product(
            id: (int) $row->id,
            shopId: (int) $row->shop,
            sellerId: (int) $row->seller_id,
            categoryId: (int) $row->fl_kategorie,
            name: $row->nazev,
            price: (float) $row->cenaFlorea,
            stock: (float) $row->sklad,
            visible: $row->fl_zobrazovat === '1',
        );
    }
}