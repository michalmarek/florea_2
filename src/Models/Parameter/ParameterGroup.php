<?php declare(strict_types=1);

namespace Models\Parameter;

/**
 * ParameterGroup Entity
 *
 * Represents a parameter group from es_parameterGroups table.
 * Defines parameter types (select from items, free text, or numeric).
 */
class ParameterGroup
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly bool $isFreeText,
        public readonly bool $isFreeInteger,
        public readonly ?string $units,
    ) {}

    /**
     * Check if parameter uses item selection (číselník)
     */
    public function isItemBased(): bool
    {
        return !$this->isFreeText && !$this->isFreeInteger;
    }

    /**
     * Check if parameter is numeric
     */
    public function isNumeric(): bool
    {
        return $this->isFreeInteger;
    }

    /**
     * Check if parameter is text
     */
    public function isText(): bool
    {
        return $this->isFreeText;
    }

    /**
     * Get formatted label with units
     */
    public function getLabel(mixed $value): string
    {
        if ($this->units) {
            return $value . ' ' . $this->units;
        }

        return (string) $value;
    }
}