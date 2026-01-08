<?php


declare(strict_types=1);

namespace Core\Latte;

use Latte\Extension;
use Core\Translator;

/**
 * TranslationExtension - Latte extension pro {_} překladové makro
 *
 * Registruje custom makro {_} do Latte šablon.
 * Umožňuje překládat texty pomocí Translator třídy.
 *
 * Makro používá výchozí text jako klíč:
 * - {_('Úvodní stránka')} - jednoduchý překlad
 * - {_('Máte %d zpráv', [$count])} - s parametry (sprintf)
 * - {_('Zdravím {name}', [name => $userName])} - s placeholdery
 *
 * Metody:
 * - getTags() - Vrací asociativní pole s definicí makra a jeho handlerem
 *
 * Použití v BasePresenter:
 * $this->latte->addExtension(new TranslationExtension);
 *
 * Použití v šablonách:
 * {_('Úvodní stránka')}
 * {_('O nás')}
 * {_('Máte {count} nových zpráv', [count => 5])}
 *
 * Výstup (pro en jazyk):
 * Homepage
 * About Us
 * You have 5 new messages
 *
 * @see TranslationMacro Pro implementaci samotného makra
 * @see Translator Pro logiku překládání
 */
class TranslationExtension extends Extension
{
    public function getTags(): array
    {
        return [
            '_' => [TranslationMacro::class, 'create'],
        ];
    }
}