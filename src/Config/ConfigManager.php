<?php

declare(strict_types=1);

namespace Xbot\Utils\Config;

use RuntimeException;

/**
 * 配置管理器
 *
 * 负责读取和写入 JSON 格式的配置文件
 */
class ConfigManager
{
    private const DEFAULT_CONFIG = [
        'output' => [
            'color' => 'auto',  // auto, always, never
            'format' => 'text', // text, json
        ],
        'script' => [
            'timeout' => 300,   // 默认 5 分钟超时
            'path' => './scripts',
        ],
    ];

    private string $globalConfigPath;
    private string $projectConfigPath;
    private array $config = [];

    public function __construct(string $projectRoot)
    {
        // 全局配置文件：~/.xbot.json
        $home = $this->getHomeDirectory();
        $this->globalConfigPath = $home . '/.xbot.json';

        // 项目配置文件：<projectRoot>/.xbot.json
        $this->projectConfigPath = $projectRoot . '/.xbot.json';

        // 加载配置
        $this->loadConfig();
    }

    /**
     * 获取配置值
     *
     * 支持点号分隔的路径，如 'output.color'
     *
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 设置配置值
     *
     * 支持点号分隔的路径，如 'output.color'
     *
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @param bool $global 是否写入全局配置（默认为项目配置）
     * @throws RuntimeException
     */
    public function set(string $key, mixed $value, bool $global = false): void
    {
        $keys = explode('.', $key);

        // 验证配置值
        $this->validateValue($key, $value);

        // 获取最后一个键和父级键
        $lastKey = array_pop($keys);

        // 获取父级配置的引用
        if (empty($keys)) {
            // 根级别配置
            $config = &$this->config;
        } else {
            $config = &$this->getNestedArray($keys);
        }

        // 设置值
        $config[$lastKey] = $value;

        // 保存配置
        $this->saveConfig($global);
    }

    /**
     * 获取所有配置
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * 加载配置文件
     */
    private function loadConfig(): void
    {
        // 从默认配置开始
        $this->config = self::DEFAULT_CONFIG;

        // 加载全局配置
        if (file_exists($this->globalConfigPath)) {
            $globalConfig = $this->loadJsonFile($this->globalConfigPath);
            $this->config = $this->mergeConfig($this->config, $globalConfig);
        }

        // 加载项目配置（覆盖全局配置）
        if (file_exists($this->projectConfigPath)) {
            $projectConfig = $this->loadJsonFile($this->projectConfigPath);
            $this->config = $this->mergeConfig($this->config, $projectConfig);
        }
    }

    /**
     * 保存配置到文件
     *
     * @param bool $global 是否保存到全局配置
     * @throws RuntimeException
     */
    private function saveConfig(bool $global = false): void
    {
        $path = $global ? $this->globalConfigPath : $this->projectConfigPath;

        // 确保目录存在
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new RuntimeException(sprintf('Failed to create directory: %s', $dir));
            }
        }

        // 格式化 JSON 并保存
        $json = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode configuration to JSON');
        }

        $result = file_put_contents($path, $json . "\n");
        if ($result === false) {
            throw new RuntimeException(sprintf('Failed to write configuration to: %s', $path));
        }
    }

    /**
     * 从文件加载 JSON 配置
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    private function loadJsonFile(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read configuration from: %s', $path));
        }

        $config = json_decode($content, true);
        if (!is_array($config)) {
            throw new RuntimeException(sprintf('Invalid JSON in configuration file: %s', $path));
        }

        return $config;
    }

    /**
     * 深度合并配置数组
     *
     * @param array<string, mixed> $base 基础配置
     * @param array<string, mixed> $override 覆盖配置
     * @return array<string, mixed>
     */
    private function mergeConfig(array $base, array $override): array
    {
        $result = $base;

        foreach ($override as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                $result[$key] = $this->mergeConfig($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * 获取嵌套数组的引用
     *
     * @param array<string> $keys 配置键路径
     * @return array<string, mixed>
     */
    private function &getNestedArray(array $keys): mixed
    {
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        return $config;
    }

    /**
     * 验证配置值
     *
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @throws RuntimeException
     */
    private function validateValue(string $key, mixed $value): void
    {
        switch ($key) {
            case 'output.color':
                if (!in_array($value, ['auto', 'always', 'never'], true)) {
                    throw new RuntimeException(
                        'Invalid value for output.color. Must be one of: auto, always, never'
                    );
                }
                break;

            case 'output.format':
                if (!in_array($value, ['text', 'json'], true)) {
                    throw new RuntimeException(
                        'Invalid value for output.format. Must be one of: text, json'
                    );
                }
                break;

            case 'script.timeout':
                if (!is_int($value) || $value < 1) {
                    throw new RuntimeException(
                        'Invalid value for script.timeout. Must be a positive integer'
                    );
                }
                break;

            case 'script.path':
                if (!is_string($value)) {
                    throw new RuntimeException(
                        'Invalid value for script.path. Must be a string'
                    );
                }
                break;
        }
    }

    /**
     * 获取用户主目录
     */
    private function getHomeDirectory(): string
    {
        // 优先使用 HOME 环境变量
        $home = getenv('HOME');
        if ($home !== false && is_dir($home)) {
            return $home;
        }

        // Windows 兼容
        $home = getenv('USERPROFILE');
        if ($home !== false && is_dir($home)) {
            return $home;
        }

        // 备用方案
        return '~';
    }

    /**
     * 获取全局配置文件路径
     */
    public function getGlobalConfigPath(): string
    {
        return $this->globalConfigPath;
    }

    /**
     * 获取项目配置文件路径
     */
    public function getProjectConfigPath(): string
    {
        return $this->projectConfigPath;
    }

    /**
     * 检查全局配置是否存在
     */
    public function hasGlobalConfig(): bool
    {
        return file_exists($this->globalConfigPath);
    }

    /**
     * 检查项目配置是否存在
     */
    public function hasProjectConfig(): bool
    {
        return file_exists($this->projectConfigPath);
    }
}
