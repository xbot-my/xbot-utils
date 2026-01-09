<?php

declare(strict_types=1);

namespace Xbot\Utils\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'service',
    description: 'Manage Laravel services (web, queue, schedule, horizon, echo)'
)]
class ServiceCommand extends BaseScriptCommand
{
    private const ACTIONS = ['start', 'stop', 'restart', 'status'];

    protected function getScriptPath(): string
    {
        return 'scripts/laravel/service.sh';
    }

    protected function getStartMessage(): string
    {
        return 'Managing Laravel services...';
    }

    protected function getSuccessMessage(): string
    {
        return 'Service operation completed!';
    }

    protected function configure(): void
    {
        $this
            ->setHelp('The <info>service</info> command allows you to manage Laravel services.')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                sprintf('Action to perform: %s', implode(', ', self::ACTIONS))
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        if (!in_array($action, self::ACTIONS, true)) {
            $io->error(sprintf('Invalid action "%s". Valid actions are: %s', $action, implode(', ', self::ACTIONS)));
            return Command::FAILURE;
        }

        // 检查服务脚本是否存在
        $projectRoot = dirname(__DIR__, 2);
        $scriptPath = $projectRoot . '/' . $this->getScriptPath();

        if (!file_exists($scriptPath)) {
            $io->error('Service management script not found');
            $io->note('Make sure scripts/laravel/service.sh exists');
            return Command::FAILURE;
        }

        // 执行服务管理
        $io->title(sprintf('Laravel Service: %s', ucfirst($action)));
        $io->newLine();

        try {
            $exitCode = $this->executeScript($scriptPath, [$action]);

            $io->newLine();

            if ($exitCode === 0) {
                $io->success(sprintf('Service %s completed successfully', $action));
                return Command::SUCCESS;
            } else {
                $io->error(sprintf('Service %s failed with exit code: %d', $action, $exitCode));

                // 如果是因为不在 Laravel 项目中
                if ($exitCode === 1) {
                    $io->note('Make sure you are in a Laravel project directory');
                }

                return $exitCode;
            }
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 执行脚本
     */
    protected function executeScript(string $scriptPath, array $args): int
    {
        if ($this->scriptExecutor !== null) {
            return ($this->scriptExecutor)($scriptPath, $args);
        }

        // 使用相对路径执行脚本
        $relativeScriptPath = $this->getScriptPath();
        return \Xbot\Utils\executeScript($relativeScriptPath, $args);
    }

    /**
     * 提供命令补全建议
     */
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        parent::complete($input, $suggestions);

        // 补全 action 参数：start, stop, restart, status
        if ($input->mustSuggestArgumentValuesFor('action')) {
            $suggestions->suggestValues(self::ACTIONS);
        }
    }
}
