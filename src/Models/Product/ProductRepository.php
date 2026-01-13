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