<?php

declare(strict_types=1);

namespace Core;

use Core\Config;
use Shop\ShopContext;
use Nette\Routing\RouteList;

/**
 * RouterFactory - Továrna na vytváření routeru
 *
 * Vytváří router s routami definovanými v konfiguraci.
 * Načítá jazykové nastavení a všechny routes z config.php.
 *
 * Proces vytváření routeru:
 * 1. Načte jazyky z konfigurace (výchozí + podporované)
 * 2. Vytvoří RouteList
 * 3. Přidá custom routes z config.php (blog, gallery, shop...)
 * 4. Přidá fallback routes (/<presenter>/<action>)
 *
 * Jazykový systém:
 * - Výchozí jazyk (např. 'cs') → URL bez prefixu: /blog
 * - Ostatní jazyky → URL s prefixem: /en/blog, /de/blog
 * - Pattern: [<lang=en|de|sk>/] - hranaté závorky = volitelné
 *
 * Shop-Aware:
 * - RouterFactory dostává ShopContext přes constructor injection
 * - Automaticky načte routy pro aktuální shop z config/routes/{shopTextId}.php
 * - Každý shop má své vlastní routy (žádná hierarchie/mergování)

 * Metody:
 * - createRouter() - Vytvoří a vrátí RouteList s definovanými routami
 * - setSupportedLanguages(array) - Nastaví podporované jazyky
 * - setDefaultLanguage(string) - Nastaví výchozí jazyk
 * - getSupportedLanguages() - Vrátí všechny jazyky včetně výchozího
 * - getDefaultLanguage() - Vrátí výchozí jazyk
 * - isLanguageSupported(string) - Zkontroluje podporu jazyka
 *
 * @example
 * $factory = new RouterFactory();
 * $router = $factory->createRouter();
 */
class Router
{
    private array $supportedLanguages;
    private string $defaultLanguage;
    private array $routes = [];

    public function __construct(
        private ShopContext $shopContext
    )
    {
        // Načtení jazyků z konfigurace
        $this->defaultLanguage = Config::get('app.languages.default', 'cs');
        $this->supportedLanguages = Config::get('app.languages.supported', ['en']);

        // Načtení routes z externího souboru
        $this->loadRoutes();
    }

    /**
     * Načte routes z externího souboru pro aktuální shop
     */
    private function loadRoutes(): void
    {
        $shopTextId = $this->shopContext->getTextId();
        $configDir = Config::get('app.paths.config');
        $routesFile = $configDir . "/routes/{$shopTextId}.php";

        if (!file_exists($routesFile)) {
            throw new \RuntimeException(
                "Routes file not found for shop '{$shopTextId}': {$routesFile}"
            );
        }

        $routes = require $routesFile;

        if (!is_array($routes)) {
            throw new \RuntimeException(
                "Soubor routes.php musí vracet pole routes, vrátil: " . gettype($routes)
            );
        }

        $this->routes = $routes;
    }

    /**
     * Vytvoří router s definovanými routami
     */
    public function createRouter(): RouteList
    {
        $router = new RouteList;

        // Přidání custom rout z konfigurace
        $this->addCustomRoutes($router);

        // Přidání výchozích rout (fallback)
        $this->addDefaultRoutes($router);

        return $router;
    }

    /**
     * Přidání custom rout z konfigurace
     */
    private function addCustomRoutes(RouteList $router): void
    {
        foreach ($this->routes as $route) {
            $presenter = $route['presenter'] ?? 'Home';
            $action = $route['action'] ?? 'default';
            $params = $route['params'] ?? [];

            // Podpora pro lokalizované URL slugy
            if (isset($route['patterns']) && is_array($route['patterns'])) {
                // Pro každý jazyk vytvoř samostatnou routu
                foreach ($route['patterns'] as $lang => $pattern) {
                    if (empty($pattern)) {
                        continue;
                    }

                    // Pokud je to výchozí jazyk, route nemá prefix
                    if ($lang === $this->defaultLanguage) {
                        $router->addRoute($pattern, array_merge([
                            'presenter' => $presenter,
                            'action' => $action,
                            'lang' => $lang,
                        ], $params));
                    } else {
                        // Ostatní jazyky mají prefix
                        $router->addRoute("{$lang}/{$pattern}", array_merge([
                            'presenter' => $presenter,
                            'action' => $action,
                            'lang' => $lang,
                        ], $params));
                    }
                }
            }
            // Zpětná kompatibilita - starý způsob s jedním pattern pro všechny jazyky
            elseif (isset($route['pattern'])) {
                $pattern = $route['pattern'];
                if (!empty($pattern)) {
                    $langPattern = $this->getLangPattern();
                    $router->addRoute("[<lang={$langPattern}>/]{$pattern}", array_merge([
                        'presenter' => $presenter,
                        'action' => $action,
                        'lang' => $this->defaultLanguage,
                    ], $params));
                }
            }
        }
    }

    /**
     * Přidání výchozích rout (fallback)
     */
    private function addDefaultRoutes(RouteList $router): void
    {
        $langPattern = $this->getLangPattern();

        // Obecná route pro presenter/action/id
        $router->addRoute("[<lang={$langPattern}>/]<presenter>/<action>[/<id>]", [
            'presenter' => 'Home',
            'action' => 'default',
            'lang' => $this->defaultLanguage,
        ]);

        // Homepage
        $router->addRoute("[<lang={$langPattern}>/]", [
            'presenter' => 'Home',
            'action' => 'default',
            'lang' => $this->defaultLanguage,
        ]);
    }

    /**
     * Vytvoří pattern pro podporované jazyky
     */
    private function getLangPattern(): string
    {
        return implode('|', $this->supportedLanguages);
    }

    /**
     * Nastavení podporovaných jazyků
     */
    public function setSupportedLanguages(array $languages): self
    {
        $this->supportedLanguages = $languages;
        return $this;
    }

    /**
     * Nastavení výchozího jazyka
     */
    public function setDefaultLanguage(string $language): self
    {
        $this->defaultLanguage = $language;
        return $this;
    }

    /**
     * Získání podporovaných jazyků
     */
    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }

    /**
     * Získání výchozího jazyka
     */
    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    /**
     * Kontrola, zda je jazyk podporován
     */
    public function isLanguageSupported(string $lang): bool
    {
        return in_array($lang, $this->getSupportedLanguages(), true);
    }

    /**
     * Získání načtených routes (pro debugging)
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}