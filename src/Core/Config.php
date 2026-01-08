<?php

declare(strict_types=1);

namespace Core;

/**
 * Hierarchical Configuration Loader
 *
 * Loads configuration files with hierarchy:
 * 1. config/common/{name}.php
 * 2. config/common/{name}.local.php (if exists)
 * 3. config/shops/{shopTextId}/{name}.php (if exists)
 *
 * Usage:
 * - Config::get('app') - for current shop
 * - Config::getForShop('app', 'florea') - for specific shop
 */
class Config
{
    private static ?string $currentShopTextId = null;
    private static array $cache = [];
    private static string $configDir;

    /**
     * Initialize config system with current shop
     */
    public static function init(string $currentShopTextId): void
    {
        self::$currentShopTextId = $currentShopTextId;
        self::$configDir = dirname(__DIR__, 2) . '/config';
    }

    /**
     * Get configuration for current shop
     *
     * Supports dot notation:
     * - Config::get('app') - returns entire app config
     * - Config::get('app.site.name') - returns nested value
     * - Config::get('app.site.name', 'Default') - with fallback
     *
     * @param mixed $default Default value if key not found
     * @throws \RuntimeException if current shop not set
     */
    public static function get(string $path, mixed $default = null): mixed
    {
        if (self::$currentShopTextId === null) {
            throw new \RuntimeException('Current shop not set. Call Config::init() first.');
        }

        // Split path into config name and nested keys
        // e.g. "app.site.name" -> ["app", "site.name"]
        $parts = explode('.', $path, 2);
        $configName = $parts[0];
        $nestedPath = $parts[1] ?? null;

        // Load the config file
        $config = self::load($configName, self::$currentShopTextId);

        // If no nested path, return entire config
        if ($nestedPath === null) {
            return $config;
        }

        // Navigate nested path
        return self::getNestedValue($config, $nestedPath, $default);
    }

    /**
     * Get configuration for specific shop (for admin use)
     *
     * Supports dot notation:
     * - Config::getForShop('app', 'florea') - returns entire app config
     * - Config::getForShop('app.site.name', 'florea') - returns nested value
     * - Config::getForShop('app.site.name', 'florea', 'Default') - with fallback
     *
     * @param mixed $default Default value if key not found
     */
    public static function getForShop(string $path, string $shopTextId, mixed $default = null): mixed
    {
        // Split path into config name and nested keys
        $parts = explode('.', $path, 2);
        $configName = $parts[0];
        $nestedPath = $parts[1] ?? null;

        // Load the config file
        $config = self::load($configName, $shopTextId);

        // If no nested path, return entire config
        if ($nestedPath === null) {
            return $config;
        }

        // Navigate nested path
        return self::getNestedValue($config, $nestedPath, $default);
    }

    /**
     * Clear cache (useful for testing)
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Get current shop text ID
     */
    public static function getCurrentShopTextId(): ?string
    {
        return self::$currentShopTextId;
    }

    /**
     * Internal loader with caching and hierarchy
     */
    private static function load(string $name, string $shopTextId): array
    {
        $cacheKey = "{$name}:{$shopTextId}";

        // Return from cache if available
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // 1. Load common config
        $config = self::loadFile(self::$configDir . "/common/{$name}.php");

        // 2. Merge common.local config (if exists)
        $localConfig = self::loadFile(self::$configDir . "/common/{$name}.local.php");
        if (!empty($localConfig)) {
            $config = self::mergeRecursive($config, $localConfig);
        }

        // 3. Merge shop-specific config (if exists)
        $shopConfig = self::loadFile(self::$configDir . "/shops/{$shopTextId}/{$name}.php");
        if (!empty($shopConfig)) {
            $config = self::mergeRecursive($config, $shopConfig);
        }

        // Cache the result
        self::$cache[$cacheKey] = $config;

        return $config;
    }

    /**
     * Load single config file
     * Returns empty array if file doesn't exist
     */
    private static function loadFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new \RuntimeException("Config file must return an array: {$path}");
        }

        return $config;
    }

    /**
     * Recursively merge configuration arrays
     *
     * Rules:
     * - Associative arrays are merged recursively
     * - Numeric arrays are completely overridden (not merged)
     * - Scalar values in override replace base values
     */
    private static function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                // Both are arrays - check if associative or numeric
                if (self::isAssociativeArray($value) && self::isAssociativeArray($base[$key])) {
                    // Both associative - merge recursively
                    $base[$key] = self::mergeRecursive($base[$key], $value);
                } else {
                    // At least one is numeric - override completely
                    $base[$key] = $value;
                }
            } else {
                // Scalar value or one side is not array - override
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Check if array is associative (has string keys)
     */
    private static function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Get nested value from array using dot notation
     *
     * @param array $array Source array
     * @param string $path Dot-separated path (e.g., "site.name")
     * @param mixed $default Default value if path not found
     * @return mixed
     */
    private static function getNestedValue(array $array, string $path, mixed $default = null): mixed
    {
        $keys = explode('.', $path);

        foreach ($keys as $key) {
            if (!is_array($array) || !array_key_exists($key, $array)) {
                return $default;
            }
            $array = $array[$key];
        }

        return $array;
    }
}