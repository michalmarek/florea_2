<?php

declare(strict_types=1);

namespace Core\Assets;

use Nette\Assets\Asset;
use Nette\Assets\AssetNotFoundException;
use Nette\Assets\Helpers;
use Nette\Assets\Mapper;

/**
 * ManifestMapper - Čte Gulp manifest pro verzované assety
 *
 * Gulp vytvoří flat manifest.json:
 *  {
 *    "style.css": "style-69f2e9a5b1.css",
 *    "app.js": "app-0854702cec.js",
 *    "logo.png": "logo-fcb67ded6b.png"
 *  }
 *
 *  Fyzická struktura:
 *  www/assets/
 *    css/style-69f2e9a5b1.css
 *    js/app-0854702cec.js
 *    images/logo-fcb67ded6b.png
 *
 *  V šabloně:
 *  {asset 'style.css'} → /assets/css/style-69f2e9a5b1.css
 *  {asset 'app.js'} → /assets/js/app-0854702cec.js
 *  {asset 'logo.png'} → /assets/images/logo-fcb67ded6b.png
 */
class ManifestMapper implements Mapper
{
    private ?array $manifest = null;

    /** Mapování přípon na podsložky */
    private array $typeMapping = [
        'css' => 'css',
        'js' => 'js',
        'png' => 'images',
        'jpg' => 'images',
        'jpeg' => 'images',
        'gif' => 'images',
        'webp' => 'images',
        'svg' => 'images',
        'woff' => 'fonts',
        'woff2' => 'fonts',
        'ttf' => 'fonts',
        'eot' => 'fonts',
    ];

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $basePath,
        private readonly string $manifestFile = 'manifest.json',
        array $customTypeMapping = []
    ) {
        // Možnost přepsat mapping při registraci
        if (!empty($customTypeMapping)) {
            $this->typeMapping = array_merge($this->typeMapping, $customTypeMapping);
        }
    }

    /**
     * Resolves asset z manifestu s automatickým mapováním do podsložek
     */
    public function getAsset(string $reference, array $options = []): Asset
    {
        $this->loadManifest();

        // Normalizuj referenci (odstraň leading slash)
        $reference = ltrim($reference, '/');

        // Je v manifestu?
        if (!isset($this->manifest[$reference])) {
            throw new AssetNotFoundException(
                "Asset '$reference' not found in manifest. Available assets: " .
                implode(', ', array_keys($this->manifest))
            );
        }

        $versionedFile = $this->manifest[$reference];

        // Zjisti typ souboru a podsložku
        $subfolder = $this->getSubfolder($versionedFile);

        // Sestav cesty
        $url = $this->baseUrl . '/' . $subfolder . '/' . $versionedFile;
        $path = $this->basePath . '/' . $subfolder . '/' . $versionedFile;

        if (!is_file($path)) {
            throw new AssetNotFoundException(
                "Asset '$reference' found in manifest as '$versionedFile' but physical file not found: '$path'\n" .
                "Expected in subfolder: '$subfolder'"
            );
        }

        return Helpers::createAssetFromUrl($url, $path);
    }

    /**
     * Určí podsložku podle přípony souboru
     */
    private function getSubfolder(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $this->typeMapping[$extension] ?? '';
    }

    /**
     * Načte manifest (s cache)
     */
    private function loadManifest(): void
    {
        if ($this->manifest !== null) {
            return;
        }

        $manifestPath = $this->basePath . '/' . $this->manifestFile;

        if (!file_exists($manifestPath)) {
            throw new AssetNotFoundException(
                "Manifest file not found: $manifestPath\n" .
                "Did you run 'gulp build' or 'npm run build'?"
            );
        }

        $content = file_get_contents($manifestPath);
        $this->manifest = json_decode($content, true) ?? [];

        if (empty($this->manifest)) {
            throw new AssetNotFoundException(
                "Manifest file is empty or invalid JSON: $manifestPath"
            );
        }
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Vrátí celý manifest (pro debugging)
     */
    public function getManifest(): array
    {
        $this->loadManifest();
        return $this->manifest;
    }
}
