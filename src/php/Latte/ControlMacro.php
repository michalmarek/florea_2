<?php

declare(strict_types=1);

namespace Core\Latte;

use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\Node;

/**
 * ControlMacro - Implementace {control} makra pro Latte
 *
 * Zpracovává syntax makra a generuje PHP kód pro vykreslení komponent (formulářů).
 * Funguje ve dvou fázích:
 * 1. Parsing (create) - Parsuje syntax {control componentName}
 * 2. Code generation (print) - Generuje PHP kód, který vykreslí komponentu
 *
 * Podporované formáty:
 * - {control contactForm} - Základní vykreslení
 * - {control $formName} - Dynamická hodnota
 *
 * Proces:
 * 1. Latte najde {control ...} v šabloně
 * 2. create() naparsuje název komponenty
 * 3. print() vygeneruje PHP kód který:
 *    - Získá komponentu z $this->global->uiPresenter->getComponent()
 *    - Vykreslí ji pomocí ->render()
 * 4. Pokud je komponenta Form, automaticky se vykreslí kompletní <form>
 *
 * Metody:
 * - create(Tag) - Statická metoda pro parsing makra (volá Latte)
 * - print(PrintContext) - Generuje PHP kód pro renderování
 * - getIterator() - Vrací child nodes pro Latte AST
 *
 * Vlastnosti:
 * - $componentName (Node) - Název komponenty (např. "contactForm")
 *
 * @see ControlExtension Pro registraci makra
 * @see BasePresenter::getComponent() Pro získání komponenty
 */
class ControlMacro extends StatementNode
{
    public Node $componentName;

    public static function create(Tag $tag): static
    {
        $tag->outputMode = $tag::OutputKeepIndentation;
        $tag->expectArguments();

        $node = new static;
        $node->componentName = $tag->parser->parseUnquotedStringOrExpression();

        return $node;
    }

    public function print(PrintContext $context): string
    {
        $componentNameCode = $this->componentName->print($context);

        return $context->format(
            '(function($name) { ' .
            '$component = $this->global->uiPresenter->getComponent($name); ' .
            'if ($component instanceof \Nette\Forms\Form) { ' .
            'echo $component->render(); ' .
            '} else { ' .
            'echo $component; ' .
            '} ' .
            '})(%raw) %line;',
            $componentNameCode,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->componentName;
    }
}