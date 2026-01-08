<?php

declare(strict_types=1);

namespace Core\Latte;

use Latte\Extension;

/**
 * IsActiveExtension - Registrace {isActive} makra
 *
 * Použití v BasePresenter:
 * $this->latte->addExtension(new IsActiveExtension);
 *
 * Použití v šablonách:
 * <a href="{link Home:default}" class="{isActive Home:default}">Domů</a>
 */
class IsActiveExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'isActive' => [IsActiveMacro::class, 'create'],
        ];
    }
}