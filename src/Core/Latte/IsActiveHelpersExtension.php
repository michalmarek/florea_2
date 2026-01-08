<?php

declare(strict_types=1);

namespace Core\Latte;

use UI\Base\BasePresenter;
use Latte\Extension;

/**
 * IsActiveHelpersExtension - Helper funkce pro kontrolu aktivních odkazů
 *
 * Poskytuje funkce pro práci s navigací a podmínkami v šablonách:
 * - isActiveLink() - Plná kontrola aktivního odkazu (presenter + action + params)
 * - isPresenter() - Jednoduchá kontrola presenteru
 * - isAction() - Jednoduchá kontrola akce
 *
 * Použití v BasePresenter:
 * $this->latte->addExtension(new IsActiveHelpersExtension($this));
 *
 * Použití v šablonách:
 * {if isActiveLink('Blog:default')} ... {/if}
 * {if isPresenter('Home')} ... {/if}
 * {if isAction('archive')} ... {/if}
 */
class IsActiveHelpersExtension extends Extension
{
    public function __construct(
        private BasePresenter $presenter
    ) {}

    public function getFunctions(): array
    {
        return [
            'isActiveLink' => [$this, 'isActiveLink'],
            'isPresenter' => [$this, 'isPresenter'],
            'isAction' => [$this, 'isAction'],
        ];
    }

    /**
     * Kontrola aktivního odkazu s parametry
     */
    public function isActiveLink(string $destination, array $params = []): bool
    {
        // Parse destination
        $parts = explode(':', $destination);
        $targetPresenter = ucfirst($parts[0]);
        $targetAction = $parts[1] ?? 'default';

        // Aktuální presenter a action
        $currentPresenter = $this->presenter->getParam('presenter') ?? 'Home';
        $currentAction = $this->presenter->getParam('action') ?? 'default';

        // Kontrola presenteru
        if ($targetPresenter !== $currentPresenter) {
            return false;
        }

        // Pokud je wildcard (*), ignoruj action
        if ($targetAction !== '*' && $targetAction !== $currentAction) {
            return false;
        }

        // Kontrola parametrů (kromě lang a class)
        foreach ($params as $key => $value) {
            if ($key === 'lang' || $key === 'class') {
                continue;
            }
            if ($this->presenter->getParam($key) != $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Kontrola aktivního presenteru
     */
    public function isPresenter(string $presenter): bool
    {
        return ucfirst($presenter) === ($this->presenter->getParam('presenter') ?? 'Home');
    }

    /**
     * Kontrola aktivní akce
     */
    public function isAction(string $action): bool
    {
        return $action === ($this->presenter->getParam('action') ?? 'default');
    }
}