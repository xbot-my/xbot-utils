<?php

declare(strict_types=1);

namespace ExamplePlugin;

use Xbot\Utils\Plugin\AbstractPlugin;
use Symfony\Component\Console\Application;
use ExamplePlugin\Command\HelloCommand;

class ExamplePlugin extends AbstractPlugin
{
    public function boot(Application $app): void
    {
        // Register plugin commands
        $app->addCommand(new HelloCommand());
    }

    public function onEnable(): void
    {
        // Plugin enable logic
        // For example: create config files, initialize data, etc.
    }

    public function onDisable(): void
    {
        // Plugin disable logic
        // For example: clear cache, close connections, etc.
    }

    public function checkDependencies(): array
    {
        // Check plugin dependencies
        // Return list of unsatisfied dependencies
        return [];
    }
}
