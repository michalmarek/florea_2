<?php

declare(strict_types=1);

/**
 * Skript pro kontrolu překladů
 *
 * Použití: php bin/check-translations.php [volby]
 *
 * Volby:
 *   --lang=en,de    Kontrolovat pouze vybrané jazyky (čárkou oddělené)
 *   --help          Zobrazit nápovědu
 *
 * Příklady:
 *   php bin/check-translations.php
 *   php bin/check-translations.php --lang=en
 */

// Bootstrap
require __DIR__ . '/../vendor/autoload.php';

use Core\Config;

// Načtení konfigurace
Config::load(__DIR__ . '/../app/config.php');
Config::loadLocal(__DIR__ . '/../app/config.local.php');

// Parsování argumentů
$options = parseArguments($argv);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

// Kontrola deprecated parametrů
if (isset($options['export']) || isset($options['unused'])) {
    echo "⚠️  Parametry --export a --unused byly odstraněny\n";
    echo "Export a nepoužívané překlady se zobrazují automaticky\n\n";
}

// Barvy pro terminál
$colors = [
    'reset' => "\033[0m",
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'bold' => "\033[1m",
];

echo $colors['bold'] . "=== Kontrola překladů ===" . $colors['reset'] . "\n\n";

// 1. Najdi všechny texty v šablonách
echo "🔍 Hledám texty v šablonách...\n";
$textsInTemplates = findTextsInTemplates();
echo $colors['green'] . "✓ Nalezeno " . count($textsInTemplates) . " unikátních textů" . $colors['reset'] . "\n\n";

// 2. Načti překlady ze souborů
$defaultLang = Config::get('languages.default', 'cs');
$supportedLanguages = Config::get('languages.supported', []);

// Filtrování jazyků podle --lang parametru
if (isset($options['lang'])) {
    $requestedLangs = explode(',', $options['lang']);
    $supportedLanguages = array_filter($supportedLanguages, function($lang) use ($requestedLangs, $defaultLang) {
        return $lang !== $defaultLang && in_array($lang, $requestedLangs, true);
    });
}

$allTranslations = [];
foreach ($supportedLanguages as $lang) {
    if ($lang === $defaultLang) {
        continue; // Výchozí jazyk přeskočíme
    }
    $allTranslations[$lang] = loadTranslationFile($lang);
}

// 3. Kontrola překladů pro každý jazyk
$report = [];

foreach ($allTranslations as $lang => $translations) {
    echo $colors['blue'] . "Jazyk: {$lang}" . $colors['reset'] . "\n";

    $missing = [];
    $translated = 0;

    foreach ($textsInTemplates as $text) {
        if (isset($translations[$text])) {
            $translated++;
        } else {
            $missing[] = $text;
        }
    }

    echo $colors['green'] . "  ✓ {$translated} přeloženo" . $colors['reset'] . "\n";

    if (!empty($missing)) {
        echo $colors['red'] . "  ✗ " . count($missing) . " chybí:" . $colors['reset'] . "\n";
        foreach ($missing as $text) {
            echo "    - \"{$text}\"\n";
        }
    }

    echo "\n";

    $report[$lang] = [
        'translated' => $translated,
        'missing' => $missing,
        'total' => count($textsInTemplates),
    ];
}

// 4. Nepoužívané překlady (vždy zobrazit)
echo $colors['yellow'] . "⚠️  Nepoužívané překlady:" . $colors['reset'] . "\n\n";

$hasUnused = false;
foreach ($allTranslations as $lang => $translations) {
    $unused = array_diff(array_keys($translations), $textsInTemplates);

    if (!empty($unused)) {
        $hasUnused = true;
        echo $colors['blue'] . "Jazyk: {$lang}" . $colors['reset'] . "\n";
        foreach ($unused as $text) {
            echo "  - \"{$text}\"\n";
        }
        echo "\n";
    }

    $report[$lang]['unused'] = $unused;
}

if (!$hasUnused) {
    echo $colors['green'] . "✓ Žádné nepoužívané překlady" . $colors['reset'] . "\n\n";
}

// 5. Shrnutí
echo $colors['bold'] . "=== Shrnutí ===" . $colors['reset'] . "\n";
$totalMissing = array_sum(array_map(fn($r) => count($r['missing']), $report));
if ($totalMissing === 0) {
    echo $colors['green'] . "✓ Všechny texty jsou přeloženy!" . $colors['reset'] . "\n";
} else {
    $totalTranslations = count($allTranslations);
    echo $colors['yellow'] . "⚠️  Celkem chybí {$totalMissing} překladů v {$totalTranslations} jazycích" . $colors['reset'] . "\n";
}

// === POMOCNÉ FUNKCE ===

/**
 * Najde všechny texty v {_('...')} makrech v .latte souborech
 */
function findTextsInTemplates(): array
{
    $templatesDir = Config::get('paths.app') . '/ui';
    $texts = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($templatesDir)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'latte') {
            continue;
        }

        $content = file_get_contents($file->getPathname());

        // Regex pro {_('text')} nebo {_("text")}
        // Podporuje i escaped uvozovky: {_('it\'s')}
        preg_match_all(
            '/\{_\([\'"](.+?)(?<!\\\\)[\'"](?:\s*,\s*.*?)?\)\}/s',
            $content,
            $matches
        );

        if (!empty($matches[1])) {
            foreach ($matches[1] as $text) {
                // Unescape escaped quotes
                $text = str_replace(["\\'", '\\"'], ["'", '"'], $text);
                $texts[] = $text;
            }
        }
    }

    return array_unique($texts);
}

/**
 * Načte překladový soubor pro daný jazyk
 */
function loadTranslationFile(string $lang): array
{
    $langFile = Config::get('paths.app') . "/lang/{$lang}.php";

    if (!file_exists($langFile)) {
        return [];
    }

    $translations = require $langFile;

    return is_array($translations) ? $translations : [];
}

/**
 * Parsuje argumenty příkazové řádky
 */
function parseArguments(array $argv): array
{
    $options = [];

    foreach ($argv as $arg) {
        if (strpos($arg, '--') === 0) {
            $arg = substr($arg, 2);

            if (strpos($arg, '=') !== false) {
                [$key, $value] = explode('=', $arg, 2);
                $options[$key] = $value;
            } else {
                $options[$arg] = true;
            }
        }
    }

    return $options;
}

/**
 * Zobrazí nápovědu
 */
function showHelp(): void
{
    echo <<<HELP
Skript pro kontrolu překladů

Použití:
  php bin/check-translations.php [volby]

Volby:
  --lang=en,de    Kontrolovat pouze vybrané jazyky (čárkou oddělené)
  --help          Zobrazit tuto nápovědu

Příklady:
  php bin/check-translations.php
    Zkontroluje všechny jazyky a zobrazí chybějící i nepoužívané překlady

  php bin/check-translations.php --lang=en
    Zkontroluje pouze anglické překlady


HELP;
}