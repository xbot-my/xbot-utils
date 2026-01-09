<?php

declare(strict_types=1);

namespace ExamplePlugin\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'example:hello',
    description: 'Say hello from the example plugin'
)]
class HelloCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Hello from Example Plugin!');
        $io->text('This is a command provided by the example plugin.');
        $io->newLine();

        $io->section('Plugin System Features');
        $io->listing([
            'Dynamic plugin loading',
            'Plugin enable/disable management',
            'Plugin dependency checking',
            'Extensible command registration',
        ]);

        $io->newLine();
        $io->success('Plugin system is working correctly!');

        return Command::SUCCESS;
    }
}
