<?php declare(strict_types=1);

namespace Models\Category;

class BaseCategory
{
    public function __construct(
        public readonly int $id,
        public readonly int $shop_id,
        public readonly int $parent_id,
        public readonly string $photo,
        public readonly string $heurekaFeed,
        public readonly string $zboziFeed,
        public readonly string $googleFeed,
        public readonly ?array $parameterGroups,
        public readonly bool $visible,       // legacy
        public readonly int $sortOrder,
    ) {}
}