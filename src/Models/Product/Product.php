<?php declare(strict_types=1);

namespace Models\Product;

/**
 * Product Entity
 *
 * Immutable value object representing a product from es_zbozi table.
 *
 * Uses hybrid approach:
 * - Public readonly: Simple data passthrough
 * - Private readonly + getter: Data with business logic
 */
class Product
{
    public function __construct(
        // === Public readonly (simple passthrough) ===
        public readonly int $id,
        public readonly int $shopId,
        public readonly int $sellerId,
        public readonly int $categoryId,
        public readonly string $name,
        public readonly float $stock,
        public readonly bool $visible,

        // === Private readonly (with business logic) ===
        private readonly float $price,  // cenaFlorea
    ) {}

    // === Getters with logic ===

    /**
     * Get product price
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * Get formatted price with currency
     */
    public function getFormattedPrice(): string
    {
        return number_format($this->price, 0, ',', ' ') . ' Kč';
    }

    // === Helper methods ===

    /**
     * Check if product is in stock
     */
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Check if product is available for purchase
     * (visible AND in stock)
     */
    public function isAvailable(): bool
    {
        return $this->visible && $this->isInStock();
    }
}