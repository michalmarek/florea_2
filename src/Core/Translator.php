<?php
declare(strict_types=1);

namespace Core;


/**
 * Translator - Správa překladů aplikace
 *
 * Statická třída pro práci s překlady. Podporuje:
 * - load(string $lang) - Načtení překladového souboru pro daný jazyk
 * - translate(string $message, string $lang) - Překlad textu (výchozí text jako klíč)
 * - setDefaultLanguage(string $lang) - Nastavení výchozího jazyka
 * - getLoadedLanguages() - Seznam načtených jazyků
 * - hasTranslation(string $message, string $lang) - Kontrola existence překladu
 * - reset() - Reset pro testování
 *
 * Systém používá výchozí text jako klíč:
 * - Výchozí jazyk (např. 'cs'): vrací text jak je → {_('Úvodní stránka')} = 'Úvodní stránka'
 * - Ostatní jazyky: hledá v překladovém poli → en['Úvodní stránka'] = 'Homepage'
 *
 * Struktura překladových souborů:
 * /app/lang/en.php:
 * return [
 *     'Úvodní stránka' => 'Homepage',
 *     'O nás' => 'About Us',
 * ];
 *
 * @example
 * Translator::setDefaultLanguage('cs');
 * Translator::load('en');
 * echo Translator::translate('Úvodní stránka', 'cs'); // 'Úvodní stránka'
 * echo Translator::translate('Úvodní stránka', 'en'); // 'Homepage'
 */
class Translator
{
    /** @var array<string, array<string, string>> Načtené překlady [jazyk => [klíč => překlad]] */
    private static array $translations = [];

    /** @var string Výchozí jazyk (zobrazuje originální texty) */
    private static string $defaultLanguage = 'cs';

    /** @var array<string> Seznam načtených jazyků */
    private static array $loadedLanguages = [];

    /**
     * Nastavení výchozího jazyka
     */
    public static function setDefaultLanguage(string $lang): void
    {
        self::$defaultLanguage = $lang;
    }

    /**
     * Získání výchozího jazyka
     */
    public static function getDefaultLanguage(): string
    {
        return self::$defaultLanguage;
    }

    /**
     * Načtení překladového souboru pro daný jazyk
     */
    public static function load(string $lang): void
    {
        // Výchozí jazyk nepotřebuje překladový soubor
        if ($lang === self::$defaultLanguage) {
            self::$loadedLanguages[] = $lang;
            return;
        }

        // Už je načtený?
        if (in_array($lang, self::$loadedLanguages, true)) {
            return;
        }

        $langFile = Config::get('app.paths.lang') . "/{$lang}.php";

        if (!file_exists($langFile)) {
            // Není chyba pokud překladový soubor neexistuje - prostě nebude přeloženo
            trigger_error(
                "Překladový soubor pro jazyk '{$lang}' nebyl nalezen: {$langFile}",
                E_USER_NOTICE
            );
            self::$translations[$lang] = [];
            self::$loadedLanguages[] = $lang;
            return;
        }

        $translations = require $langFile;

        if (!is_array($translations)) {
            trigger_error(
                "Překladový soubor '{$langFile}' nevrací pole",
                E_USER_WARNING
            );
            self::$translations[$lang] = [];
        } else {
            self::$translations[$lang] = $translations;
        }

        self::$loadedLanguages[] = $lang;
    }

    /**
     * Překlad textu
     *
     * @param string $message Originální text (výchozí jazyk)
     * @param string|null $lang Cílový jazyk (null = použije se aktuální z $GLOBALS)
     * @param array $params Parametry pro nahrazení (sprintf style nebo {placeholder})
     * @return string Přeložený text nebo originál
     */
    public static function translate(
        string $message,
        ?string $lang = null,
        array $params = []
    ): string {
        // Automatická detekce jazyka z globální proměnné
        if ($lang === null) {
            $lang = $GLOBALS['currentLang'] ?? self::$defaultLanguage;
        }

        // Načtení jazyka pokud ještě není
        if (!in_array($lang, self::$loadedLanguages, true)) {
            self::load($lang);
        }

        // Výchozí jazyk - vrať originál
        if ($lang === self::$defaultLanguage) {
            return self::replaceParams($message, $params);
        }

        // Hledání překladu
        $translation = self::$translations[$lang][$message] ?? null;

        // Pokud překlad neexistuje, vrať originál
        if ($translation === null) {
            return self::replaceParams($message, $params);
        }

        return self::replaceParams($translation, $params);
    }

    /**
     * Nahrazení parametrů v textu
     * Podporuje dva formáty:
     * - sprintf: "Máte %d nových zpráv" s [5]
     * - placeholders: "Máte {count} nových zpráv" s ['count' => 5]
     */
    private static function replaceParams(string $text, array $params): string
    {
        if (empty($params)) {
            return $text;
        }

        // Pokud jsou klíče číselné, použij sprintf
        if (array_keys($params) === range(0, count($params) - 1)) {
            return sprintf($text, ...$params);
        }

        // Jinak nahraď {placeholder}
        foreach ($params as $key => $value) {
            $text = str_replace('{' . $key . '}', (string)$value, $text);
        }

        return $text;
    }

    /**
     * Kontrola existence překladu
     */
    public static function hasTranslation(string $message, string $lang): bool
    {
        if ($lang === self::$defaultLanguage) {
            return true; // Výchozí jazyk má vždy "překlad"
        }

        if (!in_array($lang, self::$loadedLanguages, true)) {
            self::load($lang);
        }

        return isset(self::$translations[$lang][$message]);
    }

    /**
     * Získání všech překladů pro daný jazyk
     */
    public static function getTranslations(string $lang): array
    {
        if (!in_array($lang, self::$loadedLanguages, true)) {
            self::load($lang);
        }

        return self::$translations[$lang] ?? [];
    }

    /**
     * Získání seznamu načtených jazyků
     */
    public static function getLoadedLanguages(): array
    {
        return self::$loadedLanguages;
    }

    /**
     * Získání všech použitých textů (klíčů) napříč všemi jazyky
     * Užitečné pro debugging a helper skripty
     */
    public static function getAllKeys(): array
    {
        $keys = [];

        foreach (self::$translations as $langTranslations) {
            $keys = array_merge($keys, array_keys($langTranslations));
        }

        return array_unique($keys);
    }

    /**
     * Reset překladů (pro testování)
     */
    public static function reset(): void
    {
        self::$translations = [];
        self::$loadedLanguages = [];
        self::$defaultLanguage = 'cs';
    }
}