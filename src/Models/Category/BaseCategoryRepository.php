<?php declare(strict_types=1);

namespace Models\Category;

use Nette\Database\Explorer;
use Nette\Database\Table\Selection;

class BaseCategoryRepository
{
    private const TABLE_NAME = 'fl_kategorie';

    public function __construct(
        private Explorer $database,
    ) {}

    /**
     * Get all visible base categories
     */
    public function getAllCategoriesSelection(): Selection
    {
        return $this->database->table(self::TABLE_NAME)
            ->order('poradi ASC');
    }

    /**
     * Get category by ID
     */
    public function getCategoryById(int $id): ?BaseCategory
    {
        $row = $this->database->table(self::TABLE_NAME)
            ->get($id);

        return $row ? $this->mapRowToEntity($row) : null;
    }

    /**
     * Get child categories for given parent
     */
    public function getChildCategoriesSelection(int $parentId): Selection
    {
        return $this->database->table(self::TABLE_NAME)
            ->where('nadrazena', $parentId)
            ->order('poradi ASC');
    }

    /**
     * Get root categories (parent_id = 0 or NULL)
     */
    public function getRootCategoriesSelection(): Selection
    {
        return $this->database->table(self::TABLE_NAME)
            ->where('nadrazena', 0)
            ->order('poradi ASC');
    }

    /**
     * Map database row to BaseCategory entity
     */
    private function mapRowToEntity($row): BaseCategory
    {
        return new BaseCategory(
            id: $row->id,
            shop_id: $row->shop,
            parent_id: $row->nadrazena,
            photo: $row->foto ?? '',
            heurekaFeed: $row->heurekaFeed ?? '',
            zboziFeed: $row->zboziFeed ?? '',
            googleFeed: $row->googleFeed ?? '',
            parameterGroups: $row->parameterGroups
                ? json_decode($row->parameterGroups, true)
                : null,
            visible: $row->zobrazovat === '1',
            sortOrder: $row->poradi,
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