<?php

declare(strict_types=1);

namespace Xbot\Utils\Plugin;

use RuntimeException;
use Symfony\Component\Console\Application;
use Xbot\Utils\Config\ConfigManager;

/**
 * Plugin Manager
 *
 * Handles plugin discovery, loading, enabling, disabling, and lifecycle management
 */
class PluginManager
{
    private const PLUGINS_DIR = 'plugins';
    private const STATE_FILE = '.xbot/plugins.json';

    private string $projectRoot;
    private string $pluginsDir;
    private string $stateFile;
    private ConfigManager $config;

    /** @var array<string, PluginInterface> */
    private array $loadedPlugins = [];

    /** @var array<string, array{name: string, enabled: bool, version: string}> */
    private array $pluginStates = [];

    public function __construct(string $projectRoot, ConfigManager $config)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->pluginsDir = $this->projectRoot . '/' . self::PLUGINS_DIR;
        $this->stateFile = $this->projectRoot . '/' . self::STATE_FILE;
        $this->config = $config;

        $this->ensureDirectoriesExist();
        $this->loadPluginStates();
    }

    /**
     * Discover all available plugins
     *
     * @return array<string, array{name: string, version: string, description: string, enabled: bool, loaded: bool, path: string}>
     */
    public function discoverPlugins(): array
    {
        $plugins = [];

        if (!is_dir($this->pluginsDir)) {
            return $plugins;
        }

        $dirs = glob($this->pluginsDir . '/*/plugin.json');

        if ($dirs === false) {
            return $plugins;
        }

        foreach ($dirs as $metadataFile) {
            try {
                $pluginDir = dirname($metadataFile);
                $metadata = $this->loadPluginMetadata($metadataFile);
                $pluginName = $metadata['name'];

                $plugins[$pluginName] = [
                    'name' => $pluginName,
                    'version' => $metadata['version'] ?? 'unknown',
                    'description' => $metadata['description'] ?? '',
                    'path' => $pluginDir,
                    'enabled' => $this->isPluginEnabled($pluginName),
                    'loaded' => $this->isPluginLoaded($pluginName),
                ];
            } catch (\Exception $e) {
                // Skip invalid plugins
                error_log(sprintf('Skipping invalid plugin at %s: %s', $metadataFile, $e->getMessage()));
            }
        }

        return $plugins;
    }

    /**
     * Load a plugin
     *
     * @throws RuntimeException
     */
    public function loadPlugin(string $pluginName): PluginInterface
    {
        // If already loaded, return cached instance
        if (isset($this->loadedPlugins[$pluginName])) {
            return $this->loadedPlugins[$pluginName];
        }

        $pluginPath = $this->getPluginPath($pluginName);

        if ($pluginPath === null) {
            throw new RuntimeException(sprintf('Plugin not found: %s', $pluginName));
        }

        // Register plugin autoloader
        $this->registerPluginAutoloader($pluginPath);

        // Load plugin metadata
        $metadata = $this->loadPluginMetadata($pluginPath . '/plugin.json');
        $mainClass = $metadata['main'] ?? null;

        if ($mainClass === null) {
            throw new RuntimeException(
                sprintf('Plugin "%s" does not define a main class', $pluginName)
            );
        }

        // Instantiate plugin
        if (!class_exists($mainClass)) {
            throw new RuntimeException(
                sprintf('Plugin main class not found: %s', $mainClass)
            );
        }

        $plugin = new $mainClass($pluginPath);

        if (!$plugin instanceof PluginInterface) {
            throw new RuntimeException(
                sprintf('Plugin class must implement PluginInterface: %s', $mainClass)
            );
        }

        // Inject plugin manager for dependency checking
        if ($plugin instanceof AbstractPlugin) {
            $plugin->setPluginManager($this);
        }

        // Check dependencies
        $missingDependencies = $plugin->checkDependencies();
        if (!empty($missingDependencies)) {
            throw new RuntimeException(
                sprintf(
                    'Plugin "%s" has missing dependencies: %s',
                    $pluginName,
                    implode(', ', $missingDependencies)
                )
            );
        }

        $this->loadedPlugins[$pluginName] = $plugin;

        return $plugin;
    }

    /**
     * Boot all enabled plugins
     */
    public function bootPlugins(Application $app): void
    {
        $plugins = $this->discoverPlugins();

        foreach ($plugins as $name => $info) {
            if ($info['enabled']) {
                try {
                    $plugin = $this->loadPlugin($name);
                    $plugin->boot($app);
                } catch (\Exception $e) {
                    // Log error but continue loading other plugins
                    error_log(sprintf('Failed to boot plugin "%s": %s', $name, $e->getMessage()));
                }
            }
        }
    }

    /**
     * Enable a plugin
     */
    public function enablePlugin(string $pluginName): void
    {
        $pluginPath = $this->getPluginPath($pluginName);

        if ($pluginPath === null) {
            throw new RuntimeException(sprintf('Plugin not found: %s', $pluginName));
        }

        // Check dependencies
        $plugin = $this->loadPlugin($pluginName);
        $missingDeps = $plugin->checkDependencies();

        if (!empty($missingDeps)) {
            throw new RuntimeException(
                sprintf(
                    'Cannot enable plugin "%s". Missing dependencies: %s',
                    $pluginName,
                    implode(', ', $missingDeps)
                )
            );
        }

        // Update state
        $this->pluginStates[$pluginName] = [
            'name' => $pluginName,
            'version' => $plugin->getVersion(),
            'enabled' => true,
        ];
        $this->savePluginStates();

        // Call enable callback
        $plugin->onEnable();
    }

    /**
     * Disable a plugin
     */
    public function disablePlugin(string $pluginName): void
    {
        if (!$this->isPluginEnabled($pluginName)) {
            throw new RuntimeException(sprintf('Plugin is not enabled: %s', $pluginName));
        }

        // Check if other plugins depend on this one
        $dependents = $this->findDependentPlugins($pluginName);
        if (!empty($dependents)) {
            throw new RuntimeException(
                sprintf(
                    'Cannot disable plugin "%s". It is required by: %s',
                    $pluginName,
                    implode(', ', $dependents)
                )
            );
        }

        // Call disable callback
        if (isset($this->loadedPlugins[$pluginName])) {
            $this->loadedPlugins[$pluginName]->onDisable();
            unset($this->loadedPlugins[$pluginName]);
        }

        // Update state
        $this->pluginStates[$pluginName]['enabled'] = false;
        $this->savePluginStates();
    }

    /**
     * Install a plugin
     *
     * @param string $source Plugin source (local path or git URL)
     * @return string Installed plugin name
     */
    public function installPlugin(string $source, ?string $pluginName = null): string
    {
        // Determine target directory
        if ($pluginName !== null) {
            $targetDir = $this->pluginsDir . '/' . $pluginName;
        } else {
            // Extract name from source
            if (str_starts_with($source, 'http') || str_starts_with($source, 'git@')) {
                $pluginName = basename($source, '.git');
            } else {
                $pluginName = basename(rtrim($source, '/'));
            }
            $targetDir = $this->pluginsDir . '/' . $pluginName;
        }

        if (is_dir($targetDir)) {
            throw new RuntimeException(sprintf('Plugin already exists: %s', $pluginName));
        }

        // Create target directory
        if (!mkdir($targetDir, 0755, true)) {
            throw new RuntimeException(sprintf('Failed to create plugin directory: %s', $targetDir));
        }

        // Copy or clone plugin files
        if (str_starts_with($source, 'http') || str_starts_with($source, 'git@')) {
            // Git clone
            $exitCode = $this->executeCommand(sprintf('git clone %s %s', $source, $targetDir));
        } else {
            // Local copy
            $exitCode = $this->executeCommand(sprintf('cp -r %s %s', rtrim($source, '/'), $targetDir));
        }

        if ($exitCode !== 0) {
            // Clean up failed installation
            $this->executeCommand(sprintf('rm -rf %s', escapeshellarg($targetDir)));
            throw new RuntimeException(sprintf('Failed to install plugin from: %s', $source));
        }

        // Validate plugin
        $metadataFile = $targetDir . '/plugin.json';
        if (!file_exists($metadataFile)) {
            // Clean up invalid plugin
            $this->executeCommand(sprintf('rm -rf %s', escapeshellarg($targetDir)));
            throw new RuntimeException('Invalid plugin: plugin.json not found');
        }

        $metadata = $this->loadPluginMetadata($metadataFile);
        $installedName = $metadata['name'];

        // Initialize plugin state
        $this->pluginStates[$installedName] = [
            'name' => $installedName,
            'version' => $metadata['version'] ?? 'unknown',
            'enabled' => false,
        ];
        $this->savePluginStates();

        return $installedName;
    }

    /**
     * Uninstall a plugin
     */
    public function uninstallPlugin(string $pluginName): void
    {
        if ($this->isPluginEnabled($pluginName)) {
            throw new RuntimeException(
                sprintf('Cannot uninstall enabled plugin. First disable it: %s', $pluginName)
            );
        }

        $pluginPath = $this->getPluginPath($pluginName);

        if ($pluginPath === null) {
            throw new RuntimeException(sprintf('Plugin not found: %s', $pluginName));
        }

        // Delete plugin files
        $this->executeCommand(sprintf('rm -rf %s', escapeshellarg($pluginPath)));

        // Remove state
        unset($this->pluginStates[$pluginName]);
        $this->savePluginStates();
    }

    /**
     * Get plugin information
     *
     * @return array<string, mixed>
     */
    public function getPluginInfo(string $pluginName): array
    {
        $pluginPath = $this->getPluginPath($pluginName);

        if ($pluginPath === null) {
            throw new RuntimeException(sprintf('Plugin not found: %s', $pluginName));
        }

        $metadata = $this->loadPluginMetadata($pluginPath . '/plugin.json');

        return [
            'name' => $metadata['name'],
            'version' => $metadata['version'] ?? 'unknown',
            'description' => $metadata['description'] ?? '',
            'author' => $metadata['author'] ?? '',
            'homepage' => $metadata['homepage'] ?? '',
            'license' => $metadata['license'] ?? '',
            'dependencies' => $metadata['dependencies'] ?? [],
            'enabled' => $this->isPluginEnabled($pluginName),
            'loaded' => $this->isPluginLoaded($pluginName),
            'path' => $pluginPath,
        ];
    }

    /**
     * Check if a plugin is available (installed and enabled)
     */
    public function isPluginAvailable(string $pluginName): bool
    {
        return $this->isPluginEnabled($pluginName);
    }

    /**
     * Get all loaded plugins
     *
     * @return array<string, PluginInterface>
     */
    public function getLoadedPlugins(): array
    {
        return $this->loadedPlugins;
    }

    // ========== Private Helper Methods ==========

    private function ensureDirectoriesExist(): void
    {
        if (!is_dir($this->pluginsDir)) {
            mkdir($this->pluginsDir, 0755, true);
        }

        $stateDir = dirname($this->stateFile);
        if (!is_dir($stateDir)) {
            mkdir($stateDir, 0755, true);
        }
    }

    private function loadPluginStates(): void
    {
        if (file_exists($this->stateFile)) {
            $content = file_get_contents($this->stateFile);
            if ($content !== false) {
                $states = json_decode($content, true);
                if (is_array($states)) {
                    $this->pluginStates = $states;
                }
            }
        }
    }

    private function savePluginStates(): void
    {
        $json = json_encode($this->pluginStates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode plugin states to JSON');
        }
        file_put_contents($this->stateFile, $json . "\n");
    }

    private function loadPluginMetadata(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read plugin metadata: %s', $path));
        }

        $metadata = json_decode($content, true);

        if (!is_array($metadata)) {
            throw new RuntimeException(sprintf('Invalid plugin metadata: %s', $path));
        }

        return $metadata;
    }

    private function getPluginPath(string $pluginName): ?string
    {
        $path = $this->pluginsDir . '/' . $pluginName;
        return is_dir($path) ? $path : null;
    }

    private function isPluginEnabled(string $pluginName): bool
    {
        return isset($this->pluginStates[$pluginName]) &&
            $this->pluginStates[$pluginName]['enabled'] === true;
    }

    private function isPluginLoaded(string $pluginName): bool
    {
        return isset($this->loadedPlugins[$pluginName]);
    }

    private function registerPluginAutoloader(string $pluginPath): void
    {
        $metadataFile = $pluginPath . '/plugin.json';
        $metadata = $this->loadPluginMetadata($metadataFile);
        $autoload = $metadata['autoload'] ?? [];

        if (isset($autoload['psr-4'])) {
            foreach ($autoload['psr-4'] as $namespace => $path) {
                $fullPath = $pluginPath . '/' . rtrim($path, '/');

                spl_autoload_register(function ($class) use ($namespace, $fullPath) {
                    if (str_starts_with($class, $namespace)) {
                        $relativeClass = substr($class, strlen($namespace));
                        $file = $fullPath . '/' . str_replace('\\', '/', $relativeClass) . '.php';

                        if (file_exists($file)) {
                            require_once $file;
                        }
                    }
                });
            }
        }
    }

    private function findDependentPlugins(string $pluginName): array
    {
        $dependents = [];
        $plugins = $this->discoverPlugins();

        foreach ($plugins as $name => $info) {
            if ($name === $pluginName) {
                continue;
            }

            $metadata = $this->loadPluginMetadata($info['path'] . '/plugin.json');
            $dependencies = $metadata['dependencies'] ?? [];

            if (in_array($pluginName, $dependencies, true)) {
                $dependents[] = $name;
            }
        }

        return $dependents;
    }

    private function executeCommand(string $command): int
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            return 1;
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }
}
