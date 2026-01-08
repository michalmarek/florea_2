<?php declare(strict_types=1);

namespace Shop\Exception;

use RuntimeException;

class ShopNotFoundException extends RuntimeException
{
    public static function forDomain(string $domain): self
    {
        return new self(
            "Shop not found for domain: {$domain}. " .
            "Please check your domain_mapping configuration."
        );
    }

    public static function forTextId(string $textId): self
    {
        return new self(
            "Shop with textId '{$textId}' not found in database."
        );
    }
}
