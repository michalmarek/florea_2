<?php declare(strict_types=1);

namespace Models\Product;

use Core\Database;
use Models\Category\BaseCategoryRepository;
use Models\Parameter\ParameterGroup;
use Models\Parameter\ParameterGroupRepository;

class ProductVariantService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly BaseCategoryRepository $baseCategoryRepository,
        private readonly ParameterGroupRepository $parameterGroupRepository,
    ) {}

    /**
     * Get variant parameter group for product
     * Returns parameter that defines variants for this product's category
     */
    public function getVariantParameterGroup(Product $product): ?ParameterGroup
    {
        $category = $this->baseCategoryRepository->findById($product->categoryId);

        if (!$category || !$category->variantParameterGroupId) {
            return null;
        }

        return $this->parameterGroupRepository->findById($category->variantParameterGroupId);
    }

    /**
     * Get all variants of a product
     *
     * @return array Array of ['product' => Product, 'value' => mixed, 'label' => string]
     */
    public function getVariants(Product $product): array
    {
        // No group code = no variants
        if (!$product->groupCode) {
            return [];
        }

        // Get variant parameter
        $variantGroup = $this->getVariantParameterGroup($product);
        if (!$variantGroup) {
            return [];
        }

        // Find all products with same groupCode
        $rows = Database::table('es_zbozi')
            ->where('groupCode', $product->groupCode)
            ->where('fl_zobrazovat', '1')
            ->where('sklad > ?', 0)
            ->fetchAll();

        // Build variants array
        $variants = [];
        foreach ($rows as $row) {
            $variantProduct = $this->productRepository->mapToEntity($row);
            $value = $this->getParameterValue($variantProduct->id, $variantGroup->id);

            if ($value !== null) {
                $variants[] = [
                    'product' => $variantProduct,
                    'value' => $value,
                    'label' => $variantGroup->getLabel($value),
                ];
            }
        }

        // Sort by value
        usort($variants, fn($a, $b) => $a['value'] <=> $b['value']);

        return $variants;
    }

    /**
     * Get parameter value for product
     *
     * @return string|float|null
     */
    public function getParameterValue(int $productId, int $groupId): mixed
    {
        $param = Database::table('es_zboziParameters')
            ->where('product_id', $productId)
            ->where('group_id', $groupId)
            ->fetch();

        if (!$param) {
            return null;
        }

        // Item-based (číselník)
        if ($param->item_id) {
            $item = Database::table('es_parameterItems')
                ->where('id', $param->item_id)
                ->fetch();
            return $item ? $item->value : null;
        }

        // Free text
        if ($param->freeText) {
            return $param->freeText;
        }

        // Free integer/numeric
        if ($param->freeInteger !== null) {
            return (float) $param->freeInteger;
        }

        return null;
    }
}