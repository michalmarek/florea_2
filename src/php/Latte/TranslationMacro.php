<?php

declare(strict_types=1);

namespace Core\Latte;

use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\Node;

/**
 * TranslationMacro - Implementace {_} makra pro překlady
 *
 * Zpracovává syntax makra a generuje PHP kód pro překlady.
 * Funguje ve dvou fázích:
 * 1. Parsing (create) - Parsuje syntax {_('text', [params])}
 * 2. Code generation (print) - Generuje PHP kód, který volá Translator::translate()
 *
 * Podporované formáty:
 * - {_('Úvodní stránka')} - Jednoduchý překlad
 * - {_('Máte %d zpráv', [5])} - S parametry (sprintf)
 * - {_('Zdravím {name}', [name => 'Jan'])} - S named parametry
 * - {_($dynamicText)} - Dynamická hodnota
 *
 * Proces:
 * 1. Latte najde {_ ...} v šabloně
 * 2. create() naparsuje text a volitelné parametry do AST nodů
 * 3. print() vygeneruje PHP kód který:
 *    - Volá Core\Translator::translate($message, $lang, $params)
 *    - Automaticky detekuje jazyk z $GLOBALS['currentLang']
 *    - Escapuje výstup pro bezpečnost
 * 4. Při renderingu se vrátí přeložený text nebo originál
 *
 * Metody:
 * - create(Tag) - Statická metoda pro parsing makra (volá Latte)
 * - print(PrintContext) - Generuje PHP kód pro renderování
 * - getIterator() - Vrací child nodes pro Latte AST
 *
 * Vlastnosti:
 * - $message (Node) - Text k překladu
 * - $params (ArrayNode|null) - Volitelné parametry pro nahrazení
 *
 * @see TranslationExtension Pro registraci makra
 * @see Translator Pro logiku překládání
 */
class TranslationMacro extends StatementNode
{
    public Node $message;
    public ?ArrayNode $params = null;

    public static function create(Tag $tag): static
    {
        $tag->outputMode = $tag::OutputKeepIndentation;
        $tag->expectArguments();

        $node = new static;

        // Parse zprávu (povinné)
        $node->message = $tag->parser->parseUnquotedStringOrExpression();

        // Parse parametry (volitelné)
        $stream = $tag->parser->stream;
        if ($stream->tryConsume(',')) {
            $node->params = $tag->parser->parseArguments();
        }

        return $node;
    }

    public function print(PrintContext $context): string
    {
        // Získáme kód pro zprávu
        $messageCode = $this->message->print($context);

        // Parametry - pokud nejsou, dáme prázdné pole
        $paramsCode = $this->params
            ? $this->params->print($context)
            : '[]';

        // Vygenerujeme PHP kód pro překlad
        // %escape automaticky escapuje výstup
        // %raw vloží raw PHP kód
        return $context->format(
            'echo str_replace(
        ["&lt;br&gt;", "&lt;strong&gt;", "&lt;/strong&gt;", "&lt;em&gt;", "&lt;/em&gt;"],
        ["<br>", "<strong>", "</strong>", "<em>", "</em>"],
        %escape(\Core\Translator::translate(%raw, null, %raw))
    ) %line;',
            $messageCode,
            $paramsCode,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->message;
        if ($this->params) {
            yield $this->params;
        }
    }
}