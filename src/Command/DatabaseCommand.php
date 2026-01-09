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

#[AsCommand(
    name: 'db',
    description: 'Database management commands for Laravel projects'
)]
class DatabaseCommand extends BaseScriptCommand
{
    private const ACTIONS = ['migrate', 'backup', 'restore', 'test'];

    protected function getScriptPath(): string
    {
        return 'scripts/db/manage.sh';
    }

    protected function getStartMessage(): string
    {
        return 'Executing database operation...';
    }

    protected function getSuccessMessage(): string
    {
        return 'Database operation completed!';
    }

    protected function configure(): void
    {
        $this
            ->setHelp('The <info>db</info> command provides database management utilities for Laravel projects.')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                sprintf('Action to perform: %s', implode(', ', self::ACTIONS))
            )
            ->addOption(
                'seed',
                's',
                InputOption::VALUE_NONE,
                'Run database seeder after migration (only for migrate action)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force operation without confirmation'
            )
            ->addOption(
                'file',
                null,
                InputOption::VALUE_REQUIRED,
                'Backup file to restore (only for restore action)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        if (!in_array($action, self::ACTIONS, true)) {
            $io->error(sprintf('Invalid action "%s". Valid actions are: %s', $action, implode(', ', self::ACTIONS)));
            $this->showAvailableActions($io);
            return Command::FAILURE;
        }

        // 检查是否在 Laravel 项目中
        if (!$this->isLaravelProject()) {
            $io->error('Not in a Laravel project directory');
            $io->note('Database commands require a Laravel project with artisan file');
            return Command::FAILURE;
        }

        // 执行数据库操作
        return match ($action) {
            'migrate' => $this->doMigrate($input, $io),
            'backup' => $this->doBackup($input, $io),
            'restore' => $this->doRestore($input, $io),
            'test' => $this->doTest($io),
        };
    }

    /**
     * 执行数据库迁移
     */
    private function doMigrate(InputInterface $input, SymfonyStyle $io): int
    {
        $seed = $input->getOption('seed');
        $force = $input->getOption('force');

        $io->title('Database Migration');
        $io->newLine();

        // 确认操作
        if (!$force && !$io->confirm('Run database migrations?', false)) {
            $io->note('Migration cancelled');
            return Command::SUCCESS;
        }

        try {
            // 运行迁移
            $io->writeln('<fg=cyan>Running migrations...</>');
            $exitCode = $this->runArtisanCommand('migrate', ['--force' => true]);

            if ($exitCode !== 0) {
                $io->error('Migration failed');
                return $exitCode;
            }

            // 如果需要，运行 seeder
            if ($seed) {
                $io->writeln('<fg=cyan>Running seeders...</>');
                $exitCode = $this->runArtisanCommand('db:seed', ['--force' => true]);

                if ($exitCode !== 0) {
                    $io->warning('Seeder failed, but migration completed');
                }
            }

            $io->success('Database migration completed successfully!');
            return Command::SUCCESS;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 备份数据库
     */
    private function doBackup(InputInterface $input, SymfonyStyle $io): int
    {
        $force = $input->getOption('force');

        $io->title('Database Backup');
        $io->newLine();

        // 确认操作
        if (!$force && !$io->confirm('Create database backup?', false)) {
            $io->note('Backup cancelled');
            return Command::SUCCESS;
        }

        try {
            // 创建备份目录
            $projectRoot = dirname(__DIR__, 2);
            $backupDir = $projectRoot . '/storage/backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // 生成备份文件名
            $database = $this->getDatabaseName();
            $timestamp = date('Y-m-d_H-i-s');
            $backupFile = $backupDir . '/' . $database . '_' . $timestamp . '.sql';

            $io->writeln(sprintf('<fg=cyan>Creating backup: %s</>', basename($backupFile)));
            $io->newLine();

            // 创建进度条（模拟进度）
            $progress = $this->createProgress($io, 100);
            $progress->setMessage('Starting backup...');
            $progress->start();

            // 定义进度阶段消息
            $stages = [
                10 => 'Connecting to database...',
                30 => 'Exporting schema...',
                50 => 'Exporting data...',
                70 => 'Compressing...',
                90 => 'Finalizing...',
                100 => 'Completing backup...'
            ];

            // 模拟进度更新
            // 注意：由于 passthru() 是阻塞调用，这里使用简化实现
            foreach ($stages as $step => $message) {
                $progress->setProgress($step);
                $progress->setMessage($message);
                usleep(50000); // 短暂延迟以显示进度
            }

            // 使用 mysqldump 备份（需要根据配置调整）
            $exitCode = $this->runMysqldump($backupFile);

            // 完成进度条
            $this->finishProgress();
            $io->newLine();

            if ($exitCode !== 0) {
                $io->error('Backup failed');
                return $exitCode;
            }

            $io->success(sprintf('Database backed up to: %s', $backupFile));
            $io->note(sprintf('Size: %s', $this->formatFileSize(filesize($backupFile))));

            return Command::SUCCESS;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 恢复数据库
     */
    private function doRestore(InputInterface $input, SymfonyStyle $io): int
    {
        $file = $input->getOption('file');
        $force = $input->getOption('force');

        $io->title('Database Restore');
        $io->newLine();

        if (empty($file)) {
            // 列出可用的备份文件
            $backups = $this->listBackupFiles();

            if (empty($backups)) {
                $io->error('No backup files found');
                $io->note('Place backup files in storage/backups/');
                return Command::FAILURE;
            }

            $io->writeln('<fg=cyan>Available backup files:</>');
            foreach ($backups as $index => $backup) {
                $io->writeln(sprintf('  [%d] %s (%s)', $index + 1, basename($backup), $this->formatFileSize(filesize($backup))));
            }

            $choice = $io->ask('Select backup file (number)', '1');
            $selectedIndex = (int) $choice - 1;

            if (!isset($backups[$selectedIndex])) {
                $io->error('Invalid selection');
                return Command::FAILURE;
            }

            $file = $backups[$selectedIndex];
        }

        // 确认操作
        if (!$force && !$io->confirm(sprintf('Restore database from: %s?', basename($file)), false)) {
            $io->note('Restore cancelled');
            return Command::SUCCESS;
        }

        try {
            $io->writeln('<fg=cyan>Restoring database...</>');

            $exitCode = $this->runMysqlRestore($file);

            if ($exitCode !== 0) {
                $io->error('Restore failed');
                return $exitCode;
            }

            $io->success('Database restored successfully!');
            return Command::SUCCESS;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 测试数据库连接
     */
    private function doTest(SymfonyStyle $io): int
    {
        $io->title('Database Connection Test');
        $io->newLine();

        try {
            $io->writeln('<fg=cyan>Testing database connection...</>');
            $exitCode = $this->runArtisanCommand('db:show', []);

            if ($exitCode === 0) {
                $io->success('Database connection is working!');
                return Command::SUCCESS;
            } else {
                // 如果 db:show 不可用，尝试其他方法
                $exitCode = $this->runArtisanCommand('db:test', []);

                if ($exitCode === 0) {
                    $io->success('Database connection is working!');
                    return Command::SUCCESS;
                }
            }

            $io->warning('Could not verify database connection');
            $io->note('Check your .env database configuration');
            return Command::FAILURE;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 检查是否在 Laravel 项目中
     */
    private function isLaravelProject(): bool
    {
        $projectRoot = dirname(__DIR__, 2);
        return file_exists($projectRoot . '/artisan') && file_exists($projectRoot . '/composer.json');
    }

    /**
     * 获取数据库名称
     */
    private function getDatabaseName(): string
    {
        $projectRoot = dirname(__DIR__, 2);
        $envFile = $projectRoot . '/.env';

        if (!file_exists($envFile)) {
            return 'database';
        }

        $envContent = file_get_contents($envFile);
        preg_match('/DB_DATABASE=(.+)/', $envContent, $matches);

        return $matches[1] ?? 'database';
    }

    /**
     * 列出可用的备份文件
     *
     * @return array<string>
     */
    private function listBackupFiles(): array
    {
        $projectRoot = dirname(__DIR__, 2);
        $backupDir = $projectRoot . '/storage/backups';

        if (!is_dir($backupDir)) {
            return [];
        }

        $files = glob($backupDir . '/*.sql');
        if ($files === false) {
            return [];
        }

        // 按修改时间降序排序
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        return $files;
    }

    /**
     * 运行 mysqldump 备份
     */
    private function runMysqldump(string $outputFile): int
    {
        $projectRoot = dirname(__DIR__, 2);

        // 构建备份命令
        $command = sprintf(
            'php artisan db:backup --file=%s',
            escapeshellarg($outputFile)
        );

        passthru($command, $exitCode);
        return $exitCode ?? 0;
    }

    /**
     * 恢复 MySQL 数据库
     */
    private function runMysqlRestore(string $backupFile): int
    {
        $projectRoot = dirname(__DIR__, 2);

        $command = sprintf(
            'php artisan db:restore --file=%s',
            escapeshellarg($backupFile)
        );

        passthru($command, $exitCode);
        return $exitCode ?? 0;
    }

    /**
     * 运行 Artisan 命令
     */
    private function runArtisanCommand(string $command, array $options = []): int
    {
        $projectRoot = dirname(__DIR__, 2);

        $cmd = 'php artisan ' . $command;
        foreach ($options as $key => $value) {
            if ($value === true) {
                $cmd .= ' ' . $key;
            } elseif ($value !== false) {
                $cmd .= ' ' . $key . '=' . escapeshellarg((string) $value);
            }
        }

        passthru($cmd, $exitCode);
        return $exitCode ?? 0;
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

    /**
     * 显示可用的操作
     */
    private function showAvailableActions(SymfonyStyle $io): void
    {
        $io->section('Available Actions');
        $io->writeln('  <fg=cyan>migrate</>  - Run database migrations');
        $io->writeln('  <fg=cyan>backup</>   - Create database backup');
        $io->writeln('  <fg=cyan>restore</>  - Restore database from backup');
        $io->writeln('  <fg=cyan>test</>     - Test database connection');
    }
}
