<?php

declare(strict_types=1);

namespace Xbot\Utils\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Xbot\Utils\Config\ConfigManager;

#[AsCommand(
    name: 'config',
    description: 'Manage xbot configuration'
)]
class ConfigCommand extends Command
{
    private const SUBCOMMANDS = ['set', 'get', 'list', 'edit'];

    private ?ConfigManager $configManager = null;

    protected function configure(): void
    {
        $this
            ->setHelp('The <info>config</info> command allows you to manage xbot configuration settings.')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                sprintf('Action to perform: %s', implode(', ', self::SUBCOMMANDS))
            )
            ->addArgument(
                'key',
                InputArgument::OPTIONAL,
                'Configuration key (e.g., output.color, script.timeout)'
            )
            ->addArgument(
                'value',
                InputArgument::OPTIONAL,
                'Configuration value to set'
            )
            ->addOption(
                'global',
                'g',
                InputOption::VALUE_NONE,
                'Operate on global configuration (~/.xbot.json) instead of project configuration'
            )
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_NONE,
                'Output configuration as JSON'
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

        // 初始化配置管理器
        $this->configManager = new ConfigManager(dirname(__DIR__, 2));

        return match ($action) {
            'set' => $this->doSet($input, $io),
            'get' => $this->doGet($input, $io),
            'list' => $this->doList($input, $io),
            'edit' => $this->doEdit($input, $io),
        };
    }

    /**
     * 设置配置值
     */
    private function doSet(InputInterface $input, SymfonyStyle $io): int
    {
        $key = $input->getArgument('key');
        $value = $input->getArgument('value');
        $global = $input->getOption('global');

        if (empty($key)) {
            $io->error('Configuration key is required for "set" action');
            $io->note('Usage: ./bin/xbot config set <key> <value>');
            return Command::FAILURE;
        }

        if ($value === null) {
            $io->error('Configuration value is required for "set" action');
            $io->note('Usage: ./bin/xbot config set <key> <value>');
            return Command::FAILURE;
        }

        // 尝试解析值类型
        $parsedValue = $this->parseValue($value);

        try {
            $this->configManager->set($key, $parsedValue, $global);

            $scope = $global ? 'global' : 'project';
            $io->success(sprintf('Set %s config "%s" to: %s', $scope, $key, $this->formatValue($parsedValue)));

            return Command::SUCCESS;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 获取配置值
     */
    private function doGet(InputInterface $input, SymfonyStyle $io): int
    {
        $key = $input->getArgument('key');

        if (empty($key)) {
            $io->error('Configuration key is required for "get" action');
            $io->note('Usage: ./bin/xbot config get <key>');
            return Command::FAILURE;
        }

        $value = $this->configManager->get($key, null);

        if ($value === null) {
            $io->warning(sprintf('Configuration key "%s" not found', $key));
            return Command::FAILURE;
        }

        $io->writeln(sprintf('<fg=blue;options=bold>%s</>', $key));
        $io->writeln($this->formatValue($value));

        return Command::SUCCESS;
    }

    /**
     * 列出所有配置
     */
    private function doList(InputInterface $input, SymfonyStyle $io): int
    {
        $config = $this->configManager->all();
        $asJson = $input->getOption('json');

        if ($asJson) {
            $io->writeln(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $io->title('xbot Configuration');
        $io->newLine();

        // 显示配置文件位置
        $io->writeln('<fg=gray>Configuration Files:</>');
        $io->writeln(sprintf('  Global: %s', $this->configManager->getGlobalConfigPath()));
        $io->writeln(sprintf('  Project: %s', $this->configManager->getProjectConfigPath()));
        $io->newLine();

        // 递归显示配置
        $this->displayConfigArray($io, $config);

        return Command::SUCCESS;
    }

    /**
     * 编辑配置文件
     */
    private function doEdit(InputInterface $input, SymfonyStyle $io): int
    {
        $global = $input->getOption('global');
        $configPath = $global ? $this->configManager->getGlobalConfigPath() : $this->configManager->getProjectConfigPath();

        // 检查 EDITOR 环境变量
        $editor = getenv('EDITOR') ?: getenv('VISUAL') ?: 'vi';

        $io->note(sprintf('Opening %s in %s...', $configPath, $editor));

        // 使用系统编辑器打开文件
        $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open("$editor " . escapeshellarg($configPath), $descriptor, $pipes);

        if (!is_resource($process)) {
            $io->error(sprintf('Failed to open editor: %s', $editor));
            return Command::FAILURE;
        }

        // 等待编辑器关闭
        $exitCode = proc_close($process);

        if ($exitCode === 0) {
            $io->success('Configuration file updated');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('Editor exited with code: %d', $exitCode));
        return Command::FAILURE;
    }

    /**
     * 解析值（自动转换为适当的类型）
     */
    private function parseValue(string $value): mixed
    {
        // 布尔值
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        // null
        if (strtolower($value) === 'null') {
            return null;
        }

        // 数字
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        // JSON
        if (str_starts_with($value, '{') || str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // 字符串
        return $value;
    }

    /**
     * 格式化值用于显示
     */
    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '<fg=green>true</>' : '<fg=red>false</>';
        }

        if (is_null($value)) {
            return '<fg=gray>null</>';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    /**
     * 递归显示配置数组
     */
    private function displayConfigArray(SymfonyStyle $io, array $config, string $prefix = ''): void
    {
        foreach ($config as $key => $value) {
            $fullKey = $prefix . $key;

            if (is_array($value)) {
                $io->writeln(sprintf('<fg=cyan;options=bold>%s</>', $fullKey . ' (object)'));
                $this->displayConfigArray($io, $value, $fullKey . '.');
            } else {
                $formattedValue = $this->formatValue($value);
                $io->writeln(sprintf('<fg=cyan;options=bold>%s</> = %s', $fullKey, $formattedValue));
            }
        }
    }

    /**
     * 提供命令补全建议
     */
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        parent::complete($input, $suggestions);

        // 补全 action 参数：set, get, list, edit
        if ($input->mustSuggestArgumentValuesFor('action')) {
            $suggestions->suggestValues([
                new Suggestion('set', 'Set a configuration value'),
                new Suggestion('get', 'Get a configuration value'),
                new Suggestion('list', 'List all configuration'),
                new Suggestion('edit', 'Edit configuration file'),
            ]);
            return;
        }

        // 补全 key 参数：基于当前配置文件中的键
        if ($input->mustSuggestArgumentValuesFor('key')) {
            $this->configManager = new ConfigManager(dirname(__DIR__, 2));
            $configKeys = $this->getAvailableConfigKeys();
            $suggestions->suggestValues($configKeys);
        }
    }

    /**
     * 获取所有可用的配置键
     *
     * @return array<string>
     */
    private function getAvailableConfigKeys(): array
    {
        $config = $this->configManager->all();
        $keys = [];
        $this->flattenConfigKeys($config, '', $keys);
        return array_keys($keys);
    }

    /**
     * 扁平化配置键数组
     *
     * @param array<mixed> $config
     * @param array<string, bool> $keys
     */
    private function flattenConfigKeys(array $config, string $prefix, array &$keys): void
    {
        foreach ($config as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            if (is_array($value)) {
                $this->flattenConfigKeys($value, $fullKey, $keys);
            } else {
                $keys[$fullKey] = true;
            }
        }
    }
}
