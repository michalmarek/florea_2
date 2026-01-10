<?php
declare(strict_types=1);

namespace Core;

use Latte\Engine;
use Nette\Http\IRequest;
use Nette\Http\RequestFactory;
use Nette\Routing\Router as NetteRouterInterface;
use Core\Router;
use Core\Container;
use Shop\ShopContext;

/**
 * Application - Hlavní aplikační třída
 *
 * Orchestruje celý životní cyklus HTTP požadavku:
 * 1. Vytvoří HTTP request z globálních proměnných
 * 2. Inicializuje router s definovanými routami
 * 3. Matchuje URL na konkrétní presenter a akci
 * 4. Vyčistí a validuje jazykový parametr
 * 5. Spustí presenter (action → render)
 * 6. Ošetřuje chyby (404, 500)
 *
 * DI Integration:
 * - Application dostává Container v konstruktoru
 * - Router se vytváří přes Container (autowiring ShopContext)
 * - Router se cachuje jako singleton
 * - Presentery se vytváří přes Container (autowiring dependencies)
 *
 * Použití:
 * - run() - Spustí aplikaci (volá se v bootstrap.php)
 * - getRouter() - Vrátí router instanci
 * - getHttpRequest() - Vrátí HTTP request
 * - getParams() - Vrátí parametry z matchnuté routy
 * - getCurrentLang() - Vrátí aktuální jazyk
 *
 * @example
 * $app = new Application;
 * $app->run();
 */

class Application
{
    private Container $container;
    private NetteRouterInterface $router;
    private IRequest $httpRequest;
    private array $params = [];
    private string $currentLang;
    private ShopContext $shopContext;

    public function __construct(Container $container)
    {
        $this->container = $container;

        // Vytvoření HTTP požadavku
        $requestFactory = new RequestFactory;
        $this->httpRequest = $requestFactory->fromGlobals();

        // Načtení ShopContext z DI Containeru
        $this->shopContext = $this->container->get(ShopContext::class);

        // Vytvoření routeru přes DI Container
        $routerFactory = $this->container->get(Router::class);
        $this->router = $routerFactory->createRouter();
    }

    /**
     * Spuštění aplikace
     */
    public function run(): void
    {
        try {
            // Match aktuální URL
            $this->params = $this->router->match($this->httpRequest);

            \Tracy\Debugger::barDump($this->httpRequest->getUrl(), 'Request URL');
            \Tracy\Debugger::barDump($this->params, 'Matched Params');

            if ($this->params === null) {
                // Žádná route nevyhovuje → 404
                $this->handleError(404, 'Stránka nebyla nalezena');
                return;
            }

            // Vyčištění a validace jazyka
            $this->cleanupLanguage();

            // Nastavení jazyka
            $this->currentLang = $this->params['lang'] ?? 'cs';
            $GLOBALS['currentLang'] = $this->currentLang;

            // Spuštění presenteru
            $this->runPresenter();

        } catch (\Throwable $e) {
            $this->handleError(500, 'Došlo k chybě na serveru', $e);
        }
    }

    /**
     * Vyčištění a validace jazykového parametru
     *
     * Pokud parametr 'lang' obsahuje neplatnou hodnotu (např. pattern 'en|de|sk'),
     * nahradí ji výchozím jazykem z konfigurace.
     */
    private function cleanupLanguage(): void
    {
        $lang = $this->params['lang'] ?? Config::get('app.languages.default', 'cs');
        $supportedLanguages = Config::get('app.languages.supported', ['cs']);

        // Pokud jazyk není v seznamu podporovaných, použij výchozí
        if (!in_array($lang, $supportedLanguages, true)) {
            $lang = Config::get('app.languages.default', 'cs');
        }

        $this->params['lang'] = $lang;
    }

    /**
     * Spuštění presenteru
     */
    private function runPresenter(): void
    {
        // Získá název presenteru z parametrů (např. 'Home')
        $presenter = ucfirst($this->params['presenter']);
        $action = $this->params['action'];

        // Vytvoří název třídy
        $presenterClass = "UI\\{$presenter}\\{$presenter}Presenter";

        if (!class_exists($presenterClass)) {
            $this->handleError(404, 'Presenter nebyl nalezen');
            return;
        }

        // Vytvoří instanci presenteru pomocí Containeru (autowiring)
        $presenterInstance = $this->container->get($presenterClass);
        // Nastaví routing kontext
        $presenterInstance->setContext($this->params, $this->router, $this->httpRequest);

        // Zavolá akci (např. actionDefault())
        $actionMethod = 'action' . ucfirst($action);

        if (!method_exists($presenterInstance, $actionMethod)) {
            $this->handleError(404, 'Akce nebyla nalezena');
            return;
        }

        // Spuštění akce
        $presenterInstance->$actionMethod();

        // Zavolá render metodu (např. renderDefault())
        $renderMethod = 'render' . ucfirst($action);
        if (method_exists($presenterInstance, $renderMethod)) {
            $presenterInstance->$renderMethod();
        } else {
            // Pokud není render metoda, pokusíme se vykreslit výchozí template
            if (method_exists($presenterInstance, 'render')) {
                $presenterInstance->render();
            }
        }
    }

    /**
     * Zpracování chyby
     */
    private function handleError(int $code, string $message, ?\Throwable $exception = null): void
    {
        http_response_code($code);

        // V development módu VŽDY hoď výjimku - Tracy ji zachytí a zobrazí
        if (Config::get('app.debugger.mode') === \Tracy\Debugger::Development) {
            if ($exception) {
                throw $exception; // Tracy tohle zachytí a krásně zobrazí
            }
            throw new \Exception($message, $code);
        }

        // V produkci zobraz vlastní error stránku
        $errorTemplate = __DIR__ . "/../app/ui/Error/{$code}.latte";

        if (file_exists($errorTemplate)) {
            $latte = new Engine;

            $latte->setTempDirectory(Config::get('app.latte.tempDirectory'));

            $latte->render($errorTemplate, [
                'code' => $code,
                'message' => $message,
                'lang' => $this->currentLang ?? Config::get('app.languages.default', 'cs'),
                'siteName' => $this->shopContext->getWebsiteName(),
                'shopContext' => $this->shopContext,
            ]);
        } else {
            echo "<h1>Error {$code}</h1>";
            echo "<p>{$message}</p>";
        }
    }

    /**
     * Získání routeru
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Získání HTTP požadavku
     */
    public function getHttpRequest(): IRequest
    {
        return $this->httpRequest;
    }

    /**
     * Získání parametrů z routy
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Získání aktuálního jazyka
     */
    public function getCurrentLang(): string
    {
        return $this->currentLang;
    }

    /**
     * Získání aktuálního shop contextu
     */
    public function getShopContext(): ShopContext
    {
        return $this->shopContext;
    }
}