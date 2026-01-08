# Překladový systém - Návod k použití

## Základní použití v šablonách

```latte
{* Jednoduchý překlad *}
<h1>{_('Úvodní stránka')}</h1>

{* S parametry (sprintf) *}
<p>{_('Celkem %d položek', [$count])}</p>

{* S named parametry *}
<p>{_('Zdravíme {name}', [name => $userName])}</p>
```

## Struktura souborů

```
/app/lang/
  ├── en.php    ← anglické překlady
  ├── de.php    ← německé překlady
  └── sk.php    ← slovenské překlady
```

**Výchozí jazyk (cs) nemá překladový soubor** - texty se zobrazují jak jsou napsané v `{_('...')}`.

## Helper skripty

### 1. Kontrola překladů

Zkontroluje všechny překlady a najde chybějící i nepoužívané:

```bash
# Základní kontrola všech jazyků
php bin/check-translations.php

# Kontrola pouze angličtiny
php bin/check-translations.php --lang=en
```

**Výstup:**
```
=== Kontrola překladů ===

🔍 Hledám texty v šablonách...
✓ Nalezeno 45 unikátních textů

Jazyk: en
  ✓ 42 přeloženo
  ✗ 3 chybí:
    - "Nový text co není přeložený"
    - "Další nepřeložený text"

Jazyk: de
  ✓ 40 přeloženo
  ✗ 5 chybí:
    - ...

⚠️  Nepoužívané překlady:

Jazyk: en
  - "Starý text" (můžete smazat)

✓ Žádné nepoužívané překlady
```

### 2. Generování chybějících překladů

Automaticky vytvoří záznamy pro chybějící překlady:

```bash
# Vygenerovat chybějící EN překlady
php bin/generate-translations.php --lang=en

# Pouze zobrazit co by se změnilo (bez uložení)
php bin/generate-translations.php --lang=de --dry-run
```

**Co dělá:**
1. Najde všechny texty v šablonách
2. Porovná s existujícími překlady
3. Vytvoří backup souboru (`en.php.backup.2025-01-15_143022`)
4. **Přidá chybějící překlady na konec souboru** s prefixem `TODO:`
5. **Zachová strukturu, komentáře a pořadí** existujících překladů

**Příklad - před:**
```php
// app/lang/en.php
return [
    // Navigace
    'Úvodní stránka' => 'Homepage',
    'O nás' => 'About Us',
];
```

**Příklad - po spuštění:**
```php
// app/lang/en.php
return [
    // Navigace
    'Úvodní stránka' => 'Homepage',
    'O nás' => 'About Us',

    // === Automaticky přidané překlady ===
    // TODO: Doplnit překlady níže
    'Naše služby' => 'TODO: Naše služby',
    'Kontaktujte nás' => 'TODO: Kontaktujte nás',
];
```

Pak ručně nahradíš `TODO:` skutečným překladem.

## Workflow

### 1. Přidáš nový text do šablony
```latte
<h2>{_('Naše služby')}</h2>
```

### 2. Zkontroluj chybějící překlady
```bash
php bin/check-translations.php
```

### 3. Vygeneruj placeholdery
```bash
php bin/generate-translations.php --lang=en
php bin/generate-translations.php --lang=de
```

### 4. Doplň překlady v souborech
```php
// app/lang/en.php
'Naše služby' => 'Our Services',

// app/lang/de.php
'Naše služby' => 'Unsere Dienstleistungen',
```

### 5. Znovu zkontroluj
```bash
php bin/check-translations.php
# ✓ Všechny texty jsou přeloženy!
```

## Tipy

### Použití parametrů

**Sprintf style** (poziční):
```latte
{_('Máte %d nových zpráv', [$count])}
```

**Named placeholders** (doporučeno pro více parametrů):
```latte
{_('Zobrazeno {from}-{to} z {total}', [
    from => $start,
    to => $end,
    total => $totalItems
])}
```

### Hledání nepoužívaných překladů

Najde překlady, které máš v souborech, ale nepoužívají se nikde v šablonách.

**Zobrazuje se automaticky** při každém spuštění `check-translations.php`.

```bash
php bin/check-translations.php
```

Výstup ukáže:
```
⚠️  Nepoužívané překlady:

Jazyk: en
  - "Starý text co už není nikde"
  - "Další mrtvý překlad"
```

Tyto překlady můžeš bezpečně smazat z jazykového souboru.

### CI/CD integrace

Přidej do CI pipeline:

```bash
# Fail pokud chybí překlady
php bin/check-translations.php || exit 1
```

## Troubleshooting

### "Překladový soubor nebyl nalezen"
- Překladový soubor pro výchozí jazyk (cs) **není potřeba**
- Pro ostatní jazyky vytvoř prázdný soubor: `touch app/lang/en.php`

### Překlad se nezobrazuje
1. Je jazyk v `config.php` v `languages.supported`?
2. Existuje soubor `/app/lang/{jazyk}.php`?
3. Je klíč v překladovém souboru přesně stejný jako v šabloně?

### Regex nenachází některé texty
Podporované formáty:
- `{_('text')}`
- `{_("text")}`
- `{_('text', [params])}`
- `{_($variable)}`

Nepodporované:
- Víceřádkové texty uvnitř makra
- Složité interpolace

## Technické detaily

### Jak funguje překlad

1. V šabloně: `{_('Úvodní stránka')}`
2. Makro se zkompiluje na: `Translator::translate('Úvodní stránka', null, [])`
3. Translator:
   - Detekuje jazyk z `$GLOBALS['currentLang']`
   - Pro výchozí jazyk (cs) vrátí originál
   - Pro ostatní jazyky hledá v `/app/lang/{lang}.php`
   - Pokud nenajde, vrátí originál

### Bezpečnost

- Všechny výstupy jsou automaticky escapovány pomocí `%escape` v Latte
- Parametry jsou escapovány před vložením do textu
