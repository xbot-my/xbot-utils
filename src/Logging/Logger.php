<?php

declare(strict_types=1);

namespace Xbot\Utils\Logging;

use RuntimeException;
use Xbot\Utils\Config\ConfigManager;

/**
 * 日志记录器
 *
 * 提供文件日志记录功能，支持多种日志级别和日志轮转
 */
class Logger
{
    // 日志级别常量
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';
    public const CRITICAL = 'CRITICAL';

    // 日志级别优先级（数字越大优先级越高）
    private const LEVEL_PRIORITY = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::WARNING => 2,
        self::ERROR => 3,
        self::CRITICAL => 4,
    ];

    private string $logPath;
    private string $logFile;
    private ?ConfigManager $config = null;
    private ?string $channel = null;

    /**
     * 构造函数
     *
     * @param string $projectRoot 项目根目录
     * @param string|null $channel 日志通道（用于区分不同命令）
     */
    public function __construct(string $projectRoot, ?string $channel = null)
    {
        $this->channel = $channel;
        $this->logPath = $projectRoot . '/storage/logs';
        $this->logFile = $this->logPath . '/xbot.log';

        // 确保日志目录存在
        $this->ensureLogDirectory();
    }

    /**
     * 设置配置管理器（用于依赖注入）
     */
    public function setConfig(ConfigManager $config): void
    {
        $this->config = $config;
    }

    /**
     * 记录 DEBUG 级别日志
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * 记录 INFO 级别日志
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * 记录 WARNING 级别日志
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * 记录 ERROR 级别日志
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * 记录 CRITICAL 级别日志
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * 通用日志记录方法
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文信息
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // 检查是否启用日志
        if ($this->config && !$this->config->get('logging.enabled', true)) {
            return;
        }

        // 验证日志级别
        if (!isset(self::LEVEL_PRIORITY[$level])) {
            throw new RuntimeException(sprintf('Invalid log level: %s', $level));
        }

        // 检查是否应该记录此级别的日志
        if (!$this->shouldLog($level)) {
            return;
        }

        // 格式化日志条目
        $logEntry = $this->formatLogEntry($level, $message, $context);

        // 写入日志
        $this->writeLog($logEntry);

        // 检查是否需要轮转
        $this->checkRotation();
    }

    /**
     * 记录命令执行开始
     */
    public function logCommandStart(string $commandName, array $args = []): void
    {
        $this->info(sprintf('Command started: %s', $commandName), [
            'arguments' => empty($args) ? 'none' : implode(' ', $args),
        ]);
    }

    /**
     * 记录命令执行成功
     */
    public function logCommandSuccess(string $commandName, float $duration): void
    {
        $this->info(sprintf('Command completed: %s', $commandName), [
            'duration' => sprintf('%.2fs', $duration),
            'status' => 'success',
        ]);
    }

    /**
     * 记录命令执行失败
     */
    public function logCommandFailure(string $commandName, int $exitCode, ?string $error = null): void
    {
        $this->error(sprintf('Command failed: %s', $commandName), [
            'exit_code' => $exitCode,
            'error' => $error ?? 'unknown error',
        ]);
    }

    /**
     * 格式化日志条目
     */
    private function formatLogEntry(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $channel = $this->channel ? '.' . $this->channel : '';

        // 构建基础日志格式
        $logEntry = sprintf(
            "[%s] %s%s.%s: %s",
            $timestamp,
            'xbot' . $channel,
            $level,
            $message
        );

        // 添加上下文信息
        if (!empty($context)) {
            $logEntry .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $logEntry . PHP_EOL;
    }

    /**
     * 写入日志到文件
     */
    private function writeLog(string $logEntry): void
    {
        // 使用 FILE_APPEND 标志追加内容
        // 使用 LOCK_EX 获取独占锁，防止并发写入问题
        $result = file_put_contents(
            $this->logFile,
            $logEntry,
            FILE_APPEND | LOCK_EX
        );

        if ($result === false) {
            throw new RuntimeException(sprintf('Failed to write to log file: %s', $this->logFile));
        }
    }

    /**
     * 检查是否应该记录此级别的日志
     */
    private function shouldLog(string $level): bool
    {
        // 从配置获取最低日志级别
        $minLevel = $this->config
            ? $this->config->get('logging.min_level', self::INFO)
            : self::INFO;

        $minPriority = self::LEVEL_PRIORITY[$minLevel] ?? 0;
        $currentPriority = self::LEVEL_PRIORITY[$level] ?? 0;

        return $currentPriority >= $minPriority;
    }

    /**
     * 检查并执行日志轮转
     */
    private function checkRotation(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        // 获取轮转配置
        $maxSize = $this->config
            ? $this->config->get('logging.max_size', 10 * 1024 * 1024) // 默认 10MB
            : 10 * 1024 * 1024;

        $fileSize = filesize($this->logFile);

        if ($fileSize === false || $fileSize < $maxSize) {
            return;
        }

        // 执行轮转
        $this->rotateLog();
    }

    /**
     * 执行日志轮转
     */
    private function rotateLog(): void
    {
        // 获取最大保留文件数
        $maxFiles = $this->config
            ? $this->config->get('logging.max_files', 5)
            : 5;

        // 删除最老的日志文件
        $oldestLog = sprintf('%s.%d', $this->logFile, $maxFiles);
        if (file_exists($oldestLog)) {
            unlink($oldestLog);
        }

        // 重命名现有日志文件
        for ($i = $maxFiles - 1; $i >= 1; $i--) {
            $oldFile = sprintf('%s.%d', $this->logFile, $i);
            $newFile = sprintf('%s.%d', $this->logFile, $i + 1);

            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }

        // 重命名当前日志文件
        if (file_exists($this->logFile)) {
            $rotatedFile = $this->logFile . '.1';
            rename($this->logFile, $rotatedFile);
        }
    }

    /**
     * 确保日志目录存在
     */
    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->logPath)) {
            if (!mkdir($this->logPath, 0755, true)) {
                throw new RuntimeException(sprintf('Failed to create log directory: %s', $this->logPath));
            }
        }
    }

    /**
     * 获取日志文件路径
     */
    public function getLogPath(): string
    {
        return $this->logFile;
    }

    /**
     * 获取所有日志文件（包括轮转的文件）
     */
    public function getLogFiles(): array
    {
        $files = glob($this->logFile . '*');
        if ($files === false) {
            return [];
        }

        // 按修改时间排序（最新的在前）
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $files;
    }
}
