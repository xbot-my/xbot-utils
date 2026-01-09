<?php

declare(strict_types=1);

namespace Xbot\Utils\Plugin;

use Symfony\Component\Console\Application;

/**
 * Plugin Interface
 *
 * All plugins must implement this interface to define their lifecycle and metadata
 */
interface PluginInterface
{
    /**
     * Boot the plugin
     *
     * Called when the plugin is loaded. Use this to register commands, services, etc.
     */
    public function boot(Application $app): void;

    /**
     * Get plugin metadata
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * Callback when plugin is enabled
     */
    public function onEnable(): void;

    /**
     * Callback when plugin is disabled
     */
    public function onDisable(): void;

    /**
     * Check if plugin dependencies are satisfied
     *
     * @return array<string> List of missing dependencies, empty array if all satisfied
     */
    public function checkDependencies(): array;
}
