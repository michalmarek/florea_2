<?php
declare(strict_types=1);

namespace Core;

use Nette\Caching\Storages\FileStorage;
use Nette\Database\Connection;
use Nette\Database\Conventions\DiscoveredConventions;
use Nette\Database\Explorer;
use Nette\Database\Structure;
use Tracy\Debugger;

/**
 * Database - Správa databázového připojení
 *
 * Singleton třída pro vytvoření a správu Nette Database Explorer instance.
 * Připojení je lazy - vytvoří se až při prvním použití.
 *
 * Metody:
 * - connect() - Vytvoří připojení k databázi (volá se automaticky)
 * - getExplorer() - Vrátí Explorer instanci (hlavní metoda pro použití)
 * - getConnection() - Vrátí Connection instanci
 * - query(string, ...$params) - Provede SQL dotaz přímo
 * - table(string) - Shortcut pro $explorer->table()
 *
 * Konfigurace v config.php:
 * 'database' => [
 *     'dsn' => 'mysql:host=localhost;dbname=db_name',
 *     'user' => 'root',
 *     'password' => 'heslo',
 *     'options' => [...],
 * ]
 *
 * Použití:
 * $articles = Database::table('articles')->where('published', 1);
 *
 * @example
 * // V presenteru
 * $users = Database::table('users')->fetchAll();
 * $user = Database::table('users')->get(5);
 */
class Database
{
    private static ?Explorer $explorer = null;
    private static ?Connection $connection = null;

    /**
     * Vytvoření připojení k databázi
     */
    public static function connect(): void
    {
        if (self::$connection !== null) {
            return; // Již připojeno
        }

        // Načtení konfigurace
        $dsn = Config::get('database.dsn');
        $user = Config::get('database.user');
        $password = Config::get('database.password');
        $options = Config::get('database.options', []);

        // Vytvoření Connection
        self::$connection = new Connection($dsn, $user, $password, $options);

        // Vytvoření cache pro Database
        $cacheStorage = new FileStorage(Config::get('paths.cache'));

        // Vytvoření Structure (pro automatickou detekci relací)
        $structure = new Structure(self::$connection, $cacheStorage);

        // Vytvoření Conventions (pojmenování sloupců, tabulek)
        $conventions = new DiscoveredConventions($structure);

        // Tracy panel automaticky
        if (Config::get('debugger.mode') === Debugger::Development) {
            \Nette\Bridges\DatabaseTracy\ConnectionPanel::initialize(
                self::$connection,
                true,
                "database",
            );
        }

        // Vytvoření Explorer
        self::$explorer = new Explorer(
            self::$connection,
            $structure,
            $conventions,
            $cacheStorage
        );
    }

    /**
     * Získání Explorer instance
     */
    public static function getExplorer(): Explorer
    {
        if (self::$explorer === null) {
            self::connect();
        }

        return self::$explorer;
    }

    /**
     * Získání Connection instance
     */
    public static function getConnection(): Connection
    {
        if (self::$connection === null) {
            self::connect();
        }

        return self::$connection;
    }

    /**
     * Shortcut pro přístup k tabulce
     */
    public static function table(string $table): \Nette\Database\Table\Selection
    {
        return self::getExplorer()->table($table);
    }

    /**
     * Provedení SQL dotazu
     */
    public static function query(string $sql, ...$params): \Nette\Database\ResultSet
    {
        return self::getConnection()->query($sql, ...$params);
    }

    /**
     * Začátek transakce
     */
    public static function beginTransaction(): void
    {
        self::getConnection()->beginTransaction();
    }

    /**
     * Commit transakce
     */
    public static function commit(): void
    {
        self::getConnection()->commit();
    }

    /**
     * Rollback transakce
     */
    public static function rollBack(): void
    {
        self::getConnection()->rollBack();
    }

    /**
     * Získání posledního vloženého ID
     */
    public static function getInsertId(): int
    {
        return (int)self::getConnection()->getInsertId();
    }
}