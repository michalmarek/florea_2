<?php

declare(strict_types=1);

namespace UI\Base;

use Latte\Engine;
use Nette\Assets\Registry;
use Nette\Bridges\AssetsLatte\LatteExtension;
use Nette\Database\Explorer;
use Nette\Forms\Form;
use Nette\Http\IRequest;
use Nette\Routing\Router;
use Core\Config;
use Core\Database;
use Core\Assets\ManifestMapper;
use Shop\ShopContext;

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
    protected array $params;
    protected Router $router;
    protected IRequest $httpRequest;
    protected ShopContext $shopContext;
    protected Engine $latte;
    protected array $templateVars = [];
    protected string $lang;
    protected Explorer $database;
    private array $components = [];

    public function __construct(
        array $params,
        Router $router,
        IRequest $httpRequest,
        ShopContext $shopContext
    ) {
        // Uložení základních závislostí
        $this->params = $params;
        $this->router = $router;
        $this->httpRequest = $httpRequest;
        $this->shopContext = $shopContext;
        $this->lang = $params['lang'] ?? Config::get('languages.default', 'cs');

        // Inicializace databáze
        $this->database = Database::getExplorer();

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
            new ManifestMapper(
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

        $this->startup();
    }

    /**
     * Startup metoda - volá se před akcí
     */
    protected function startup(): void
    {
        // Nastavení základních proměnných pro všechny template
        $this->templateVars['lang'] = $this->lang;
        $this->templateVars['basePath'] = $this->httpRequest->getUrl()->getBasePath();

        $this->templateVars['siteName'] = $this->shopContext->getWebsiteName();
        $this->templateVars['shopContext'] = $this->shopContext;
        $this->templateVars['seller'] = $this->shopContext->getSeller();

        $this->templateVars['config'] = Config::get('app');

        // Načtení flash messages
        $this->templateVars['flashes'] = $this->getFlashMessages();

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
     * @return array Pole flash zpráv ve formátu [['message' => '...', 'type' => '...'], ...]
     */
    private function getFlashMessages(): array
    {

        $flashes = $_SESSION['_flashes'] ?? [];

        // Vymazání flash zpráv ze session (zobrazují se jen jednou)
        unset($_SESSION['_flashes']);

        return $flashes;
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
     * Vykreslení templatu
     */
    protected function render(string $template = null): void
    {
        if ($template === null) {
            // Automatické určení templatu z názvu presenteru a akce
            $presenterName = $this->getPresenterName();
            $action = $this->params['action'] ?? 'default';
            $template = __DIR__ . "/../{$presenterName}/{$action}.latte";
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
}