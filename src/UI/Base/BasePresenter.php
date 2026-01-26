<?php

declare(strict_types=1);

namespace UI\Base;

use Core\Config;
use Core\Container;
use Core\AssetMapper;
use Latte\Engine;
use Nette\Assets\Registry;
use Nette\Bridges\AssetsLatte\LatteExtension;
use Nette\Forms\Form;
use Nette\Http\IRequest;
use Nette\Routing\Router;
use Shop\ShopContext;
use Models\Customer\Customer;
use Models\Customer\CustomerAuthService;

/**
 * BasePresenter - Základní třída pro všechny presentery
 *
 * Abstraktní třída poskytující společnou funkcionalitu pro všechny presentery.
 * Každý presenter v aplikaci by měl dědit z této třídy.
 *
 * Poskytuje:
 * - Integraci s Latte šablonovacím systémem
 * - Generování odkazů pomocí routeru
 * - Přístup k URL parametrům
 * - Jazykovou podporu
 * - Redirecty
 * - Práci se šablonami
 * - Flash messages
 * - Podporu pro komponenty (formuláře)
 *
 * Lifecycle presenteru:
 * 1. __construct() - Inicializace (router, request, Latte)
 * 2. startup() - Hook pro inicializaci (nastavení základních proměnných)
 * 3. action*() - Načtení dat pro konkrétní akci
 * 4. render*() - Vykreslení šablony s daty
 *
 * Umístění souborů (co-located struktura):
 * /app/ui/Home/HomePresenter.php
 * /app/ui/Home/default.latte
 * /app/ui/Blog/BlogPresenter.php
 * /app/ui/Blog/default.latte
 * /app/ui/Blog/detail.latte
 *
 * Flash messages:
 * $this->flashMessage('Text zprávy', 'success'); // success, info, warning, error
 * $this->redirect('Home:default');
 *
 * Metody:
 * - startup() - Hook volaný po konstrukci (pro inicializaci)
 * - assign(string, mixed) - Přiřadí proměnnou do šablony
 * - link(string, array) - Vygeneruje URL odkaz
 * - render(string|null) - Vyrenderuje šablonu
 * - redirect(string, array, int) - HTTP redirect
 * - getParam(string, mixed) - Získá parametr z URL
 * - flashMessage(string, string) - Přidá flash zprávu
 *
 * @example
 * class HomePresenter extends BasePresenter {
 *     public function actionDefault(): void {
 *         $this->assign('title', 'Homepage');
 *     }
 *     public function renderDefault(): void {
 *         $this->render(); // → /app/ui/Home/default.latte
 *     }
 * }
 *
 * Práce s formuláři:
 * 1. Vytvoř metodu createComponent*Form() která vrací Form
 * 2. V šabloně použij {control *Form}
 * 3. Po submitu se zavolá onSuccess handler
 *
 * @example
 * class ContactPresenter extends BasePresenter {
 *      protected function createComponentContactForm(): Form {
 *          $form = FormFactory::create();
 *          $form->addText('name')->setRequired();
 *          $form->onSuccess[] = [$this, 'contactFormSucceeded'];
 *          return $form;
 *      }
 * }
 */

abstract class BasePresenter
{
    protected ?array $params = null;
    protected ?Router $router = null;
    protected ?IRequest $httpRequest = null;
    protected Engine $latte;
    protected array $templateVars = [];
    protected ?string $lang = null;
    private array $components = [];
    private ?Customer $customerCache = null;
    protected ShopContext $shopContext;
    protected LayoutDataProvider $layoutDataProvider;
    protected CustomerAuthService $customerAuthService;

    public function __construct(Container $container) {
        $this->shopContext = $container->get(ShopContext::class);
        $this->layoutDataProvider = $container->get(LayoutDataProvider::class);
        $this->customerAuthService = $container->get(CustomerAuthService::class);


        // Inicializace Latte
        $this->latte = new Engine;
        $this->latte->setTempDirectory(Config::get('app.latte.tempDirectory'));
        $this->latte->setAutoRefresh(Config::get('app.latte.autoRefresh', true));

        // Připojení Tracy panelu pro Latte
        if (Config::get('app.debugger.mode') === \Tracy\Debugger::Development) {
            $this->latte->addExtension(new \Latte\Bridges\Tracy\TracyExtension);
        }

        // Inicializace Nette Assets
        $registry = new Registry;
        $registry->addMapper(
            'default',
            new AssetMapper(
                Config::get('app.assets.url', '/assets'),
                Config::get('app.paths.assets')
            )
        );

        // Přidání Latte extension pro Assety
        $this->latte->addExtension(new LatteExtension($registry));

        // Přidání Translation extension
        $this->latte->addExtension(new \Core\Latte\TranslationExtension);

        // Přidání custom Link extension
        $this->latte->addExtension(new \Core\Latte\LinkExtension);

        // Přidání custom Icon extension
        $this->latte->addExtension(new \Core\Latte\IconExtension);

        // Přidání IsActive extension
        $this->latte->addExtension(new \Core\Latte\IsActiveExtension);

        // Přidání helper funkcí (isActiveLink, isPresenter, isAction)
        $this->latte->addExtension(new \Core\Latte\IsActiveHelpersExtension($this));

        // Přidání custom Control extension pro formuláře
        $this->latte->addExtension(new \Core\Latte\ControlExtension);

        // Přidání Forms extension pro n:name makra
        $this->latte->addExtension(new \Nette\Bridges\FormsLatte\FormsExtension);

        $this->latte->addFunction('t', function (string $key): string {
            return \Core\Translator::translate($key);
        });

        // Přidání presenteru jako providera pro {link} makro
        $this->latte->addProvider('uiPresenter', $this);
        $this->latte->addProvider('assetRegistry', $registry);

        // Přidání filtru pro generování linků (backup možnost)
        $this->latte->addFilter('link', function (string $destination, array $params = []): string {
            return $this->link($destination, $params);
        });
    }

    /**
     * Nastavení routing kontextu
     *
     * Volá se z Application po vytvoření presenteru.
     * Předává routing dependencies (params, router, httpRequest).
     */
    public function setContext(array $params, Router $router, IRequest $httpRequest): void
    {
        $this->params = $params;
        $this->router = $router;
        $this->httpRequest = $httpRequest;
        $this->lang = $params['lang'] ?? Config::get('languages.default', 'cs');

        // Teď až zavoláme startup
        $this->startup();
    }

    /**
     * Startup metoda - volá se před akcí
     */
    protected function startup(): void
    {
        // Ochrana - startup se volá až po setContext()
        if ($this->params === null || $this->router === null || $this->httpRequest === null) {
            throw new \RuntimeException('Call setContext() before startup()');
        }

        // Nastavení základních proměnných pro všechny template
        $this->templateVars['lang'] = $this->lang;
        $this->templateVars['basePath'] = $this->httpRequest->getUrl()->getBasePath();

        $this->templateVars['siteName'] = $this->shopContext->getWebsiteName();
        $this->templateVars['shopContext'] = $this->shopContext;
        $this->templateVars['seller'] = $this->shopContext->getSeller();

        $this->templateVars['config'] = Config::get('app');

        // Načtení flash messages
        $this->templateVars['flashes'] = $this->getFlashMessages();

        // Nastavení layoutu pro všechny template
        $this->templateVars['layoutPath'] = $this->getLayoutPath();

        // Layout data provider for menus, etc.
        $this->templateVars['layoutData'] = [
            'mainMenu' => $this->layoutDataProvider->getMainMenuData(),
            'footer' => $this->layoutDataProvider->getFooterData(),
        ];

        // Customer session data (no SQL)
        $this->assign('customer', $this->customerAuthService->createViewModel());

        // Přidání reference na presenter do template (pro {link} makro)
        $this->templateVars['_presenter'] = $this;
    }

    /**
     * Přidá flash zprávu
     *
     * @param string $message Text zprávy
     * @param string $type Typ zprávy: success, info, warning, error (odpovídá Bootstrap alert třídám)
     */
    protected function flashMessage(string $message, string $type = 'info'): void
    {

        if (!isset($_SESSION['_flashes'])) {
            $_SESSION['_flashes'] = [];
        }

        $_SESSION['_flashes'][] = [
            'message' => $message,
            'type' => $type,
        ];
    }

    /**
     * Získá všechny flash zprávy a vymaže je ze session
     *
     * @return object[] Pole flash message objektů
     */
    private function getFlashMessages(): array
    {
        $flashes = $_SESSION['_flashes'] ?? [];

        // Vymazání flash zpráv ze session (zobrazují se jen jednou)
        unset($_SESSION['_flashes']);

        // Převést na objekty
        return array_map(function($flash) {
            $obj = new \stdClass();
            $obj->message = $flash['message'];
            $obj->type = $flash['type'];
            return $obj;
        }, $flashes);
    }

    /**
     * Získání komponenty (např. formuláře)
     *
     * Automaticky vytvoří komponentu voláním createComponent*() metody.
     * Komponenta se cachuje, takže se vytvoří pouze jednou.
     */
    public function getComponent(string $name): mixed
    {
        if (!isset($this->components[$name])) {
            $method = 'createComponent' . ucfirst($name);

            if (!method_exists($this, $method)) {
                throw new \RuntimeException("Metoda {$method}() neexistuje v " . get_class($this));
            }

            $this->components[$name] = $this->$method();

            // Pokud je to formulář, zpracuj submit
            if ($this->components[$name] instanceof Form) {
                $this->processForm($this->components[$name]);
            }
        }

        return $this->components[$name];
    }

    /**
     * Zpracování formuláře
     *
     * Nastaví action URL a zpracuje odeslání formuláře.
     */
    private function processForm(Form $form): void
    {
        // Nastavení action URL (kam se formulář odešle)
        $form->setAction($this->httpRequest->getUrl()->getPath());

        // Zpracování odeslání formuláře
        if ($this->httpRequest->getMethod() === 'POST') {
            $form->fireEvents();
        }
    }

    /**
     * Přiřazení proměnné do templatu
     */
    protected function assign(string $name, mixed $value): void
    {
        $this->templateVars[$name] = $value;
    }

    /**
     * Generování odkazu
     */
    public function link(string $destination, array $params = []): string
    {
        // Parse destination (Presenter:action)
        [$presenter, $action] = $this->parseDestination($destination);

        // Přidání jazyka do parametrů
        $defaultLang = Config::get('languages.default', 'cs');
        if (!isset($params['lang']) && $this->lang !== $defaultLang) {
            $params['lang'] = $this->lang;
        }

        $params['presenter'] = $presenter;
        $params['action'] = $action;

        // Vygenerování URL pomocí routeru
        $url = $this->router->constructUrl($params, $this->httpRequest->getUrl());

        return $url ? (string) $url : '#';
    }

    /**
     * Parse destination string
     */
    private function parseDestination(string $destination): array
    {
        if (strpos($destination, ':') !== false) {
            [$presenter, $action] = explode(':', $destination);
        } else {
            $presenter = $destination;
            $action = 'default';
        }

        return [ucfirst($presenter), $action];
    }

    /**
     * Hierarchické vyhledání template souboru
     *
     * Hierarchie:
     * 1. UI/{ShopTextId}/{Presenter}/{action}.latte
     * 2. UI/Base/{Presenter}/{action}.latte
     *
     * @param string $presenterName Název presenteru (např. 'Home')
     * @param string $action Název akce (např. 'default')
     * @return string Cesta k template souboru
     * @throws \RuntimeException Pokud template nebyl nalezen
     */
    private function resolveTemplate(string $presenterName, string $action): string
    {
        $shopTextId = $this->shopContext->getTextId();
        $basePath = dirname(__DIR__); // src/UI/

        // 1. Zkus shop-specific template
        $shopTemplate = "{$basePath}/{$shopTextId}/{$presenterName}/{$action}.latte";

        if (file_exists($shopTemplate)) {
            \Tracy\Debugger::barDump($shopTemplate, 'Using Shop-Specific Template');
            return $shopTemplate;
        }

        // 2. Fallback na Base template
        $baseTemplate = "{$basePath}/Base/{$presenterName}/{$action}.latte";

        if (file_exists($baseTemplate)) {
            \Tracy\Debugger::barDump($baseTemplate, 'Using Base Template');
            return $baseTemplate;
        }

        // 3. Template nebyl nalezen
        throw new \RuntimeException(
            "Template '{$presenterName}/{$action}.latte' nebyl nalezen (ani shop-specific, ani base)"
        );
    }

    /**
     * Hierarchické vyhledání layout souboru
     *
     * Hierarchie:
     * 1. UI/{ShopTextId}/@layout.latte (shop-specific layout - povinný pro produkci)
     * 2. UI/Base/@layout.latte (fallback pro development)
     *
     * @return string Cesta k layout souboru
     * @throws \RuntimeException Pokud layout nebyl nalezen
     */
    protected function getLayoutPath(): string
    {
        $shopTextId = $this->shopContext->getTextId();
        $basePath = dirname(__DIR__); // src/UI/

        // 1. Zkus shop-specific layout (preferovaný)
        $shopLayout = "{$basePath}/{$shopTextId}/@layout.latte";

        if (file_exists($shopLayout)) {
            \Tracy\Debugger::barDump($shopLayout, 'Using Shop-Specific Layout');
            return $shopLayout;
        }

        // 2. Fallback na Base layout (pro development/testing)
        $baseLayout = "{$basePath}/Base/@layout.latte";

        if (file_exists($baseLayout)) {
            \Tracy\Debugger::barDump($baseLayout, 'Using Base Layout (Fallback)');
            return $baseLayout;
        }

        // 3. Layout nebyl nalezen
        throw new \RuntimeException(
            "Layout pro shop '{$shopTextId}' nebyl nalezen (ani shop-specific, ani base)"
        );
    }

    /**
     * Vykreslení templatu
     */
    protected function render(string $template = null): void
    {
        if ($template === null) {
            // Automatické určení templatu z názvu presenteru a akce
            $presenterName = $this->getPresenterName();
            $action = $this->params['action'] ?? 'default';
            $template = $this->resolveTemplate($presenterName, $action);
        }

        if (!file_exists($template)) {
            throw new \RuntimeException("Template nebyl nalezen: {$template}");
        }

        $this->latte->render($template, $this->templateVars);
    }

    /**
     * Získání názvu presenteru
     */
    private function getPresenterName(): string
    {
        $class = get_class($this);
        $name = substr($class, strrpos($class, '\\') + 1);
        return str_replace('Presenter', '', $name);
    }

    /**
     * Redirect
     */
    protected function redirect(string $destination, array $params = [], int $code = 302): void
    {
        $url = $this->link($destination, $params);
        header("Location: {$url}", true, $code);
        exit;
    }

    /**
     * Získání parametru z URL
     */
    public function getParam(string $name, mixed $default = null): mixed
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * Check if customer is logged in
     *
     * @return bool
     */
    protected function isLoggedIn(): bool
    {
        return $this->customerAuthService->isLoggedIn();
    }

    /**
     * Get currently logged in customer
     *
     * @return Customer|null
     */
    protected function getCustomer(): ?Customer
    {
        if ($this->customerCache === null) {
            $this->customerCache = $this->customerAuthService->getCurrentCustomer();
        }

        return $this->customerCache;
    }

    /**
     * Require customer to be logged in
     * Redirect to login page if not authenticated
     */
    protected function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            $this->flashMessage('Pro pokračování se prosím přihlaste', 'warning');
            $this->redirect('Auth:login', ['backlink' => $this->storeRequest()]);
        }
    }

    /**
     * Store current request for later restoration (backlink)
     *
     * @return string Backlink key
     */
    protected function storeRequest(): string
    {
        $key = uniqid('backlink_', true);
        $_SESSION['backlinkStorage'][$key] = $_SERVER['REQUEST_URI'] ?? '/';
        return $key;
    }

    /**
     * Restore request from backlink key and redirect
     *
     * @param string $key Backlink key
     * @return void
     */
    protected function restoreRequest(string $key): void
    {
        if (isset($_SESSION['backlinkStorage'][$key])) {
            $url = $_SESSION['backlinkStorage'][$key];
            unset($_SESSION['backlinkStorage'][$key]);
            header("Location: $url");
            exit;
        }
    }
}