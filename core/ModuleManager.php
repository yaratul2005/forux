<?php

namespace Core;

/**
 * Dynamic Module Loader and Manager
 */
class ModuleManager
{
    protected Container $container;
    protected array $loadedModules = [];

    /**
     * Create a new ModuleManager instance.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Bootstrap all enabled modules.
     */
    public function boot(): void
    {
        $modulesDir = ROOT_PATH . '/modules';
        if (!is_dir($modulesDir)) {
            return;
        }

        // Get list of enabled modules from settings (fallback to all discovered modules)
        $enabledModules = [];
        if ($this->container->has(Settings::class)) {
            $settings = $this->container->get(Settings::class);
            $enabledModulesJson = $settings->get('enabled_modules');
            if ($enabledModulesJson) {
                $enabledModules = json_decode($enabledModulesJson, true);
            }
        }

        // Scan the directory
        $folders = array_diff(scandir($modulesDir), ['.', '..']);

        // Bootstrapping local variables for routes/hooks files
        $container = $this->container;
        $router = $this->container->get(Router::class);
        $hook = $this->container->get(Hook::class);

        foreach ($folders as $folder) {
            $modulePath = $modulesDir . '/' . $folder;
            if (!is_dir($modulePath)) {
                continue;
            }

            $manifestFile = $modulePath . '/module.php';
            if (!file_exists($manifestFile)) {
                continue;
            }

            // If an enabled list is configured, enforce it
            if (!empty($enabledModules) && !in_array($folder, $enabledModules, true)) {
                continue;
            }

            // Load manifest (manifest can define metadata, service bindings, etc.)
            $manifest = require $manifestFile;
            
            // Boot hooks first (so modules can listen to early kernel/routing events)
            $hooksFile = $modulePath . '/hooks.php';
            if (file_exists($hooksFile)) {
                require_once $hooksFile;
            }

            // Boot routes
            $routesFile = $modulePath . '/routes.php';
            if (file_exists($routesFile)) {
                require_once $routesFile;
            }

            $this->loadedModules[$folder] = $manifest;
        }
    }

    /**
     * Get all loaded module manifests.
     *
     * @return array
     */
    public function getLoadedModules(): array
    {
        return $this->loadedModules;
    }
}
