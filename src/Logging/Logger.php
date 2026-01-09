<?php

declare(strict_types=1);

namespace Xbot\Utils\Logging;

use RuntimeException;

/**
 * 日志记录器
 *
 * 提供文件日志记录功能，支持日志级别和日志轮转
 */
class Logger
{
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';

    private const LOG_LEVELS = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::WARNING => 2,
        self::ERROR => 3,
    ];

    private string $logFile;
    private int $maxFileSize;
    private int $maxFiles;
    private string $minLevel;

    public function __construct(
        string $logFile,
        int $maxFileSize = 10485760, // 10MB
        int $maxFiles = 5,
        string $minLevel = self::INFO
    ) {
        $this->logFile = $logFile;
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        $this->minLevel = $minLevel;

        // 确保日志目录存在
        $this->ensureLogDirectoryExists();
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
     * 记录日志
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array<string, mixed> $context 上下文信息
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // 检查日志级别
        if (!$this->shouldLog($level)) {
            return;
        }

        // 格式化日志消息
        $logLine = $this->formatLogLine($level, $message, $context);

        // 写入日志
        $this->writeLog($logLine);

        // 检查是否需要轮转
        $this->rotateIfNeeded();
    }

    /**
     * 设置最小日志级别
     */
    public function setMinLevel(string $level): void
    {
        if (!isset(self::LOG_LEVELS[$level])) {
            throw new RuntimeException(sprintf('Invalid log level: %s', $level));
        }

        $this->minLevel = $level;
    }

    /**
     * 获取日志文件路径
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * 检查是否应该记录该级别的日志
     */
    private function shouldLog(string $level): bool
    {
        if (!isset(self::LOG_LEVELS[$level])) {
            return false;
        }

        return self::LOG_LEVELS[$level] >= self::LOG_LEVELS[$this->minLevel];
    }

    /**
     * 格式化日志行
     *
     * @param array<string, mixed> $context
     */
    private function formatLogLine(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = $this->formatMessage($message, $context);

        return sprintf('[%s] %s: %s', $timestamp, $level, $formattedMessage);
    }

    /**
     * 格式化消息和上下文
     *
     * @param array<string, mixed> $context
     */
    private function formatMessage(string $message, array $context): string
    {
        if (empty($context)) {
            return $message;
        }

        $contextStr = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return sprintf('%s %s', $message, $contextStr);
    }

    /**
     * 写入日志到文件
     */
    private function writeLog(string $logLine): void
    {
        $result = file_put_contents($this->logFile, $logLine . PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            throw new RuntimeException(sprintf('Failed to write to log file: %s', $this->logFile));
        }
    }

    /**
     * 检查并执行日志轮转
     */
    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        if (filesize($this->logFile) >= $this->maxFileSize) {
            $this->rotateLogFiles();
        }
    }

    /**
     * 执行日志轮转
     */
    private function rotateLogFiles(): void
    {
        // 删除最老的日志文件
        $oldestLog = $this->logFile . '.' . $this->maxFiles;
        if (file_exists($oldestLog)) {
            unlink($oldestLog);
        }

        // 重命名现有的日志文件
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = ($i === 1) ? $this->logFile : $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);

            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }
    }

    /**
     * 确保日志目录存在
     */
    private function ensureLogDirectoryExists(): void
    {
        $logDir = dirname($this->logFile);

        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                throw new RuntimeException(sprintf('Failed to create log directory: %s', $logDir));
            }
        }
    }

    /**
     * 读取日志文件内容
     *
     * @param int $lines 要读取的行数，0 表示全部
     * @return array<string>
     */
    public function readLogs(int $lines = 0): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $content = file_get_contents($this->logFile);
        if ($content === false) {
            return [];
        }

        $logLines = explode(PHP_EOL, trim($content));

        if ($lines > 0) {
            return array_slice($logLines, -$lines);
        }

        return $logLines;
    }

    /**
     * 跟踪日志文件（类似 tail -f）
     *
     * @param callable $callback 每次读取新行时调用的回调函数
     * @param bool $follow 是否持续跟踪
     */
    public function tail(callable $callback, bool $follow = false): void
    {
        $file = fopen($this->logFile, 'r');

        if ($file === false) {
            throw new RuntimeException(sprintf('Failed to open log file: %s', $this->logFile));
        }

        // 移动到文件末尾
        fseek($file, 0, SEEK_END);
        $lastSize = ftell($file);

        try {
            do {
                clearstatcache(true, $this->logFile);
                $currentSize = filesize($this->logFile);

                if ($currentSize > $lastSize) {
                    // 读取新内容
                    fseek($file, $lastSize);
                    $newContent = fread($file, $currentSize - $lastSize);

                    if ($newContent !== false && $newContent !== '') {
                        $newLines = explode(PHP_EOL, trim($newContent));
                        foreach ($newLines as $line) {
                            if ($line !== '') {
                                $callback($line);
                            }
                        }
                    }

                    $lastSize = $currentSize;
                }

                if ($follow) {
                    usleep(100000); // 100ms
                }
            } while ($follow);
        } finally {
            fclose($file);
        }
    }

    /**
     * 清空日志文件
     */
    public function clear(): void
    {
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
    }

    /**
     * 获取所有日志文件（包括轮转的文件）
     *
     * @return array<string>
     */
    public function getAllLogFiles(): array
    {
        $logDir = dirname($this->logFile);
        $baseName = basename($this->logFile);

        $files = glob($logDir . '/' . $baseName . '*');
        if ($files === false) {
            return [];
        }

        // 过滤并排序（最新的在前）
        $baseNamePattern = str_replace('.', '\\.', $baseName);
        $pattern = '/^' . $baseNamePattern . '(\\.\\d+)?$/';

        $filtered = array_filter($files, fn ($file) => preg_match($pattern, basename($file)));
        usort($filtered, fn ($a, $b) => filemtime($b) - filemtime($a));

        return $filtered;
    }
}
