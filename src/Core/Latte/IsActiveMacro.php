<?php
declare(strict_types=1);

namespace Core\Latte;

use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * IsActiveMacro - Kontrola aktivního odkazu v menu
 *
 * Použití:
 * {isActive Home:default} → 'active' nebo ''
 * {isActive Blog:*} → kontroluje jen presenter
 * {isActive Article:detail, id => 5} → kontroluje i parametry
 * {isActive Contact:default, class: 'current'} → vlastní CSS třída
 *
 * Výstup:
 * - Pokud je odkaz aktivní: vrátí 'active' (nebo custom třídu)
 * - Pokud není aktivní: vrátí prázdný string ''
 */
class IsActiveMacro extends StatementNode
{
    public Node $destination;
    public ?ArrayNode $args = null;

    public static function create(Tag $tag): static
    {
        $tag->outputMode = $tag::OutputKeepIndentation;
        $tag->expectArguments();

        $node = new static;

        // Parse destination (povinné)
        $node->destination = $tag->parser->parseUnquotedStringOrExpression();

        // Parse argumenty (volitelné)
        $stream = $tag->parser->stream;
        if ($stream->tryConsume(',')) {
            $node->args = $tag->parser->parseArguments();
        }

        return $node;
    }

    public function print(PrintContext $context): string
    {
        $destinationCode = $this->destination->print($context);
        $argsCode = $this->args ? $this->args->print($context) : '[]';

        return $context->format(
            'echo (function($dest, $args) { ' .

            // Výchozí CSS třída
            '$activeClass = $args["class"] ?? "active"; ' .

            // Parse destination
            '$parts = explode(":", $dest); ' .
            '$targetPresenter = ucfirst($parts[0]); ' .
            '$targetAction = $parts[1] ?? "default"; ' .

            // Aktuální presenter a action
            '$currentPresenter = $this->global->uiPresenter->getParam("presenter") ?? "Home"; ' .
            '$currentAction = $this->global->uiPresenter->getParam("action") ?? "default"; ' .

            // Kontrola presenteru
            'if ($targetPresenter !== $currentPresenter) return ""; ' .

            // Pokud je wildcard (*), ignoruj action
            'if ($targetAction !== "*" && $targetAction !== $currentAction) return ""; ' .

            // Kontrola parametrů (kromě lang a class)
            'foreach ($args as $key => $value) { ' .
            '    if ($key === "lang" || $key === "class") continue; ' .
            '    if ($this->global->uiPresenter->getParam($key) != $value) return ""; ' .
            '} ' .

            'return $activeClass; ' .
            '})(%raw, %raw) %line;',
            $destinationCode,
            $argsCode,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->destination;
        if ($this->args) {
            yield $this->args;
        }
    }
}