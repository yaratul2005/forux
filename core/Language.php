<?php

namespace Core;

/**
 * Core Language and Internationalization (i18n) Manager
 */
class Language
{
    protected Container $container;
    protected string $locale = 'en';
    protected array $loaded = []; // Loaded dictionaries: [$locale][$module][$file] = array()
    protected array $supportedLocales = ['en', 'es', 'fr'];
    protected static ?Language $instance = null;

    /**
     * Create a new Language manager instance.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        self::$instance = $this;
        $this->locale = $this->detectLocale();
    }

    /**
     * Get the active Language instance.
     */
    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /**
     * Set the current active locale.
     */
    public function setLocale(string $locale): void
    {
        if (in_array($locale, $this->supportedLocales, true)) {
            $this->locale = $locale;
        }
    }

    /**
     * Get the active locale.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Detect the user preferred or browser language.
     */
    public function detectLocale(): string
    {
        $request = $this->container->get(Request::class);

        // 0. Check Query Parameter
        $langParam = $request->input('lang');
        if ($langParam && in_array($langParam, $this->supportedLocales, true)) {
            if (!headers_sent()) {
                setcookie('forux_lang', $langParam, time() + 86400 * 365, '/', '', false, true);
            }
            return $langParam;
        }

        // 1. Check Cookie
        $cookieLang = $request->cookie('forux_lang');
        if ($cookieLang && in_array($cookieLang, $this->supportedLocales, true)) {
            return $cookieLang;
        }

        // 2. Check Database if user is authenticated
        // To avoid circular dependency during bootstrap, we resolve AuthService dynamically
        try {
            $auth = $this->container->has(\Modules\Auth\Services\AuthService::class) 
                ? $this->container->get(\Modules\Auth\Services\AuthService::class) 
                : null;
            
            if ($auth && $auth->check()) {
                $user = $auth->user();
                if (!empty($user['language']) && in_array($user['language'], $this->supportedLocales, true)) {
                    return $user['language'];
                }
            }
        } catch (\Throwable $e) {
            // Silence any bootstrap db dependency errors
        }

        // 3. Check Accept-Language Header
        $acceptLang = $request->header('Accept-Language');
        if ($acceptLang) {
            $parts = explode(',', $acceptLang);
            foreach ($parts as $part) {
                $langPart = explode(';', $part)[0];
                $langCode = strtolower(trim(explode('-', $langPart)[0]));
                if (in_array($langCode, $this->supportedLocales, true)) {
                    return $langCode;
                }
            }
        }

        // 4. Default configuration
        $config = $this->container->get('config');
        return $config['app']['locale'] ?? 'en';
    }

    /**
     * Translate a key with parameter replacements.
     * Key formats:
     * - "auth::messages.login_success" (Module key)
     * - "common.welcome" (Global common key)
     */
    public static function translate(string $key, array $replacements = []): string
    {
        if (!self::$instance) {
            return $key;
        }

        return self::$instance->get($key, $replacements);
    }

    /**
     * Get the translation string.
     */
    public function get(string $key, array $replacements = []): string
    {
        $locale = $this->locale;
        $translation = $this->resolveKey($locale, $key);

        // Fallback to default 'en' if not found in active locale
        if ($translation === null && $locale !== 'en') {
            $translation = $this->resolveKey('en', $key);
        }

        if ($translation === null) {
            return $key; // Return key itself if no match
        }

        // Process placeholders, e.g. ":username"
        foreach ($replacements as $placeholder => $value) {
            $translation = str_replace(':' . ltrim($placeholder, ':'), (string)$value, $translation);
        }

        return $translation;
    }

    /**
     * Resolve the translation string from loaded or file dictionaries.
     */
    protected function resolveKey(string $locale, string $key): ?string
    {
        $module = 'core';
        $rest = $key;

        if (str_contains($key, '::')) {
            $parts = explode('::', $key, 2);
            $module = ucfirst(strtolower($parts[0])); // Normalize module case e.g. "Auth"
            $rest = $parts[1];
        }

        $keyParts = explode('.', $rest);
        if (count($keyParts) < 2) {
            return null;
        }

        $filename = array_shift($keyParts);
        
        $dictionary = $this->loadDictionary($locale, $module, $filename);
        $result = $this->getNested($dictionary, $keyParts);

        return is_string($result) ? $result : null;
    }

    /**
     * Recursively fetch dot-nested key path.
     */
    protected function getNested(array $array, array $keys)
    {
        $current = $array;
        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        return $current;
    }

    /**
     * Load translation file dynamically.
     */
    protected function loadDictionary(string $locale, string $module, string $filename): array
    {
        if (isset($this->loaded[$locale][$module][$filename])) {
            return $this->loaded[$locale][$module][$filename];
        }

        $dictionary = [];
        $filesToCheck = [];

        if ($module === 'core') {
            // Check global override/common folder
            $filesToCheck[] = ROOT_PATH . "/lang/{$locale}/{$filename}.php";
        } else {
            // Check global override folder for module
            $filesToCheck[] = ROOT_PATH . "/lang/{$locale}/{$module}/{$filename}.php";
            // Check module lang folder
            $filesToCheck[] = ROOT_PATH . "/modules/{$module}/lang/{$locale}/{$filename}.php";
        }

        foreach ($filesToCheck as $file) {
            if (file_exists($file)) {
                $content = require $file;
                if (is_array($content)) {
                    $dictionary = array_replace_recursive($dictionary, $content);
                }
            }
        }

        $this->loaded[$locale][$module][$filename] = $dictionary;
        return $dictionary;
    }
}

// Global helper function registration
require_once __DIR__ . '/helpers.php';
