<?php

declare(strict_types=1);

namespace Xbot\Utils\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Xbot\Utils\Scheduler\Scheduler;
use Xbot\Utils\Scheduler\Task;
use Xbot\Utils\Scheduler\TaskRepository;

#[AsCommand(
    name: 'schedule',
    description: 'Manage scheduled tasks (cron jobs)'
)]
class ScheduleCommand extends Command
{
    private const SUBCOMMANDS = ['list', 'add', 'remove', 'run', 'enable', 'disable', 'sync'];

    private ?Scheduler $scheduler = null;

    protected function configure(): void
    {
        $this
            ->setHelp('The <info>schedule</info> command allows you to manage scheduled tasks.')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                sprintf('Action to perform: %s', implode(', ', self::SUBCOMMANDS))
            )
            ->addArgument(
                'task-id',
                InputArgument::OPTIONAL,
                'Task ID (for remove, run, enable, disable actions)'
            )
            ->addOption(
                'id',
                'i',
                InputOption::VALUE_REQUIRED,
                'Task ID (for add action)'
            )
            ->addOption(
                'cron',
                'c',
                InputOption::VALUE_REQUIRED,
                'Cron expression (e.g., "0 0 * * *" for daily at midnight)'
            )
            ->addOption(
                'command',
                'm',
                InputOption::VALUE_REQUIRED,
                'Command to execute'
            )
            ->addOption(
                'description',
                'd',
                InputOption::VALUE_REQUIRED,
                'Task description'
            )
            ->addOption(
                'working-dir',
                'w',
                InputOption::VALUE_REQUIRED,
                'Working directory for the task'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path'
            )
            ->addOption(
                'error',
                'e',
                InputOption::VALUE_REQUIRED,
                'Error file path'
            )
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_NONE,
                'Output as JSON'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        if (!in_array($action, self::SUBCOMMANDS, true)) {
            $io->error(sprintf('Invalid action "%s". Valid actions are: %s', $action, implode(', ', self::SUBCOMMANDS)));
            return Command::FAILURE;
        }

        $this->scheduler = $this->createScheduler();

        return match ($action) {
            'list' => $this->doList($input, $io),
            'add' => $this->doAdd($input, $io),
            'remove' => $this->doRemove($input, $io),
            'run' => $this->doRun($input, $io),
            'enable' => $this->doEnable($input, $io),
            'disable' => $this->doDisable($input, $io),
            'sync' => $this->doSync($input, $io),
        };
    }

    private function createScheduler(): Scheduler
    {
        $projectRoot = dirname(__DIR__, 2);

        $xbotPath = $_SERVER['argv'][0] ?? $projectRoot . '/bin/xbot';

        if (!str_starts_with($xbotPath, '/')) {
            $xbotPath = $projectRoot . '/' . $xbotPath;
        }

        return new Scheduler($projectRoot, $xbotPath);
    }

    private function doList(InputInterface $input, SymfonyStyle $io): int
    {
        $repository = $this->scheduler->getRepository();
        $tasks = $repository->all();
        $asJson = $input->getOption('json');

        if ($asJson) {
            $data = array_map(fn(Task $task) => $task->toArray(), $tasks);
            $io->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        if (empty($tasks)) {
            $io->note('No scheduled tasks found.');
            $io->note('Use "schedule add" to create a new task.');
            return Command::SUCCESS;
        }

        $io->title('Scheduled Tasks');
        $io->newLine();

        $rows = [];
        foreach ($tasks as $task) {
            $nextRun = 'N/A';
            try {
                $nextRunTime = $this->scheduler->getNextRunTime($task->getId());
                if ($nextRunTime !== null) {
                    $nextRun = $nextRunTime->format('Y-m-d H:i:s');
                }
            } catch (\Exception $e) {
                $nextRun = 'Error';
            }

            $rows[] = [
                $task->getId(),
                $task->getCronExpression(),
                $task->getCommand(),
                $task->getDescription() ?: '-',
                $task->isEnabled() ? '<fg=green>✓</>' : '<fg=red>✗</>',
                $nextRun,
            ];
        }

        $io->table(
            ['ID', 'Cron', 'Command', 'Description', 'Enabled', 'Next Run'],
            $rows
        );

        $io->newLine();
        $io->writeln(sprintf('Total: %d task(s)', count($tasks)));

        return Command::SUCCESS;
    }

    private function doAdd(InputInterface $input, SymfonyStyle $io): int
    {
        $taskId = $input->getOption('id');
        $cron = $input->getOption('cron');
        $command = $input->getOption('command');
        $description = $input->getOption('description') ?? '';
        $workingDir = $input->getOption('working-dir');
        $outputFile = $input->getOption('output');
        $errorFile = $input->getOption('error');

        if (!$taskId || !$cron || !$command) {
            $io->section('Add New Scheduled Task');
            $io->newLine();

            if (!$taskId) {
                $defaultId = 'task-' . time();
                $taskId = $io->ask('Task ID', $defaultId);
            }

            if (!$cron) {
                $cron = $io->ask('Cron expression (e.g., "0 0 * * *" for daily at midnight)');
            }

            if (!$command) {
                $command = $io->ask('Command to execute');
            }

            if (!$description) {
                $description = $io->ask('Description (optional)', '');
            }

            $io->newLine();
        }

        if (empty($taskId) || empty($cron) || empty($command)) {
            $io->error('Task ID, cron expression, and command are required');
            $io->note('Usage: ./bin/xbot schedule add --id <id> --cron <expression> --command <command>');
            return Command::FAILURE;
        }

        if (!TaskRepository::isValidTaskId($taskId)) {
            $io->error(sprintf('Invalid task ID: %s', $taskId));
            $io->note('Task ID must contain only lowercase letters, numbers, and hyphens');
            return Command::FAILURE;
        }

        try {
            $task = new Task(
                $taskId,
                $command,
                $cron,
                $description,
                true,
                $workingDir,
                $outputFile,
                $errorFile
            );

            if (!$this->scheduler->validateTask($task)) {
                $io->error('Invalid task configuration');
                return Command::FAILURE;
            }

            $this->scheduler->addTask($task);

            $io->success(sprintf('Task "%s" added successfully!', $taskId));
            $io->newLine();
            $io->writeln(sprintf('  <info>Cron:</info> %s', $cron));
            $io->writeln(sprintf('  <info>Command:</info> %s', $command));
            $io->newLine();
            $io->note('Run "schedule sync" to synchronize with system crontab.');

            return Command::SUCCESS;

        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function doRemove(InputInterface $input, SymfonyStyle $io): int
    {
        $taskId = $input->getArgument('task-id');

        if (empty($taskId)) {
            $io->error('Task ID is required for "remove" action');
            $io->note('Usage: ./bin/xbot schedule remove <task-id>');
            return Command::FAILURE;
        }

        try {
            $repository = $this->scheduler->getRepository();
            $task = $repository->find($taskId);

            if ($task === null) {
                $io->error(sprintf('Task not found: %s', $taskId));
                return Command::FAILURE;
            }

            if (!$io->confirm(sprintf('Are you sure you want to remove task "%s"?', $taskId), false)) {
                $io->note('Operation cancelled.');
                return Command::SUCCESS;
            }

            $this->scheduler->removeTask($taskId);

            $io->success(sprintf('Task "%s" removed successfully!', $taskId));
            $io->note('Run "schedule sync" to update system crontab.');

            return Command::SUCCESS;

        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function doRun(InputInterface $input, SymfonyStyle $io): int
    {
        $taskId = $input->getArgument('task-id');

        if (empty($taskId)) {
            $io->error('Task ID is required for "run" action');
            $io->note('Usage: ./bin/xbot schedule run <task-id>');
            return Command::FAILURE;
        }

        try {
            $io->writeln(sprintf('<fg=blue;options=bold>Running task: %s</>', $taskId));
            $io->newLine();

            $exitCode = $this->scheduler->runTask($taskId);

            $io->newLine();

            if ($exitCode === 0) {
                $io->success(sprintf('Task "%s" completed successfully!', $taskId));
                return Command::SUCCESS;
            } else {
                $io->error(sprintf('Task "%s" failed with exit code: %d', $taskId, $exitCode));
                return $exitCode;
            }

        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function doEnable(InputInterface $input, SymfonyStyle $io): int
    {
        $taskId = $input->getArgument('task-id');

        if (empty($taskId)) {
            $io->error('Task ID is required for "enable" action');
            $io->note('Usage: ./bin/xbot schedule enable <task-id>');
            return Command::FAILURE;
        }

        try {
            $this->scheduler->enableTask($taskId);

            $io->success(sprintf('Task "%s" enabled!', $taskId));
            $io->note('Run "schedule sync" to update system crontab.');

            return Command::SUCCESS;

        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function doDisable(InputInterface $input, SymfonyStyle $io): int
    {
        $taskId = $input->getArgument('task-id');

        if (empty($taskId)) {
            $io->error('Task ID is required for "disable" action');
            $io->note('Usage: ./bin/xbot schedule disable <task-id>');
            return Command::FAILURE;
        }

        try {
            $this->scheduler->disableTask($taskId);

            $io->success(sprintf('Task "%s" disabled!', $taskId));
            $io->note('Run "schedule sync" to update system crontab.');

            return Command::SUCCESS;

        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function doSync(InputInterface $input, SymfonyStyle $io): int
    {
        try {
            $io->writeln('<fg=blue;options=bold>Synchronizing tasks to system crontab...</>');
            $io->newLine();

            $this->scheduler->syncToSystemCrontab();

            $io->success('Tasks synchronized successfully!');
            $io->newLine();

            $repository = $this->scheduler->getRepository();
            $tasks = $repository->getEnabledTasks();

            if (!empty($tasks)) {
                $io->writeln(sprintf('<info>Enabled tasks:</info> %d', count($tasks)));
                foreach ($tasks as $task) {
                    $io->writeln(sprintf('  - %s: %s', $task->getId(), $task->getCronExpression()));
                }
            }

            return Command::SUCCESS;

        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            $io->note('Make sure crontab is installed and you have permission to modify it.');
            return Command::FAILURE;
        }
    }
}
