<?php

declare(strict_types=1);

namespace Core\Latte;

use Latte\Extension;

/**
 * ControlExtension - Latte extension pro {control} makro
 *
 * Registruje custom makro {control} do Latte šablon.
 * Umožňuje vykreslit komponenty (formuláře) ve stylu Nette Application.
 *
 * Použití v BasePresenter:
 * $this->latte->addExtension(new ControlExtension);
 *
 * Použití v šablonách:
 * {control contactForm}
 *
 * Výstup:
 * <form> ... </form> - kompletně vykreslený formulář
 *
 * @see ControlMacro Pro implementaci samotného makra
 */
class ControlExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'control' => [ControlMacro::class, 'create'],
        ];
    }
}