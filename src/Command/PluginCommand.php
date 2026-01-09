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
use Xbot\Utils\Config\ConfigManager;
use Xbot\Utils\Plugin\PluginManager;

#[AsCommand(
    name: 'plugin',
    description: 'Manage xbot plugins'
)]
class PluginCommand extends Command
{
    private const SUBCOMMANDS = ['list', 'enable', 'disable', 'install', 'uninstall', 'info'];

    private ?PluginManager $pluginManager = null;

    protected function configure(): void
    {
        $this
            ->setHelp('The <info>plugin</info> command allows you to manage xbot plugins.')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                sprintf('Action to perform: %s', implode(', ', self::SUBCOMMANDS))
            )
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Plugin name or source path/URL'
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

        // Initialize plugin manager
        $projectRoot = dirname(__DIR__, 2);
        $config = new ConfigManager($projectRoot);
        $this->pluginManager = new PluginManager($projectRoot, $config);

        return match ($action) {
            'list' => $this->doList($input, $io),
            'enable' => $this->doEnable($input, $io),
            'disable' => $this->doDisable($input, $io),
            'install' => $this->doInstall($input, $io),
            'uninstall' => $this->doUninstall($input, $io),
            'info' => $this->doInfo($input, $io),
        };
    }

    private function doList(InputInterface $input, SymfonyStyle $io): int
    {
        $plugins = $this->pluginManager->discoverPlugins();
        $asJson = $input->getOption('json');

        if ($asJson) {
            $io->writeln(json_encode($plugins, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        if (empty($plugins)) {
            $io->warning('No plugins found');
            $io->note('Install plugins using: ./bin/xbot plugin install <path|url>');
            return Command::SUCCESS;
        }

        $io->title('Installed Plugins');
        $io->newLine();

        $rows = [];
        foreach ($plugins as $name => $info) {
            $status = $info['enabled'] ? '<fg=green>Enabled</>' : '<fg=gray>Disabled</>';
            $loaded = $info['loaded'] ? ' <fg=blue>(loaded)</>' : '';

            $rows[] = [
                sprintf('<fg=cyan;options=bold>%s</>', $name),
                $info['version'],
                $info['description'],
                $status . $loaded,
            ];
        }

        $io->table(
            ['Name', 'Version', 'Description', 'Status'],
            $rows
        );

        $io->newLine();
        $io->note(sprintf('Total: %d plugin(s)', count($plugins)));

        return Command::SUCCESS;
    }

    private function doEnable(InputInterface $input, SymfonyStyle $io): int
    {
        $pluginName = $input->getArgument('name');

        if (empty($pluginName)) {
            $io->error('Plugin name is required');
            $io->note('Usage: ./bin/xbot plugin enable <name>');
            return Command::FAILURE;
        }

        try {
            $this->pluginManager->enablePlugin($pluginName);
            $io->success(sprintf('Plugin "%s" has been enabled', $pluginName));
            return Command::SUCCESS;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function doDisable(InputInterface $input, SymfonyStyle $io): int
    {
        $pluginName = $input->getArgument('name');

        if (empty($pluginName)) {
            $io->error('Plugin name is required');
            $io->note('Usage: ./bin/xbot plugin disable <name>');
            return Command::FAILURE;
        }

        try {
            $this->pluginManager->disablePlugin($pluginName);
            $io->success(sprintf('Plugin "%s" has been disabled', $pluginName));
            return Command::SUCCESS;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function doInstall(InputInterface $input, SymfonyStyle $io): int
    {
        $source = $input->getArgument('name');

        if (empty($source)) {
            $io->error('Plugin source is required');
            $io->note('Usage: ./bin/xbot plugin install <path|url>');
            $io->newLine();
            $io->note('Examples:');
            $io->note('  ./bin/xbot plugin install https://github.com/user/xbot-plugin.git');
            $io->note('  ./bin/xbot plugin install /path/to/local/plugin');
            return Command::FAILURE;
        }

        $io->note(sprintf('Installing plugin from: %s', $source));

        try {
            $pluginName = $this->pluginManager->installPlugin($source);
            $io->success(sprintf('Plugin "%s" has been installed', $pluginName));
            $io->note(sprintf('Enable it with: ./bin/xbot plugin enable %s', $pluginName));
            return Command::SUCCESS;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function doUninstall(InputInterface $input, SymfonyStyle $io): int
    {
        $pluginName = $input->getArgument('name');

        if (empty($pluginName)) {
            $io->error('Plugin name is required');
            $io->note('Usage: ./bin/xbot plugin uninstall <name>');
            return Command::FAILURE;
        }

        $io->warning(sprintf('This will remove the plugin "%s" permanently', $pluginName));

        try {
            $this->pluginManager->uninstallPlugin($pluginName);
            $io->success(sprintf('Plugin "%s" has been uninstalled', $pluginName));
            return Command::SUCCESS;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function doInfo(InputInterface $input, SymfonyStyle $io): int
    {
        $pluginName = $input->getArgument('name');

        if (empty($pluginName)) {
            $io->error('Plugin name is required');
            $io->note('Usage: ./bin/xbot plugin info <name>');
            return Command::FAILURE;
        }

        try {
            $info = $this->pluginManager->getPluginInfo($pluginName);

            $io->title(sprintf('Plugin: %s', $info['name']));
            $io->newLine();

            $io->writeln('<fg=blue;options=bold>Version</>');
            $io->writeln($info['version']);
            $io->newLine();

            $io->writeln('<fg=blue;options=bold>Description</>');
            $io->writeln($info['description'] ?: '<fg=gray>No description</>');
            $io->newLine();

            $io->writeln('<fg=blue;options=bold>Author</>');
            $io->writeln($info['author'] ?: '<fg=gray>Unknown</>');
            $io->newLine();

            $io->writeln('<fg=blue;options=bold>Homepage</>');
            $io->writeln($info['homepage'] ?: '<fg=gray>Not specified</>');
            $io->newLine();

            $io->writeln('<fg=blue;options=bold>License</>');
            $io->writeln($info['license'] ?: '<fg=gray>Not specified</>');
            $io->newLine();

            $io->writeln('<fg=blue;options=bold>Status</>');
            $status = $info['enabled'] ? '<fg=green>Enabled</>' : '<fg=gray>Disabled</>';
            $loaded = $info['loaded'] ? ' <fg=blue>(Loaded)</>' : '';
            $io->writeln($status . $loaded);
            $io->newLine();

            if (!empty($info['dependencies'])) {
                $io->writeln('<fg=blue;options=bold>Dependencies</>');
                foreach ($info['dependencies'] as $dep) {
                    $io->writeln(sprintf('  - %s', $dep));
                }
                $io->newLine();
            }

            $io->writeln('<fg=blue;options=bold>Path</>');
            $io->writeln(sprintf('<fg=gray>%s</>', $info['path']));

            return Command::SUCCESS;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
