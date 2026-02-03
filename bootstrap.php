<?php declare(strict_types=1);

// Set timezone for entire application
date_default_timezone_set('Europe/Prague');

// Načtu vendory
require __DIR__ . '/vendor/autoload.php';

use Core\Application;
use Core\Config;
use Core\Database;
use Core\Container;
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


// === PRE-CONFIG PHASE (minimum nutné před Config::init) ===
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

} catch (ShopNotFoundException $e) {
    // Vlastní 404 pro neznámou doménu
    http_response_code(404);

    // Zalogovat chybu
    Tracy\Debugger::log($e);
    exit;
}


// === CONFIG INITIALIZATION (TEĎ MÁME DETEKOVANÝ SHOP) ===
Config::init($shopContext->getTextId());

// === TRACY DEBUGGER (používá Config) ===
Tracy\Debugger::enable(
    Config::get('app.debugger.mode'),
    Config::get('app.paths.log')
);
// Debug info v Tracy
if (Config::get('app.debugger.mode') === Tracy\Debugger::Development) {
    Tracy\Debugger::barDump($shopContext, 'ShopContext');
    Tracy\Debugger::barDump(Config::get('app'), 'App Config');
}


// === CONTAINER REGISTRATIONS ===

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


// === EMAIL SERVICES (používají Config::get s tečkovým zápisem) ===

// Register Nette SMTP Mailer
$container->register(\Nette\Mail\Mailer::class, function() {
    return new \Nette\Mail\SmtpMailer(
        host: Config::get('email.smtp.host'),
        port: Config::get('email.smtp.port'),
        username: Config::get('email.smtp.username'),
        password: Config::get('email.smtp.password'),
        encryption: Config::get('email.smtp.encryption'),
    );
});

// Register Maileon Provider
$container->register(\Core\Email\Provider\MaileonProvider::class, function() {
    return new \Core\Email\Provider\MaileonProvider(
        Config::get('email.maileon.apiKey'),
        Config::get('email.maileon.baseUrl')
    );
});

// Register Nette Mail Provider
$container->register(\Core\Email\Provider\NetteMailProvider::class, function($c) {
    return new \Core\Email\Provider\NetteMailProvider(
        $c->get(\Nette\Mail\Mailer::class),
        Config::get('email.from.email'),
        Config::get('email.from.name')
    );
});

// Register EmailService (main service)
$container->register(\Core\Email\EmailService::class, function($c) {
    // Build log path from app config + email config
    $logPath = Config::get('email.logging.enabled')
        ? Config::get('app.paths.log') . '/' . Config::get('email.logging.filename')
        : null;

    return new \Core\Email\EmailService(
        $c->get(\Core\Email\Provider\MaileonProvider::class),
        $c->get(\Core\Email\Provider\NetteMailProvider::class),
        Config::get('email.logging.enabled'),
        $logPath
    );
});


// Password Reset
$container->register(\Models\Customer\PasswordResetTokenRepository::class, function() {
    return new \Models\Customer\PasswordResetTokenRepository();
});

$container->register(\Models\Customer\PasswordResetService::class, function($c) {
    return new \Models\Customer\PasswordResetService(
        $c->get(\Models\Customer\CustomerRepository::class),
        $c->get(\Models\Customer\PasswordResetTokenRepository::class),
        $c->get(\Core\Email\EmailService::class),
        $c->get(ShopContext::class),
    );
});


// === REMEMBER ME TOKEN CHECK ===
if (!isset($_SESSION['customer']) && isset($_COOKIE['remember_token'])) {
    $customerAuthService = $container->get(\Models\Customer\CustomerAuthService::class);
    $customerAuthService->verifyRememberToken();
}


// === RUN APPLICATION ===
$app = new Application($container);
$app->run();