<?php
declare(strict_types=1);

namespace Core;

/**
 * Config - Správa konfigurace aplikace
 *
 * Statická třída pro práci s konfigurací. Podporuje:
 * - load(string $file) - Načtení hlavního config souboru
 * - loadLocal(string $file) - Načtení a merge lokálního config souboru (přepíše hodnoty)
 * - get(string $key, mixed $default = null) - Získání hodnoty pomocí tečkové notace (např. 'database.user')
 * - set(string $key, mixed $value) - Nastavení hodnoty v runtime
 * - has(string $key) - Kontrola existence klíče
 * - getAll() - Získání celé konfigurace jako pole
 * - reset() - Reset konfigurace (pro testování)
 *
 * @example
 * Config::load(__DIR__ . '/config.php');
 * Config::loadLocal(__DIR__ . '/config.local.php');
 * $dbUser = Config::get('database.user');
 * $siteName = Config::get('site.name', 'Default Name');
 */
class Config
{
    private static ?array $config = null;

    /**
     * Načtení konfigurace ze souboru
     * Pokud je již config načtený, merguje rekurzivně s existujícím
     */
    public static function load(string $configFile): void
    {
        if (!file_exists($configFile)) {
            throw new \RuntimeException("Konfigurační soubor nebyl nalezen: {$configFile}");
        }

        $newConfig = require $configFile;

        if (self::$config === null) {
            // První načtení - prostě přiřaď
            self::$config = $newConfig;
        } else {
            // Další načtení - merguj rekurzivně
            self::$config = array_replace_recursive(self::$config, $newConfig);
        }
    }

    /**
     * Načtení lokální konfigurace a merge s existující
     */
    public static function loadLocal(string $localConfigFile): void
    {
        if (!file_exists($localConfigFile)) {
            return; // Není chyba, pokud local config neexistuje
        }

        $local = require $localConfigFile;

        foreach ($local as $key => $value) {
            if (is_array($value) && self::has($key)) {
                // Rekurzivní merge pro pole
                $existing = self::get($key);
                self::set($key, array_replace_recursive($existing, $value));
            } else {
                self::set($key, $value);
            }
        }
    }

    /**
     * Získání celé konfigurace
     */
    public static function getAll(): array
    {
        self::ensureLoaded();
        return self::$config;
    }

    /**
     * Získání hodnoty z konfigurace pomocí tečkové notace
     * Příklad: Config::get('database.user')
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureLoaded();

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Nastavení hodnoty do konfigurace (runtime)
     */
    public static function set(string $key, mixed $value): void
    {
        self::ensureLoaded();

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Kontrola, zda existuje klíč v konfiguraci
     */
    public static function has(string $key): bool
    {
        self::ensureLoaded();

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * Zajištění, že konfigurace je načtená
     */
    private static function ensureLoaded(): void
    {
        if (self::$config === null) {
            throw new \RuntimeException(
                "Konfigurace nebyla načtena. Zavolej Config::load() v bootstrap.php"
            );
        }
    }

    /**
     * Reset konfigurace (pro testování)
     */
    public static function reset(): void
    {
        self::$config = null;
    }
}