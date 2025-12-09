# 📁 Struktura projektu

## Nová co-located UI struktura + Assets

```
projekt/
│
├── app/
│   ├── ui/                          ← Presentery + šablony pohromadě
│   │   ├── Home/
│   │   │   ├── HomePresenter.php
│   │   │   └── default.latte
│   │   │
│   │   ├── Blog/
│   │   │   ├── BlogPresenter.php
│   │   │   ├── default.latte        ← seznam článků
│   │   │   └── detail.latte         ← detail článku
│   │   │
│   │   └── Error/
│   │       ├── 404.latte
│   │       └── 500.latte
│   │
│   ├── Presenters/
│   │   └── BasePresenter.php        ← Základní třída pro presentery
│   │
│   ├── bootstrap.php
│   ├── config.php
│   └── config.local.php             ← Git ignored
│
├── src/
│   ├── php/
│   │   ├── Latte/
│   │   │   ├── LinkExtension.php
│   │   │   ├── LinkMacro.php
│   │   │   ├── AssetsExtension.php  ← Assets extension
│   │   │   └── AssetMacro.php       ← n:asset makro
│   │   ├── Routing/
│   │   │   └── RouterFactory.php
│   │   ├── Application.php
│   │   ├── Config.php
│   │   ├── Database.php
│   │   └── Assets.php               ← Assets helper
│   │
│   └── assets/                      ← Zdrojové soubory pro Gulp
│       ├── scss/
│       │   ├── style.scss
│       │   └── _components.scss
│       ├── js/
│       │   └── app.js
│       └── images/
│           ├── logo.png
│           └── hero.jpg
│
├── www/
│   ├── assets/                      ← Build výstupy
│   │   ├── dist/                    ← CSS/JS z Gulpu (s hashem)
│   │   │   ├── style-abc123.css
│   │   │   └── app-def456.js
│   │   ├── images/                  ← Kopie obrázků
│   │   │   ├── logo.png
│   │   │   └── hero.jpg
│   │   └── manifest.json            ← Gulp manifest
│   │
│   ├── .htaccess
│   └── index.php
│
├── temp/
│   └── cache/                       ← Latte + Assets cache
│
├── log/                             ← Tracy logy
│
├── vendor/                          ← Composer packages
│
├── node_modules/                    ← NPM packages
│
├── gulpfile.js                      ← Gulp konfigurace
├── package.json                     ← NPM závislosti
├── composer.json
├── composer.lock
└── .gitignore
```   ├── ContactPresenter.php
│   │   │   └── default.latte
│   │   │
│   │   └── Error/
│   │       ├── 404.latte
│   │       └── 500.latte
│   │
│   ├── Presenters/
│   │   └── BasePresenter.php        ← Základní třída pro presentery
│   │
│   ├── bootstrap.php
│   ├── config.php
│   └── config.local.php             ← Git ignored
│
├── src/
│   ├── php/
│   │   ├── Latte/
│   │   │   ├── LinkExtension.php
│   │   │   └── LinkMacro.php
│   │   ├── Routing/
│   │   │   └── RouterFactory.php
│   │   ├── Application.php
│   │   └── Config.php
│   │
│   └── assets/
│       ├── css/
│       ├── js/
│       └── images/
│
├── www/
│   ├── .htaccess
│   ├── index.php
│   └── assets/                      ← Symlink nebo build output
│
├── temp/
│   └── cache/                       ← Latte cache
│
├── log/                             ← Tracy logy
│
├── vendor/                          ← Composer packages
│
├── composer.json
├── composer.lock
└── .gitignore
```

---

## 🎯 Výhody co-located struktury + Assets:

✅ **Všechno pohromadě** - presenter + jeho šablony na jednom místě  
✅ **Rychlá orientace** - vidíš hned co k sobě patří  
✅ **Snadné refaktorování** - přesuneš celou složku  
✅ **Lepší škálovatelnost** - připraveno na velké projekty  
✅ **Modulární** - každá složka je samostatný modul  
✅ **Verzované assety** - Gulp + manifest = cache busting  
✅ **Automatické rozměry** - n:asset přidá width/height

---

## 📝 Workflow s Assets:

### 1. Vývoj:
```bash
# Spusť Gulp watch
npm run dev

# Gulp sleduje změny v src/assets/
# Automaticky kompiluje do www/assets/
# Aktualizuje manifest.json
```

### 2. Šablony:
```latte
{* CSS/JS - verzované z manifestu *}
<link rel="stylesheet" href="{asset 'style.css'}">

{* Obrázky - s automatickými rozměry *}
<img n:asset="hero.jpg" alt="Hero">
```

### 3. Production build:
```bash
npm run build
git add www/assets/
git commit -m "Build assets"
```

---

## 🚀 Setup nového projektu:

### 1. Nainstaluj závislosti:
```bash
# Composer
composer install

# NPM
npm install
```

### 2. Vytvoř config.local.php:
```php
<?php
return [
    'database' => [
        'dsn' => 'mysql:host=localhost;dbname=mydb',
        'user' => 'root',
        'password' => 'heslo',
    ],
];
```

### 3. Spusť Gulp:
```bash
npm run dev
```

### 4. Spusť web server:
```bash
php -S localhost:8000 -t www
```

---

## 🔧 .gitignore doporučení:

```gitignore
# Vendor
/vendor/
/node_modules/

# Config
/app/config.local.php

# Cache a temp
/temp/cache/*
!/temp/cache/.gitkeep
/temp/*.html

# Log
/log/*
!/log/.gitkeep

# Assets - build výstupy
/www/assets/dist/
/www/assets/manifest.json

# IDE
.idea/
.vscode/
.DS_Store
```

**Poznámka:** `/www/assets/images/` commituj do gitu (jsou to source soubory)

---

## 📝 Příklad přidání nové sekce:

### 1. Vytvoř složku v `/app/ui/`:
```
/app/ui/Services/
```

### 2. Přidej presenter:
```php
// /app/ui/Services/ServicesPresenter.php
<?php
namespace App\UI\Services;
use App\Presenters\BasePresenter;

class ServicesPresenter extends BasePresenter {
    public function actionDefault(): void {
        $this->assign('title', 'Služby');
    }
    
    public function renderDefault(): void {
        $this->render();
    }
}
```

### 3. Přidej šablonu:
```latte
{* /app/ui/Services/default.latte *}
<h1>{$title}</h1>
<p>Seznam služeb...</p>
```

### 4. Přidej routu do config.php:
```php
'routes' => [
    [
        'patterns' => [
            'cs' => 'sluzby',
            'en' => 'services',
        ],
        'presenter' => 'Services',
        'action' => 'default',
    ],
]
```

### 5. Hotovo! ✅
- `/sluzby` → ServicesPresenter
- `/en/services` → ServicesPresenter (EN)

---

## 🔧 Namespace struktura:

```
App\UI\Home\HomePresenter
App\UI\Blog\BlogPresenter
App\UI\Article\ArticlePresenter
App\UI\Contact\ContactPresenter
```

**BasePresenter zůstává v:**
```
App\Presenters\BasePresenter
```

---

## 📦 Composer autoload:

```json
{
  "autoload": {
    "psr-4": {
      "Core\\": "src/php/",
      "App\\Presenters\\": "app/Presenters/",
      "App\\UI\\": "app/ui/"
    }
  }
}
```

Po změně autoloadu vždy spusť:
```bash
composer dump-autoload
```

---

## 🚀 Migrace ze staré struktury:

### Staré:
```
/app/Presenters/HomePresenter.php
/app/templates/Home/default.latte
```

### Nové:
```
/app/ui/Home/HomePresenter.php
/app/ui/Home/default.latte
```

### Změň namespace:
```php
// Staré
namespace App\Presenters;

// Nové
namespace App\UI\Home;
use App\Presenters\BasePresenter;
```