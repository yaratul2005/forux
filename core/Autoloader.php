<?php

namespace Core;

/**
 * PSR-4 Compliant Autoloader
 */
class Autoloader
{
    /**
     * Map of namespace prefixes to base directories.
     *
     * @var array
     */
    protected static array $prefixes = [];

    /**
     * Register autoloader with SPL autoloader stack.
     *
     * @return void
     */
    public static function register(): void
    {
        spl_autoload_register([self::class, 'loadClass']);
    }

    /**
     * Add a base directory for a namespace prefix.
     *
     * @param string $prefix The namespace prefix.
     * @param string $baseDir The base directory for classes in that namespace.
     * @return void
     */
    public static function addNamespace(string $prefix, string $baseDir): void
    {
        // Normalize namespace prefix
        $prefix = trim($prefix, '\\') . '\\';

        // Normalize base directory with trailing slash
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        // Initialize prefix array if not exists
        if (!isset(self::$prefixes[$prefix])) {
            self::$prefixes[$prefix] = [];
        }

        // Add base directory
        self::$prefixes[$prefix][] = $baseDir;
    }

    /**
     * Loads the class file for a given class name.
     *
     * @param string $class The fully-qualified class name.
     * @return bool True if successfully loaded, false otherwise.
     */
    public static function loadClass(string $class): bool
    {
        $prefix = $class;

        // Work backwards through the namespace parts to find a match
        while (false !== $pos = strrpos($prefix, '\\')) {
            $prefix = substr($class, 0, $pos + 1);
            $relativeClass = substr($class, $pos + 1);

            // Check if directories are registered for this prefix
            if (isset(self::$prefixes[$prefix])) {
                foreach (self::$prefixes[$prefix] as $baseDir) {
                    // Replace namespace separators with directory separators
                    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

                    // If file exists, require it
                    if (file_exists($file)) {
                        require_once $file;
                        return true;
                    }
                }
            }

            // Remove trailing backslash for next iteration
            $prefix = rtrim($prefix, '\\');
        }

        return false;
    }
}
