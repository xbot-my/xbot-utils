<?php

declare(strict_types=1);

namespace Xbot\Utils\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'info',
    description: 'Display detailed information about a command'
)]
class InfoCommand extends Command
{
    // 所有已注册的命令信息
    private const COMMANDS = [
        'clean' => [
            'description' => 'Clean Laravel project temporary and sensitive data',
            'script' => 'scripts/laravel/clean.sh',
            'category' => 'Laravel',
            'usage' => './bin/xbot clean',
            'examples' => [
                './bin/xbot clean',
            ],
        ],
        'setup' => [
            'description' => 'Initialize Laravel project setup',
            'script' => 'scripts/laravel/setup.sh',
            'category' => 'Laravel',
            'usage' => './bin/xbot setup',
            'examples' => [
                './bin/xbot setup',
            ],
        ],
        'sysinfo' => [
            'description' => 'Display system information and environment details',
            'script' => 'scripts/sysinfo.sh',
            'category' => 'System',
            'usage' => './bin/xbot sysinfo',
            'examples' => [
                './bin/xbot sysinfo',
            ],
        ],
    ];

    protected function configure(): void
    {
        $this
            ->setHelp('The <info>info</info> command displays detailed information about a specific command, including its description, usage examples, and related script path.')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The command name to get information about'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $commandName = $input->getArgument('name');

        if (!isset(self::COMMANDS[$commandName])) {
            $io->error(sprintf('Command "%s" not found', $commandName));
            $io->note('Use <info>./bin/xbot list</info> to see all available commands');
            return Command::FAILURE;
        }

        $info = self::COMMANDS[$commandName];

        $io->title(sprintf('Command: %s', $commandName));
        $io->newLine();

        // 基本信息
        $io->writeln('<fg=blue;options=bold>Description</>');
        $io->writeln($info['description']);
        $io->newLine();

        // 分类
        $io->writeln('<fg=blue;options=bold>Category</>');
        $io->writeln($info['category']);
        $io->newLine();

        // 用法
        $io->writeln('<fg=blue;options=bold>Usage</>');
        $io->writeln(sprintf('<fg=green>%s</>', $info['usage']));
        $io->newLine();

        // 示例
        if (!empty($info['examples'])) {
            $io->writeln('<fg=blue;options=bold>Examples</>');
            foreach ($info['examples'] as $example) {
                $io->writeln(sprintf('  <fg=green>%s</>', $example));
            }
            $io->newLine();
        }

        // 脚本路径
        $io->writeln('<fg=blue;options=bold>Script</>');
        $io->writeln(sprintf('<fg=gray>%s</>', $info['script']));
        $io->newLine();

        // 获取帮助
        $io->writeln('<fg=blue;options=bold>More Help</>');
        $io->writeln(sprintf('  Run <info>./bin/xbot %s --help</info> for more information', $commandName));
        $io->newLine();

        return Command::SUCCESS;
    }

    /**
     * 获取所有可用命令列表
     *
     * @return array<string>
     */
    public static function getAvailableCommands(): array
    {
        return array_keys(self::COMMANDS);
    }

    /**
     * 检查命令是否存在
     */
    public static function commandExists(string $name): bool
    {
        return isset(self::COMMANDS[$name]);
    }
}
