<?php

declare(strict_types=1);

namespace Xbot\Utils\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Xbot\Utils\executeScript;
use Xbot\Utils\ScriptExecutor;
use Xbot\Utils\executeScriptWithResult;
use Xbot\Utils\Config\ConfigManager;
use Xbot\Utils\Logging\Logger;

abstract class BaseScriptCommand extends Command
{
    protected $scriptExecutor = null;

    // 默认超时时间（秒），子类可以覆盖
    protected int $scriptTimeout = 300;

    // 配置管理器实例
    private static ?ConfigManager $configManager = null;

    // 日志记录器实例
    private ?Logger $logger = null;

    abstract protected function getScriptPath(): string;
    abstract protected function getStartMessage(): string;
    abstract protected function getSuccessMessage(): string;

    /**
     * 获取配置管理器实例
     */
    protected function getConfig(): ConfigManager
    {
        if (self::$configManager === null) {
            $projectRoot = dirname(__DIR__, 2);
            self::$configManager = new ConfigManager($projectRoot);
        }

        return self::$configManager;
    }

    /**
     * 设置脚本执行器（用于测试）
     */
    public function setScriptExecutor(?callable $executor): void
    {
        $this->scriptExecutor = $executor;
    }

    /**
     * 获取脚本超时时间（秒）
     * 优先从配置读取，否则使用默认值
     */
    protected function getScriptTimeout(): int
    {
        return $this->getConfig()->get('script.timeout', $this->scriptTimeout);
    }

    /**
     * 设置脚本超时时间（用于测试）
     */
    public function setScriptTimeout(int $timeout): void
    {
        $this->scriptTimeout = max(1, $timeout);
    }

    /**
     * 重置配置管理器（主要用于测试）
     */
    public static function resetConfigManager(): void
    {
        self::$configManager = null;
    }

    /**
     * 获取日志记录器实例
     */
    protected function getLogger(): Logger
    {
        if ($this->logger === null) {
            $projectRoot = dirname(__DIR__, 2);
            $this->logger = new Logger($projectRoot, $this->getName());
            $this->logger->setConfig($this->getConfig());
        }

        return $this->logger;
    }

    /**
     * 重置日志记录器（主要用于测试）
     */
    public function resetLogger(): void
    {
        $this->logger = null;
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

        // 记录命令开始
        $startTime = microtime(true);
        $this->getLogger()->logCommandStart($this->getName(), $args);

        try {
            $exitCode = $this->executeScript($scriptPath, $args);

            $io->newLine();

            if ($exitCode === 0) {
                $duration = microtime(true) - $startTime;
                $this->getLogger()->logCommandSuccess($this->getName(), $duration);

                $io->success($this->getSuccessMessage());
                return Command::SUCCESS;
            } else {
                $this->getLogger()->logCommandFailure($this->getName(), $exitCode, 'Script returned non-zero exit code');
                $io->error(sprintf('Command failed with exit code: %d', $exitCode));
                return $exitCode;
            }
        } catch (\RuntimeException $e) {
            $this->getLogger()->logCommandFailure($this->getName(), Command::FAILURE, $e->getMessage());
            $this->getLogger()->error(sprintf('Exception: %s', $e->getMessage()), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 执行脚本
     *
     * 如果设置了自定义执行器，使用自定义执行器
     * 否则使用默认的 executeScript 函数
     */
    protected function executeScript(string $scriptPath, array $args): int
    {
        if ($this->scriptExecutor !== null) {
            return ($this->scriptExecutor)($scriptPath, $args);
        }

        // 使用相对路径执行脚本
        $relativeScriptPath = $this->getScriptPath();
        return executeScript($relativeScriptPath, $args);
    }

    /**
     * 使用 ScriptExecutor 直接执行（需要结构化结果时使用）
     *
     * @return array{stdout: string, stderr: string, exitCode: int}
     */
    protected function executeScriptWithFullResult(string $scriptPath, array $args): array
    {
        $projectRoot = dirname(__DIR__, 2);
        $relativePath = $this->getScriptPath();

        $executor = new ScriptExecutor($projectRoot, ['scripts']);
        $executor->setTimeout($this->getScriptTimeout());

        return $executor->execute($relativePath, $args);
    }
}
