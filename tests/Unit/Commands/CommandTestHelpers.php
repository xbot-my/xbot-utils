<?php

namespace Tests\Unit\Commands;

use RuntimeException;

/**
 * 测试辅助类
 */
class CommandTestHelpers
{
    /**
     * 创建一个返回指定退出码的模拟执行器
     */
    public static function createMockExecutor(int $exitCode = 0): callable
    {
        return fn(string $path, array $args) => $exitCode;
    }

    /**
     * 创建一个抛出异常的模拟执行器
     */
    public static function createThrowingExecutor(string $message): callable
    {
        return fn(string $path, array $args) => throw new RuntimeException($message);
    }

    /**
     * 创建测试用的临时脚本
     */
    public static function createTempScript(string $content, int $exitCode = 0): string
    {
        $fp = tmpfile();
        $path = stream_get_meta_data($fp)['uri'];
        fwrite($fp, "#!/bin/bash\n{$content}\nexit {$exitCode}");
        fclose($fp);
        chmod($path, 0755);
        return $path;
    }
}
