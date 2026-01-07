<?php

declare(strict_types=1);

namespace Xbot\Utils\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Xbot\Utils\executeScript;

abstract class BaseScriptCommand extends Command
{
    protected $scriptExecutor = null;

    abstract protected function getScriptPath(): string;
    abstract protected function getStartMessage(): string;
    abstract protected function getSuccessMessage(): string;

    /**
     * 设置脚本执行器（用于测试）
     */
    public function setScriptExecutor(?callable $executor): void
    {
        $this->scriptExecutor = $executor;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = dirname(__DIR__, 2);
        $scriptPath = $projectRoot . '/' . $this->getScriptPath();

        $io->writeln(sprintf('<fg=blue;options=bold>%s</>', $this->getStartMessage()));
        $io->newLine();

        $rawArgs = $_SERVER['argv'] ?? [];
        $args = array_slice($rawArgs, 2);

        try {
            $executor = $this->scriptExecutor ?? fn($path, $args) => executeScript($path, $args);
            $exitCode = $executor($scriptPath, $args);

            $io->newLine();

            if ($exitCode === 0) {
                $io->success($this->getSuccessMessage());
                return Command::SUCCESS;
            } else {
                $io->error(sprintf('Command failed with exit code: %d', $exitCode));
                return $exitCode;
            }
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
