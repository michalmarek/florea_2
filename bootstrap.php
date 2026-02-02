<?php declare(strict_types=1);

// Načtu vendory
require __DIR__ . '/vendor/autoload.php';

use Core\Application;
use Core\Config;
use Core\Database;
use Core\Container;
use Core\Router;
use Shop\ShopContext;
use Shop\ShopDetector;
use Shop\ShopRepository;
use Shop\Exception\ShopNotFoundException;

// Spuštění session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// DI Container
$container = new Container();


// Načtení shop domain mappingu (potřebujeme před detekcí shopu)
$shopsConfig = require __DIR__ . '/config/shops.php';

// Databázové připojení (zatím bez Config, načteme přímo)
$dbConfig = require __DIR__ . '/config/common/database.php';
$appConfig = require __DIR__ . '/config/common/app.php';


// Databázové připojení
Database::connect($dbConfig['database'], $appConfig);


try {
    // Detekce shopu
    $shopRepository = new ShopRepository();
    $shopDetector = new ShopDetector($shopRepository, $shopsConfig['domain_mapping']);
    $shopContext = $shopDetector->detectFromRequest();

    // Registrace ShopContext do Containeru
    $container->register(ShopContext::class, function() use ($shopContext) {
        return $shopContext;
    });

    // Registrace ProductRepository
    $container->register(\Models\Product\ProductRepository::class, function($c) {
        return new \Models\Product\ProductRepository();
    });

    // MenuCategoryRepository
    $container->register(\Models\Category\MenuCategoryRepository::class, function($c) {
        return new \Models\Category\MenuCategoryRepository();
    });

    // CustomerRepository
    $container->register(\Models\Customer\CustomerRepository::class, function($c) {
        return new \Models\Customer\CustomerRepository();
    });

    // CustomerAuthService
    $container->register(\Models\Customer\CustomerAuthService::class, function($c) {
        return new \Models\Customer\CustomerAuthService(
            $c->get(\Models\Customer\CustomerRepository::class),
            $c->get(ShopContext::class)
        );
    });

    // LayoutDataProvider
    $container->register(\UI\Base\LayoutDataProvider::class, function($c) {
        return new \UI\Base\LayoutDataProvider(
            $c->get(ShopContext::class),
            $c->get(\Models\Category\MenuCategoryRepository::class)
        );
    });



    // Load email configuration
    $emailConfig = require __DIR__ . '/config/common/email.php';

// Register Nette SMTP Mailer
    $container->register(\Nette\Mail\Mailer::class, function() use ($emailConfig) {
        $mailer = new \Nette\Mail\SmtpMailer(
            host: $emailConfig['smtp']['host'],
            port: $emailConfig['smtp']['port'],
            username: $emailConfig['smtp']['username'],
            password: $emailConfig['smtp']['password'],
            encryption: $emailConfig['smtp']['encryption'],
        );

        return $mailer;
    });

// Register Maileon Provider
    $container->register(\Core\Email\Provider\MaileonProvider::class, function() use ($emailConfig) {
        return new \Core\Email\Provider\MaileonProvider(
            $emailConfig['maileon']['apiKey'],
            $emailConfig['maileon']['baseUrl']
        );
    });

// Register Nette Mail Provider
    $container->register(\Core\Email\Provider\NetteMailProvider::class, function($c) use ($emailConfig) {
        return new \Core\Email\Provider\NetteMailProvider(
            $c->get(\Nette\Mail\Mailer::class),
            $emailConfig['from']['email'],
            $emailConfig['from']['name']
        );
    });

// Register EmailService (main service)
    $container->register(\Core\Email\EmailService::class, function($c) use ($emailConfig) {
        // Build log path from app config + email config
        $appConfig = Config::get('app');
        $logPath = $emailConfig['logging']['enabled']
            ? $appConfig['paths']['log'] . '/' . $emailConfig['logging']['filename']
            : null;

        return new \Core\Email\EmailService(
            $c->get(\Core\Email\Provider\MaileonProvider::class),
            $c->get(\Core\Email\Provider\NetteMailProvider::class),
            $emailConfig['logging']['enabled'],
            $logPath
        );
    });



} catch (ShopNotFoundException $e) {
    // Vlastní 404 pro neznámou doménu
    http_response_code(404);

    // Zalogovat chybu
    Tracy\Debugger::log($e);
    exit;
}


// === KROK 2: Inicializovat Config pro detekovaný shop ===
Config::init($shopContext->getTextId());

// === KROK 3: Inicializovat Tracy s hierarchickou konfigurací ===
Tracy\Debugger::enable(
    Config::get('app')['debugger']['mode'],
    Config::get('app')['debugger']['logDir']
);

// Debug info v Tracy
if (Config::get('app')['debugger']['mode'] === Tracy\Debugger::Development) {
    Tracy\Debugger::barDump($shopContext, 'ShopContext');
    Tracy\Debugger::barDump(Config::get('app'), 'App Config');
}

// Pokud není přihlášený v session, zkus Remember Me token
if (!isset($_SESSION['customer']) && isset($_COOKIE['remember_token'])) {
    $customerAuthService = $container->get(\Models\Customer\CustomerAuthService::class);
    $customerAuthService->verifyRememberToken();
}

// Vytvoření a spuštění aplikace
$app = new Application($container);
$app->run();