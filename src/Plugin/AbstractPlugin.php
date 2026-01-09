<?php

declare(strict_types=1);

namespace Xbot\Utils\Plugin;

use Symfony\Component\Console\Application;
use RuntimeException;

/**
 * Abstract Plugin Base Class
 *
 * Provides default implementation for plugin development
 */
abstract class AbstractPlugin implements PluginInterface
{
    protected string $pluginPath;
    protected array $metadata;
    protected ?PluginManager $pluginManager = null;

    public function __construct(string $pluginPath)
    {
        $this->pluginPath = rtrim($pluginPath, '/');
        $this->metadata = $this->loadMetadata();
    }

    /**
     * Set plugin manager reference (for dependency checking)
     */
    public function setPluginManager(PluginManager $manager): void
    {
        $this->pluginManager = $manager;
    }

    /**
     * Load metadata from plugin.json
     */
    protected function loadMetadata(): array
    {
        $metadataFile = $this->pluginPath . '/plugin.json';

        if (!file_exists($metadataFile)) {
            throw new RuntimeException(
                sprintf('Plugin metadata file not found: %s', $metadataFile)
            );
        }

        $content = file_get_contents($metadataFile);
        if ($content === false) {
            throw new RuntimeException(
                sprintf('Failed to read plugin metadata: %s', $metadataFile)
            );
        }

        $metadata = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                sprintf('Invalid plugin metadata JSON in: %s (error: %s)', $metadataFile, json_last_error_msg())
            );
        }

        if (!is_array($metadata)) {
            throw new RuntimeException(
                sprintf('Invalid plugin metadata format in: %s', $metadataFile)
            );
        }

        // Validate required fields
        if (!isset($metadata['name'])) {
            throw new RuntimeException(
                sprintf('Plugin metadata missing required field "name" in: %s', $metadataFile)
            );
        }

        return $metadata;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function onEnable(): void
    {
        // Default empty implementation, subclasses can override
    }

    public function onDisable(): void
    {
        // Default empty implementation, subclasses can override
    }

    public function checkDependencies(): array
    {
        $dependencies = $this->metadata['dependencies'] ?? [];
        $missing = [];

        foreach ($dependencies as $dep) {
            if (!$this->isPluginAvailable($dep)) {
                $missing[] = $dep;
            }
        }

        return $missing;
    }

    /**
     * Check if a plugin is available (installed and enabled)
     */
    protected function isPluginAvailable(string $pluginName): bool
    {
        if ($this->pluginManager === null) {
            return false;
        }

        // Ask the plugin manager to check availability
        return $this->pluginManager->isPluginAvailable($pluginName);
    }

    /**
     * Get plugin name from metadata
     */
    public function getName(): string
    {
        return $this->metadata['name'] ?? 'unknown';
    }

    /**
     * Get plugin version from metadata
     */
    public function getVersion(): string
    {
        return $this->metadata['version'] ?? 'unknown';
    }

    /**
     * Get plugin description from metadata
     */
    public function getDescription(): string
    {
        return $this->metadata['description'] ?? '';
    }
}
