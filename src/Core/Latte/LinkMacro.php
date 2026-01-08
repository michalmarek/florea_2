<?php
declare(strict_types=1);

namespace Core\Latte;

use UI\Base\BasePresenter;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * LinkMacro - Implementace {link} makra pro Latte
 *
 * Zpracovává syntax makra a generuje PHP kód pro vytváření odkazů.
 * Funguje ve dvou fázích:
 * 1. Parsing (create) - Parsuje syntax {link Presenter:action, params}
 * 2. Code generation (print) - Generuje PHP kód, který volá BasePresenter->link()
 *
 * Podporované formáty:
 * - {link Home:default} - Základní odkaz
 * - {link Blog:default, slug => 'test'} - S parametry
 * - {link Article:detail, id => 123, slug => 'nazev'} - Více parametrů
 * - {link $destination, $params} - Dynamické hodnoty
 *
 * Proces:
 * 1. Latte najde {link ...} v šabloně
 * 2. create() naparsuje destination a parametry do AST nodů
 * 3. print() vygeneruje PHP: $this->global->uiPresenter->link(...)
 * 4. Při renderingu se volá BasePresenter->link() která vrátí URL
 * 5. URL se automaticky escapuje pro bezpečnost
 *
 * Metody:
 * - create(Tag) - Statická metoda pro parsing makra (volá Latte)
 * - print(PrintContext) - Generuje PHP kód pro renderování
 * - getIterator() - Vrací child nodes pro Latte AST
 *
 * Vlastnosti:
 * - $destination (Node) - Cíl odkazu (např. "Blog:default")
 * - $params (ArrayNode|null) - Volitelné parametry
 *
 * @see LinkExtension Pro registraci makra
 * @see BasePresenter::link() Pro generování URL
 */

class LinkMacro extends StatementNode
{
    public Node $destination;
    public ?ArrayNode $params = null;

    public static function create(Tag $tag): static
    {
        // Nastavení výstupu makra
        $tag->outputMode = $tag::OutputKeepIndentation;
        $tag->expectArguments();

        $node = new static;

        // Parse destination (povinné)
        $stream = $tag->parser->stream;
        $node->destination = $tag->parser->parseUnquotedStringOrExpression();

        // Parse parametry (volitelné)
        if ($stream->tryConsume(',')) {
            $node->params = $tag->parser->parseArguments();
        }

        return $node;
    }

    public function print(PrintContext $context): string
    {
        // Získáme destination jako hodnotu
        $destinationCode = $this->destination->print($context);

        // Sestavíme parametry
        $paramsCode = $this->params
            ? $this->params->print($context)
            : '[]';

        return $context->format(
            'echo %escape($this->global->uiPresenter->link(%raw, %raw)) %line;',
            $destinationCode,
            $paramsCode,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->destination;
        if ($this->params) {
            yield $this->params;
        }
    }
}