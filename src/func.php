<?php

declare(strict_types=1);

namespace Xbot\Utils;

use RuntimeException;

/**
 * 执行脚本并返回退出码
 *
 * 此函数保持向后兼容，内部使用安全的 ScriptExecutor
 *
 * @param string $scriptPath 相对于项目根目录的脚本路径
 * @param array $args 命令行参数
 * @return int 脚本退出码
 * @throws RuntimeException
 */
function executeScript(string $scriptPath, array $args = []): int
{
    // 获取项目根目录（假设 func.php 在 src/ 目录下）
    $projectRoot = dirname(__DIR__, 2);

    // 创建 ScriptExecutor 实例
    $executor = new ScriptExecutor($projectRoot, ['scripts']);

    // 执行脚本
    $result = $executor->execute($scriptPath, $args);

    // 输出标准输出（保持与 passthru 相同的行为）
    if ($result['stdout'] !== '') {
        echo $result['stdout'];
    }

    // 如果有标准错误输出，显示到 stderr
    if ($result['stderr'] !== '') {
        fwrite(STDERR, $result['stderr']);
    }

    return $result['exitCode'];
}

/**
 * 执行脚本并返回完整结果
 *
 * 返回包含 stdout、stderr 和 exitCode 的结构化结果
 *
 * @param string $scriptPath 相对于项目根目录的脚本路径
 * @param array $args 命令行参数
 * @param int $timeout 执行超时时间（秒）
 * @return array{stdout: string, stderr: string, exitCode: int}
 * @throws RuntimeException
 */
function executeScriptWithResult(string $scriptPath, array $args = [], int $timeout = 300): array
{
    $projectRoot = dirname(__DIR__, 2);

    $executor = new ScriptExecutor($projectRoot, ['scripts']);
    $executor->setTimeout($timeout);

    return $executor->execute($scriptPath, $args);
}
