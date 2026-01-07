<?php

declare(strict_types=1);

namespace Xbot\Utils\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'clean',
    description: 'Clean Laravel project temporary and sensitive data'
)]
class CleanCommand extends BaseScriptCommand
{
    protected function getScriptPath(): string
    {
        return 'scripts/laravel/clean.sh';
    }

    protected function getStartMessage(): string
    {
        return 'Cleaning Laravel project...';
    }

    protected function getSuccessMessage(): string
    {
        return 'Laravel project cleaned successfully!';
    }
}
