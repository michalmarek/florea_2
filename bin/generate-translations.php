<?php

declare(strict_types=1);

/**
 * Skript pro generování chybějících překladů
 *
 * Použití: php bin/generate-translations.php [volby]
 *
 * Volby:
 *   --lang=en       Jazyk pro který generovat (povinné)
 *   --dry-run       Pouze zobrazit co by se přidalo (neukládat)
 *   --help          Zobrazit nápovědu
 *
 * Příklady:
 *   php bin/generate-translations.php --lang=en
 *   php bin/generate-translations.php --lang=de --dry-run
 */

// Bootstrap
require __DIR__ . '/../vendor/autoload.php';

use Core\Config;

// Načtení konfigurace
Config::load(__DIR__ . '/../app/config.php');
Config::loadLocal(__DIR__ . '/../app/config.local.php');

// Parsování argumentů
$options = parseArguments($argv);

if (isset($options['help']) || !isset($options['lang'])) {
    showHelp();
    exit(0);
}

$targetLang = $options['lang'];
$dryRun = isset($options['dry-run']);

// Barvy
$colors = [
    'reset' => "\033[0m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'bold' => "\033[1m",
];

echo $colors['bold'] . "=== Generování chybějících překladů pro jazyk: {$targetLang} ===" . $colors['reset'] . "\n\n";

// 1. Najdi všechny texty v šablonách
echo "🔍 Hledám texty v šablonách...\n";
$textsInTemplates = findTextsInTemplates();
echo $colors['green'] . "✓ Nalezeno " . count($textsInTemplates) . " textů" . $colors['reset'] . "\n\n";

// 2. Načti existující překlady
$langFile = Config::get('paths.app') . "/lang/{$targetLang}.php";
$existingTranslations = file_exists($langFile) ? require $langFile : [];

// 3. Najdi chybějící
$missing = array_diff($textsInTemplates, array_keys($existingTranslations));

if (empty($missing)) {
    echo $colors['green'] . "✓ Všechny texty jsou již přeloženy!" . $colors['reset'] . "\n";
    exit(0);
}

echo $colors['yellow'] . "⚠️  Nalezeno " . count($missing) . " chybějících překladů:" . $colors['reset'] . "\n\n";

// 4. Vygeneruj nové překlady
$newTranslations = [];
foreach ($missing as $text) {
    // Placeholder - nechá originální text s komentářem TODO
    $newTranslations[$text] = "TODO: {$text}";
}

// 5. Seřaď abecedně (pro lepší přehlednost)
ksort($newTranslations);

// 6. Preview
echo $colors['blue'] . "Přidám tyto překlady:" . $colors['reset'] . "\n";
foreach ($newTranslations as $key => $value) {
    echo "  '{$key}' => '{$value}',\n";
}
echo "\n";

// 7. Ulož (pokud není dry-run)
if ($dryRun) {
    echo $colors['yellow'] . "🔍 DRY RUN - soubor nebyl změněn" . $colors['reset'] . "\n";
    echo "Spusť bez --dry-run pro uložení změn\n";
} else {
    // Backup původního souboru
    if (file_exists($langFile)) {
        $backupFile = $langFile . '.backup.' . date('Y-m-d_His');
        copy($langFile, $backupFile);
        echo $colors['green'] . "✓ Vytvořen backup: " . basename($backupFile) . $colors['reset'] . "\n";

        // Přidej na konec existujícího souboru
        appendTranslationsToFile($langFile, $newTranslations);
    } else {
        // Soubor neexistuje - vytvoř nový
        $allTranslations = $newTranslations;
        ksort($allTranslations);
        $content = generatePhpFile($allTranslations, $targetLang);
        file_put_contents($langFile, $content);
    }

    echo $colors['green'] . "✓ Překlady přidány do: {$langFile}" . $colors['reset'] . "\n";
    echo $colors['yellow'] . "⚠️  Nezapomeň doplnit TODO překlady!" . $colors['reset'] . "\n";
}

// === POMOCNÉ FUNKCE ===

/**
 * Najde všechny texty v {_('...')} makrech
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
        preg_match_all(
            '/\{_\([\'"](.+?)(?<!\\\\)[\'"](?:\s*,\s*.*?)?\)\}/s',
            $content,
            $matches
        );

        if (!empty($matches[1])) {
            foreach ($matches[1] as $text) {
                $text = str_replace(["\\'", '\\"'], ["'", '"'], $text);
                $texts[] = $text;
            }
        }
    }

    return array_unique($texts);
}

/**
 * Přidá nové překlady na konec existujícího souboru
 */
function appendTranslationsToFile(string $filePath, array $newTranslations): void
{
    // Načti existující obsah
    $content = file_get_contents($filePath);

    // Najdi pozici před uzavírací ];
    $lastBracketPos = strrpos($content, '];');

    if ($lastBracketPos === false) {
        throw new \RuntimeException("Soubor {$filePath} nemá platný formát (chybí uzavírací ];)");
    }

    // Vytvoř nový obsah pro přidání
    $newContent = "\n    // === Automaticky přidané překlady ===\n";
    $newContent .= "    // TODO: Doplnit překlady níže\n";

    foreach ($newTranslations as $key => $value) {
        $key = addslashes($key);
        $value = addslashes($value);
        $newContent .= "    '{$key}' => '{$value}',\n";
    }

    // Vlož před ];
    $updatedContent = substr($content, 0, $lastBracketPos) . $newContent . '];' . "\n";

    // Ulož
    file_put_contents($filePath, $updatedContent);
}

/**
 * Vygeneruje obsah PHP souboru s překlady
 */
function generatePhpFile(array $translations, string $lang): string
{
    $langName = [
        'en' => 'Anglické',
        'de' => 'Německé',
        'sk' => 'Slovenské',
    ][$lang] ?? ucfirst($lang);

    $content = "<?php\n\ndeclare(strict_types=1);\n\n";
    $content .= "/**\n * {$langName} překlady\n */\n\n";
    $content .= "return [\n";

    foreach ($translations as $key => $value) {
        // Escape uvozovky a backslashe
        $key = addslashes($key);
        $value = addslashes($value);

        $content .= "    '{$key}' => '{$value}',\n";
    }

    $content .= "];\n";

    return $content;
}

/**
 * Parsuje argumenty
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
Skript pro generování chybějících překladů

Použití:
  php bin/generate-translations.php --lang=JAZYK [volby]

Volby:
  --lang=en       Jazyk pro který generovat (POVINNÉ)
  --dry-run       Pouze zobrazit změny, neukládat
  --help          Zobrazit tuto nápovědu

Příklady:
  php bin/generate-translations.php --lang=en
    Vygeneruje chybějící anglické překlady

  php bin/generate-translations.php --lang=de --dry-run
    Zobrazí co by se přidalo do německých překladů (bez uložení)

Poznámka:
  Skript vytvoří záznamy s prefixem "TODO:", které musíte ručně doplnit.
  Originální soubor se před změnou zálohuje.


HELP;
}