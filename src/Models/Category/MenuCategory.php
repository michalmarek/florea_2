<?php declare(strict_types=1);

namespace Models\Category;

class MenuCategory
{
    public function __construct(
        public readonly int $id,
        public readonly int $shop_id,
        public readonly ?int $parent_id,
        public readonly int $base_category_id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly int $sortOrder,
        public readonly bool $visible,
    ) {}
}