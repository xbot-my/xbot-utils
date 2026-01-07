<?php

declare(strict_types=1);

namespace Xbot\Utils\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'sysinfo',
    description: 'Display system information and environment details'
)]
class SysinfoCommand extends BaseScriptCommand
{
    protected function getScriptPath(): string
    {
        return 'scripts/sysinfo.sh';
    }

    protected function getStartMessage(): string
    {
        return 'Gathering system information...';
    }

    protected function getSuccessMessage(): string
    {
        return 'System information retrieved successfully!';
    }
}
