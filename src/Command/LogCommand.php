<?php

declare(strict_types=1);

namespace Xbot\Utils\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Xbot\Utils\Logging\Logger;

#[AsCommand(
    name: 'logs',
    description: 'View and manage xbot logs'
)]
class LogCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setHelp('The <info>logs</info> command allows you to view and manage xbot log files.')
            ->addOption(
                'tail',
                't',
                InputOption::VALUE_NONE,
                'Follow log output in real-time (like tail -f)'
            )
            ->addOption(
                'lines',
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of lines to show',
                '50'
            )
            ->addOption(
                'level',
                'l',
                InputOption::VALUE_REQUIRED,
                'Filter by log level (DEBUG, INFO, WARNING, ERROR)'
            )
            ->addOption(
                'clear',
                'c',
                InputOption::VALUE_NONE,
                'Clear the log file'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Show all log files including rotated ones'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $projectRoot = dirname(__DIR__, 2);
        $logFile = $projectRoot . '/storage/logs/xbot.log';
        $logger = new Logger($logFile);

        // 处理清空日志
        if ($input->getOption('clear')) {
            if (!$io->confirm('Are you sure you want to clear the log file?', false)) {
                $io->note('Log file cleared cancelled');
                return Command::SUCCESS;
            }

            $logger->clear();
            $io->success('Log file cleared');
            return Command::SUCCESS;
        }

        // 显示所有日志文件
        if ($input->getOption('all')) {
            return $this->showAllLogFiles($logger, $io);
        }

        // 跟踪日志模式
        if ($input->getOption('tail')) {
            return $this->tailLogs($logger, $input, $io);
        }

        // 显示日志
        return $this->showLogs($logger, $input, $io);
    }

    /**
     * 显示日志内容
     */
    private function showLogs(Logger $logger, InputInterface $input, SymfonyStyle $io): int
    {
        $lines = (int) $input->getOption('lines');
        $levelFilter = $input->getOption('level');

        if (!file_exists($logger->getLogFile())) {
            $io->note('No log file found');
            return Command::SUCCESS;
        }

        $logLines = $logger->readLogs($lines);

        if (empty($logLines)) {
            $io->note('Log file is empty');
            return Command::SUCCESS;
        }

        // 过滤日志级别
        if ($levelFilter !== null) {
            $logLines = $this->filterByLevel($logLines, $levelFilter);
        }

        // 显示日志
        $io->title('xbot Logs');
        $io->writeln(sprintf('<fg=gray>File: %s</>', $logger->getLogFile()));

        foreach ($logLines as $line) {
            $io->writeln($this->colorizeLogLine($line));
        }

        $io->newLine();
        $io->note(sprintf('Showing %d line(s)', count($logLines)));

        return Command::SUCCESS;
    }

    /**
     * 实时跟踪日志
     */
    private function tailLogs(Logger $logger, InputInterface $input, SymfonyStyle $io): int
    {
        $levelFilter = $input->getOption('level');

        if (!file_exists($logger->getLogFile())) {
            $io->note('No log file found. Waiting for logs...');
        }

        $io->title('Following xbot Logs');
        $io->note('Press Ctrl+C to stop');
        $io->newLine();

        $logger->tail(function ($line) use ($io, $levelFilter) {
            if ($levelFilter !== null && !$this->matchesLevel($line, $levelFilter)) {
                return;
            }

            $io->writeln($this->colorizeLogLine($line));
        }, follow: true);

        return Command::SUCCESS;
    }

    /**
     * 显示所有日志文件
     */
    private function showAllLogFiles(Logger $logger, SymfonyStyle $io): int
    {
        $logFiles = $logger->getAllLogFiles();

        if (empty($logFiles)) {
            $io->note('No log files found');
            return Command::SUCCESS;
        }

        $io->title('All xbot Log Files');

        foreach ($logFiles as $file) {
            $size = file_exists($file) ? $this->formatFileSize(filesize($file)) : '0 B';
            $modified = file_exists($file) ? date('Y-m-d H:i:s', filemtime($file)) : 'N/A';
            $relativePath = basename(dirname($file)) . '/' . basename($file);

            $io->writeln(sprintf('<fg=cyan>%s</>', $relativePath));
            $io->writeln(sprintf('  Size: %s | Modified: %s', $size, $modified));
            $io->newLine();
        }

        $io->note(sprintf('Found %d log file(s)', count($logFiles)));

        return Command::SUCCESS;
    }

    /**
     * 按日志级别过滤
     *
     * @param array<string> $lines
     * @return array<string>
     */
    private function filterByLevel(array $lines, string $level): array
    {
        return array_filter($lines, fn($line) => $this->matchesLevel($line, $level));
    }

    /**
     * 检查日志行是否匹配指定级别
     */
    private function matchesLevel(string $line, string $level): bool
    {
        return str_contains($line, sprintf('] %s:', $level));
    }

    /**
     * 为日志行添加颜色
     */
    private function colorizeLogLine(string $line): string
    {
        // 根据日志级别添加颜色
        if (str_contains($line, '] ERROR:')) {
            return sprintf('<fg=red>%s</>', $line);
        }

        if (str_contains($line, '] WARNING:')) {
            return sprintf('<fg=yellow>%s</>', $line);
        }

        if (str_contains($line, '] INFO:')) {
            return sprintf('<fg=green>%s</>', $line);
        }

        if (str_contains($line, '] DEBUG:')) {
            return sprintf('<fg=gray>%s</>', $line);
        }

        return $line;
    }

    /**
     * 格式化文件大小
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return sprintf('%.2f %s', $bytes, $units[$unitIndex]);
    }
}
