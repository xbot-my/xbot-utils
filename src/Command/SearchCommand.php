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
    name: 'search',
    description: 'Search for commands by keyword'
)]
class SearchCommand extends Command
{
    // 所有已注册的命令信息
    private const COMMANDS = [
        'clean' => [
            'description' => 'Clean Laravel project temporary and sensitive data',
            'script' => 'scripts/laravel/clean.sh',
            'category' => 'Laravel',
        ],
        'setup' => [
            'description' => 'Initialize Laravel project setup',
            'script' => 'scripts/laravel/setup.sh',
            'category' => 'Laravel',
        ],
        'sysinfo' => [
            'description' => 'Display system information and environment details',
            'script' => 'scripts/sysinfo.sh',
            'category' => 'System',
        ],
    ];

    protected function configure(): void
    {
        $this
            ->setHelp('The <info>search</info> command allows you to find commands by searching for keywords in their names or descriptions.')
            ->addArgument(
                'keyword',
                InputArgument::REQUIRED,
                'The keyword to search for'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keyword = strtolower($input->getArgument('keyword'));

        $results = $this->searchCommands($keyword);

        if (empty($results)) {
            $io->warning(sprintf('No commands found matching "%s"', $keyword));
            return Command::FAILURE;
        }

        $io->title(sprintf('Search Results for "%s"', $keyword));
        $io->newLine();

        foreach ($results as $name => $info) {
            $io->section(sprintf('<fg=cyan;options=bold>%s</> <fg=gray>[%s]</>', $name, $info['category']));
            $io->writeln($info['description']);
            $io->writeln(sprintf('<fg=gray>Script: %s</>', $info['script']));
            $io->newLine();
        }

        $io->writeln(sprintf('<fg=green>Found %d command(s)</>', count($results)));

        return Command::SUCCESS;
    }

    /**
     * 搜索匹配的命令
     *
     * @return array<string, array{description: string, script: string, category: string}>
     */
    private function searchCommands(string $keyword): array
    {
        $results = [];

        foreach (self::COMMANDS as $name => $info) {
            $searchText = strtolower($name . ' ' . $info['description'] . ' ' . $info['category']);

            if (str_contains($searchText, $keyword)) {
                $results[$name] = $info;
            }
        }

        return $results;
    }
}
