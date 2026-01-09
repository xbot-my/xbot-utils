<?php

declare(strict_types=1);

namespace Xbot\Utils\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Xbot\Utils\Config\ConfigManager;
use Xbot\Utils\Logging\Logger;

#[AsCommand(
    name: 'logs',
    description: 'View and manage xbot logs'
)]
class LogCommand extends Command
{
    private const VALID_LEVELS = [
        Logger::DEBUG,
        Logger::INFO,
        Logger::WARNING,
        Logger::ERROR,
        Logger::CRITICAL,
    ];

    private ?ConfigManager $configManager = null;

    protected function configure(): void
    {
        $this
            ->setHelp('The <info>logs</info> command allows you to view and manage xbot application logs.')
            ->addOption(
                'tail',
                't',
                InputOption::VALUE_NONE,
                'Follow log output in real-time (like tail -f)'
            )
            ->addOption(
                'lines',
                'n',
                InputOption::VALUE_REQUIRED,
                'Number of lines to display',
                '50'
            )
            ->addOption(
                'level',
                'l',
                InputOption::VALUE_REQUIRED,
                sprintf('Filter by log level: %s', implode(', ', self::VALID_LEVELS))
            )
            ->addOption(
                'clear',
                'c',
                InputOption::VALUE_NONE,
                'Clear all log files'
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_NONE,
                'Show log file path'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 初始化配置管理器
        $this->configManager = new ConfigManager(dirname(__DIR__, 2));

        // 创建 Logger 实例获取日志路径
        $logger = new Logger(dirname(__DIR__, 2));
        $logger->setConfig($this->configManager);

        // 处理 --path 选项
        if ($input->getOption('path')) {
            $io->writeln($logger->getLogPath());
            return Command::SUCCESS;
        }

        // 处理 --clear 选项
        if ($input->getOption('clear')) {
            return $this->clearLogs($io, $logger);
        }

        // 处理 --tail 选项
        if ($input->getOption('tail')) {
            return $this->tailLogs($input, $io, $logger);
        }

        // 默认显示日志
        return $this->displayLogs($input, $io, $logger);
    }

    /**
     * 显示日志内容
     */
    private function displayLogs(InputInterface $input, SymfonyStyle $io, Logger $logger): int
    {
        $logPath = $logger->getLogPath();

        if (!file_exists($logPath)) {
            $io->warning('No log file found');
            return Command::SUCCESS;
        }

        // 获取行数
        $lines = (int) $input->getOption('lines');
        $level = $input->getOption('level');

        // 验证日志级别
        if ($level !== null && !in_array(strtoupper($level), self::VALID_LEVELS, true)) {
            $io->error(sprintf(
                'Invalid log level "%s". Valid levels are: %s',
                $level,
                implode(', ', self::VALID_LEVELS)
            ));
            return Command::FAILURE;
        }

        // 读取日志文件
        $logContent = file_get_contents($logPath);
        if ($logContent === false) {
            $io->error('Failed to read log file');
            return Command::FAILURE;
        }

        // 按行分割
        $logLines = explode(PHP_EOL, trim($logContent));

        // 应用级别过滤
        if ($level !== null) {
            $logLines = $this->filterByLevel($logLines, strtoupper($level));
        }

        // 获取最后 N 行
        $logLines = array_slice($logLines, -$lines);

        // 显示日志
        if (empty($logLines)) {
            $io->warning('No log entries found');
            return Command::SUCCESS;
        }

        $io->title('XBot Logs');
        $io->text(sprintf('Showing last %d entries from: %s', count($logLines), $logPath));
        $io->newLine();

        foreach ($logLines as $line) {
            $io->writeln($this->colorizeLogLine($line));
        }

        return Command::SUCCESS;
    }

    /**
     * 实时跟踪日志（类似 tail -f）
     */
    private function tailLogs(InputInterface $input, SymfonyStyle $io, Logger $logger): int
    {
        $logPath = $logger->getLogPath();

        if (!file_exists($logPath)) {
            $io->warning('No log file found');
            return Command::SUCCESS;
        }

        $level = $input->getOption('level');

        // 验证日志级别
        if ($level !== null && !in_array(strtoupper($level), self::VALID_LEVELS, true)) {
            $io->error(sprintf(
                'Invalid log level "%s". Valid levels are: %s',
                $level,
                implode(', ', self::VALID_LEVELS)
            ));
            return Command::FAILURE;
        }

        $io->text(sprintf('Following log file: %s (Press Ctrl+C to stop)', $logPath));
        $io->newLine();

        // 获取初始文件大小
        clearstatcache(true, $logPath);
        $lastSize = filesize($logPath);

        while (true) {
            clearstatcache(true, $logPath);
            $currentSize = filesize($logPath);

            // 如果文件不存在（可能被删除）
            if ($currentSize === false) {
                usleep(500000); // 500ms
                continue;
            }

            // 如果文件被重新创建（轮转等）
            if ($currentSize < $lastSize) {
                $io->writeln('<fg=yellow>Log file rotated. Starting from beginning...</>');
                $lastSize = 0;
            }

            // 如果文件有新内容
            if ($currentSize > $lastSize) {
                $fp = fopen($logPath, 'r');
                if ($fp === false) {
                    $io->error('Failed to open log file');
                    return Command::FAILURE;
                }

                fseek($fp, $lastSize);

                while (!feof($fp)) {
                    $line = fgets($fp);
                    if ($line === false) {
                        continue;
                    }

                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    // 应用级别过滤
                    if ($level !== null && !$this->matchesLevel($line, strtoupper($level))) {
                        continue;
                    }

                    $io->writeln($this->colorizeLogLine($line));
                }

                $lastSize = ftell($fp);
                fclose($fp);
            }

            // 短暂休眠避免占用过多 CPU
            usleep(100000); // 100ms
        }

        return Command::SUCCESS;
    }

    /**
     * 清除所有日志文件
     */
    private function clearLogs(SymfonyStyle $io, Logger $logger): int
    {
        $logFiles = $logger->getLogFiles();

        if (empty($logFiles)) {
            $io->warning('No log files found');
            return Command::SUCCESS;
        }

        // 确认删除
        $confirm = $io->confirm(
            sprintf('Are you sure you want to delete %d log file(s)?', count($logFiles)),
            false
        );

        if (!$confirm) {
            $io->text('Operation cancelled');
            return Command::SUCCESS;
        }

        // 删除文件
        $deleted = 0;
        foreach ($logFiles as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }

        $io->success(sprintf('Deleted %d log file(s)', $deleted));

        return Command::SUCCESS;
    }

    /**
     * 按日志级别过滤
     */
    private function filterByLevel(array $lines, string $level): array
    {
        return array_filter($lines, function ($line) use ($level) {
            return $this->matchesLevel($line, $level);
        });
    }

    /**
     * 检查日志行是否匹配指定级别
     */
    private function matchesLevel(string $line, string $level): bool
    {
        // 匹配格式: [timestamp] channel.LEVEL: message
        $pattern = sprintf('/\.%s:/', preg_quote($level, '/'));
        return preg_match($pattern, $line) === 1;
    }

    /**
     * 为日志行添加颜色
     */
    private function colorizeLogLine(string $line): string
    {
        // DEBUG: 灰色
        if (strpos($line, '.DEBUG:') !== false) {
            return sprintf('<fg=gray>%s</>', $line);
        }

        // INFO: 绿色
        if (strpos($line, '.INFO:') !== false) {
            return sprintf('<fg=green>%s</>', $line);
        }

        // WARNING: 黄色
        if (strpos($line, '.WARNING:') !== false) {
            return sprintf('<fg=yellow>%s</>', $line);
        }

        // ERROR: 红色
        if (strpos($line, '.ERROR:') !== false) {
            return sprintf('<fg=red>%s</>', $line);
        }

        // CRITICAL: 红色加粗
        if (strpos($line, '.CRITICAL:') !== false) {
            return sprintf('<fg=red;options=bold>%s</>', $line);
        }

        return $line;
    }
}
