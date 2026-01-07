<?php

declare(strict_types=1);

namespace Xbot\Utils\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'setup',
    description: 'Initialize Laravel project setup'
)]
class SetupCommand extends BaseScriptCommand
{
    protected function getScriptPath(): string
    {
        return 'scripts/laravel/setup.sh';
    }

    protected function getStartMessage(): string
    {
        return 'Setting up Laravel project...';
    }

    protected function getSuccessMessage(): string
    {
        return 'Laravel project setup completed successfully!';
    }
}
