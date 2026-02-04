<?php declare(strict_types=1);

namespace Models\Category;

use Core\Database;
use Nette\Database\Table\Selection;

/**
 * BaseCategoryRepository
 *
 * Handles database access for BaseCategory entities.
 * Maps Czech database columns to English PHP properties.
 *
 * Database table: fl_kategorie
 */
class BaseCategoryRepository
{
    /**
     * Standard columns for BaseCategory entity
     */
    private const COLUMNS = 'id, nadrazena, variantParameterGroup_id, foto, heurekaFeed, zboziFeed, googleFeed, parameterGroups, zobrazovat, poradi';

    /**
     * Find category by ID
     *
     * @param int $id Category ID
     * @return BaseCategory|null BaseCategory entity or null if not found
     */
    public function findById(int $id): ?BaseCategory
    {
        $query = "
            SELECT " . self::COLUMNS . "
            FROM fl_kategorie
            WHERE id = ?
        ";

        $row = Database::query($query, $id)->fetch();

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Get Selection for all visible base categories
     * Returns Selection for further manipulation (filtering, sorting)
     *
     * @return Selection
     */
    public function getAllCategoriesSelection(): Selection
    {
        return Database::table('fl_kategorie')
            ->where('zobrazovat', '1')
            ->order('poradi ASC');
    }

    /**
     * Get Selection for child categories of given parent
     *
     * @param int $parentId Parent category ID
     * @return Selection
     */
    public function getChildrenSelection(int $parentId): Selection
    {
        return Database::table('fl_kategorie')
            ->where('nadrazena', $parentId)
            ->where('zobrazovat', '1')
            ->order('poradi ASC');
    }

    /**
     * Map database rows to BaseCategory entities
     *
     * @param iterable $rows Database rows from Selection
     * @return BaseCategory[]
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
     * Map database row to BaseCategory entity
     *
     * Converts Czech column names to English properties:
     * - nadrazena → parentId
     * - foto → photo
     * - zobrazovat → visible
     * - poradi → position
     *
     * @param object $row Database row from Nette Database
     * @return BaseCategory BaseCategory entity
     */
    private function mapToEntity(object $row): BaseCategory
    {
        return new BaseCategory(
            id: (int) $row->id,
            parentId: $row->nadrazena > 0 ? (int) $row->nadrazena : null,
            variantParameterGroupId: $row->variantParameterGroup_id ? (int) $row->variantParameterGroup_id : null,
            photo: $row->foto ?? '',
            heurekaFeed: $row->heurekaFeed ?? '',
            zboziFeed: $row->zboziFeed ?? '',
            googleFeed: $row->googleFeed ?? '',
            visible: $row->zobrazovat === '1',
            position: (int) $row->poradi,
            parameterGroups: $row->parameterGroups,
        );
    }
}