<?php

declare(strict_types=1);

namespace Xbot\Utils;

use RuntimeException;

/**
 * 安全的脚本执行器
 *
 * 使用 proc_open 替代 passthru，提供更安全的脚本执行机制
 */
class ScriptExecutor
{
    private const DEFAULT_TIMEOUT = 300; // 5 分钟默认超时
    private const ALLOWED_PATH_PATTERN = '#^[\w\s/._-]+$#';

    private int $timeout = self::DEFAULT_TIMEOUT;
    private string $projectRoot;
    private array $allowedPaths;

    public function __construct(string $projectRoot, array $allowedPaths = ['scripts'])
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->allowedPaths = $allowedPaths;
    }

    /**
     * 设置执行超时时间（秒）
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = max(1, $timeout);
        return $this;
    }

    /**
     * 执行脚本并返回结果
     *
     * @param string $scriptPath 相对于项目根目录的脚本路径
     * @param array $args 命令行参数
     * @return array{stdout: string, stderr: string, exitCode: int}
     * @throws RuntimeException
     */
    public function execute(string $scriptPath, array $args = []): array
    {
        // 解析并验证完整路径
        $fullPath = $this->resolveFullPath($scriptPath);

        // 验证路径安全性
        $this->validatePath($fullPath);

        // 验证文件可执行性
        $this->validateExecutable($fullPath);

        // 清理和验证参数
        $sanitizedArgs = $this->sanitizeArgs($args);

        // 构建命令
        $command = $this->buildCommand($fullPath, $sanitizedArgs);

        // 执行命令
        return $this->executeCommand($command);
    }

    /**
     * 解析完整路径
     */
    private function resolveFullPath(string $scriptPath): string
    {
        // 如果是绝对路径，直接使用
        if (str_starts_with($scriptPath, '/')) {
            return $scriptPath;
        }

        // 相对于项目根目录解析
        $fullPath = $this->projectRoot . '/' . $scriptPath;

        // 解析路径中的 .. 和 .
        $fullPath = realpath($fullPath);

        if ($fullPath === false) {
            throw new RuntimeException(sprintf('Script path resolution failed: %s', $scriptPath));
        }

        return $fullPath;
    }

    /**
     * 验证路径安全性
     *
     * 防止路径遍历攻击
     */
    private function validatePath(string $fullPath): void
    {
        // 检查路径是否在允许的目录下
        $isAllowed = false;
        foreach ($this->allowedPaths as $allowedPath) {
            $allowedFullPath = $this->projectRoot . '/' . $allowedPath;
            $allowedFullPath = realpath($allowedFullPath) ?: $allowedFullPath;

            if (str_starts_with($fullPath, $allowedFullPath)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            throw new RuntimeException(
                sprintf('Script path is not in allowed directories: %s', $fullPath)
            );
        }

        // 检查路径是否包含可疑字符
        if (!preg_match(self::ALLOWED_PATH_PATTERN, $fullPath)) {
            throw new RuntimeException(
                sprintf('Script path contains invalid characters: %s', $fullPath)
            );
        }
    }

    /**
     * 验证文件可执行性
     */
    private function validateExecutable(string $fullPath): void
    {
        if (!file_exists($fullPath)) {
            throw new RuntimeException(sprintf('Script not found: %s', $fullPath));
        }

        if (!is_file($fullPath)) {
            throw new RuntimeException(sprintf('Path is not a file: %s', $fullPath));
        }

        if (!is_executable($fullPath)) {
            throw new RuntimeException(sprintf('Script is not executable: %s', $fullPath));
        }
    }

    /**
     * 清理和验证参数
     *
     * 防止命令注入攻击
     */
    private function sanitizeArgs(array $args): array
    {
        $sanitized = [];

        foreach ($args as $arg) {
            $argString = (string)$arg;

            // 检查是否包含危险的 shell 元字符
            // 这些字符可能被用于命令注入攻击
            if (preg_match('/[;&|`$()<>]/', $argString)) {
                throw new RuntimeException(
                    sprintf('Argument contains potentially dangerous characters: %s', $argString)
                );
            }

            $sanitized[] = escapeshellarg($argString);
        }

        return $sanitized;
    }

    /**
     * 构建命令字符串
     */
    private function buildCommand(string $fullPath, array $sanitizedArgs): string
    {
        // 转义脚本路径
        $escapedPath = escapeshellarg($fullPath);

        // 如果没有参数，直接返回路径
        if (empty($sanitizedArgs)) {
            return $escapedPath;
        }

        // 拼接参数
        return $escapedPath . ' ' . implode(' ', $sanitizedArgs);
    }

    /**
     * 使用 proc_open 执行命令
     *
     * @return array{stdout: string, stderr: string, exitCode: int}
     * @throws RuntimeException
     */
    private function executeCommand(string $command): array
    {
        // 定义 descriptor spec
        // 0 => stdin, 1 => stdout, 2 => stderr
        $descriptorspec = [
            0 => ['pipe', 'r'],  // 标准输入 - 从这里读取
            1 => ['pipe', 'w'],  // 标准输出 - 写到这里
            2 => ['pipe', 'w'],  // 标准错误 - 写到这里
        ];

        // 打开进程
        $process = proc_open($command, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to execute script: proc_open failed');
        }

        // 关闭标准输入（我们不向脚本输入任何内容）
        fclose($pipes[0]);

        // 读取标准输出和标准错误
        $stdout = $this->readStreamWithTimeout($pipes[1]);
        $stderr = $this->readStreamWithTimeout($pipes[2]);

        // 关闭管道
        fclose($pipes[1]);
        fclose($pipes[2]);

        // 获取退出码
        $exitCode = proc_close($process);

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exitCode' => $exitCode,
        ];
    }

    /**
     * 从流中读取数据，带超时控制
     */
    private function readStreamWithTimeout($pipe): string
    {
        // 设置流为非阻塞模式
        stream_set_blocking($pipe, false);

        $output = '';
        $startTime = time();
        $timeout = $this->timeout;

        while (!feof($pipe)) {
            // 检查超时
            if (time() - $startTime > $timeout) {
                throw new RuntimeException(sprintf('Script execution timeout (%d seconds exceeded)', $timeout));
            }

            // 读取可用数据
            $data = fread($pipe, 8192);
            if ($data === false || $data === '') {
                // 没有数据可用，短暂休眠后重试
                usleep(10000); // 10ms
                continue;
            }

            $output .= $data;
        }

        return $output;
    }

    /**
     * 检查脚本是否存在
     */
    public function scriptExists(string $scriptPath): bool
    {
        try {
            $fullPath = $this->resolveFullPath($scriptPath);
            return file_exists($fullPath) && is_file($fullPath);
        } catch (RuntimeException) {
            return false;
        }
    }
}
