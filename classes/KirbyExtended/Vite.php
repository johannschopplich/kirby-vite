<?php

namespace KirbyExtended;

use Exception;
use Kirby\Data\Data;
use Kirby\Filesystem\F;

class Vite
{
    protected static \KirbyExtended\Vite $instance;
    protected static array $manifest;

    /**
     * Reads and parses the manifest file created by Vite
     *
     * @throws Exception
     */
    protected function useManifest(): array|null
    {
        if (isset(static::$manifest)) {
            return static::$manifest;
        }

        $manifestFile = kirby()->root('index') . '/' . option('kirby-extended.vite.outDir', 'dist') . '/manifest.json';

        if (!F::exists($manifestFile)) {
            if (option('debug')) {
                throw new Exception('manifest.json not found. Run `npm run build` first.');
            }

            return [];
        }

        return static::$manifest = Data::read($manifestFile);
    }

    /**
     * Gets a value of a manifest property for a specific entry
     *
     * @throws Exception
     */
    protected function getManifestProperty(string $entry, string $key = 'file'): string|array
    {
        $manifestEntry = $this->useManifest()[$entry] ?? null;
        if (!$manifestEntry) {
            if (option('debug')) {
                throw new Exception("{$entry} is not a manifest entry");
            }

            return "";
        }

        $value = $manifestEntry[$key] ?? null;
        if (!$value) {
            if (option('debug')) {
                throw new Exception("{$key} not found in manifest entry {$entry}");
            }

            return "";
        }

        return $value;
    }

    /**
     * Gets the URL for the specified file in development mode
     */
    protected function assetDev(string $file): string
    {
        return option('kirby-extended.vite.devServer', 'http://localhost:5173') . '/' . "{$file}";
    }

    /**
     * Gets the URL for the specified file in production mode
     */
    protected function assetProd(string $file): string
    {
        return '/' . option('kirby-extended.vite.outDir', 'dist') . '/' . "{$file}";
    }

    /**
     * Checks for development mode by either `KIRBY_MODE` env var or
     * if a `.lock` file in `/src` exists
     */
    public function isDev(): bool
    {
        if (function_exists('env') && env('KIRBY_MODE') === 'development') {
            return true;
        }

        $lockFile = kirby()->root('base') . '/src/.lock';
        return F::exists($lockFile);
    }

    /**
     * Includes the CSS file for the specified entry in production mode
     *
     * @throws Exception
     */
    public function css(string|null $entry = null, array|null $options = []): string|null
    {
        if ($this->isDev()) {
            return null;
        }

        $entry ??= option('kirby-extended.vite.entry', 'main.js');
        $attr = array_merge($options, [
            'href' => $this->assetProd($this->getManifestProperty($entry, 'css')[0]),
            'rel'  => 'stylesheet'
        ]);

        return '<link ' . attr($attr) . '>';
    }

    /**
     * Includes the JS file for the specified entry and
     * Vite's client in development mode as well
     *
     * @throws Exception
     */
    public function js(string|null $entry = null, array $options = []): string|null
    {
        $entry ??= option('kirby-extended.vite.entry', 'main.js');

        $client = $this->isDev() ? js($this->assetDev('@vite/client'), ['type' => 'module']) : '';
        $file = $this->isDev()
            ? $this->assetDev($entry)
            : $this->assetProd($this->getManifestProperty($entry, 'file'));

        $attr = array_merge($options, [
            'type' => 'module',
            'src' => $file
        ]);

        return $client . '<script ' . attr($attr) . '></script>';
    }

    /**
     * Gets the instance via lazy initialization
     */
    public static function getInstance(): \KirbyExtended\Vite
    {
        return static::$instance ??= new static();
    }
}
