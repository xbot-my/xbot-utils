<?php

declare(strict_types=1);

namespace Xbot\Utils;

use RuntimeException;

function executeScript(string $scriptPath, array $args = []): int
{
    if (!file_exists($scriptPath)) {
        throw new RuntimeException(sprintf('Script not found: %s', $scriptPath));
    }

    if (!is_executable($scriptPath)) {
        throw new RuntimeException(sprintf('Script is not executable: %s', $scriptPath));
    }

    $escapedArgs = array_map(
        fn($arg) => escapeshellarg((string)$arg),
        $args
    );

    $command = sprintf('%s %s', $scriptPath, implode(' ', $escapedArgs));
    passthru($command, $exitCode);

    return $exitCode ?? 0;
}
