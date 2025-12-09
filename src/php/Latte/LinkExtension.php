<?php

declare(strict_types=1);

namespace Core\Latte;

use Latte\Extension;

/**
 * LinkExtension - Latte extension pro {link} makro
 *
 * Registruje custom makro {link} do Latte šablon.
 * Umožňuje generovat odkazy ve stylu Nette Application:
 * {link Presenter:action, param1 => value1}
 *
 * Makro funguje jako bridge mezi šablonou a BasePresenter->link() metodou.
 *
 * Metody:
 * - getTags() - Vrací asociativní pole s definicí makra a jeho handlerem
 *
 * Použití v BasePresenter:
 * $this->latte->addExtension(new LinkExtension);
 *
 * Použití v šablonách:
 * {link Home:default}
 * {link Blog:default, slug => 'article'}
 * {link Article:detail, id => 123, slug => 'nazev'}
 *
 * @see LinkMacro Pro implementaci samotného makra
 */

class LinkExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'link' => [LinkMacro::class, 'create'],
        ];
    }
}