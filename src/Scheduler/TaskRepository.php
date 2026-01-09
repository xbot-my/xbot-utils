<?php

declare(strict_types=1);

namespace Xbot\Utils\Scheduler;

use RuntimeException;

/**
 * Task repository for storing scheduled tasks
 *
 * Stores tasks in JSON file: .xbot/scheduled_tasks.json
 */
class TaskRepository
{
    private const TASKS_FILE = '.xbot/scheduled_tasks.json';

    private string $projectRoot;
    private string $tasksFile;
    private array $tasks = [];

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->tasksFile = $this->projectRoot . '/' . self::TASKS_FILE;
        $this->loadTasks();
    }

    /**
     * @return array<string, Task>
     */
    public function all(): array
    {
        return $this->tasks;
    }

    /**
     * @return array<string, Task>
     */
    public function getEnabledTasks(): array
    {
        return array_filter($this->tasks, fn (Task $task) => $task->isEnabled());
    }

    public function find(string $id): ?Task
    {
        return $this->tasks[$id] ?? null;
    }

    public function save(Task $task): void
    {
        $this->tasks[$task->getId()] = $task;
        $this->persist();
    }

    public function delete(string $id): void
    {
        if (!isset($this->tasks[$id])) {
            throw new RuntimeException(sprintf('Task not found: %s', $id));
        }

        unset($this->tasks[$id]);
        $this->persist();
    }

    public function exists(string $id): bool
    {
        return isset($this->tasks[$id]);
    }

    public function count(): int
    {
        return count($this->tasks);
    }

    public function clear(): void
    {
        $this->tasks = [];
        $this->persist();
    }

    private function loadTasks(): void
    {
        if (!file_exists($this->tasksFile)) {
            $this->tasks = [];
            return;
        }

        $content = file_get_contents($this->tasksFile);
        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read tasks file: %s', $this->tasksFile));
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Invalid JSON in tasks file: %s', $this->tasksFile));
        }

        $this->tasks = [];
        foreach ($data as $taskData) {
            $task = Task::fromArray($taskData);
            $this->tasks[$task->getId()] = $task;
        }
    }

    private function persist(): void
    {
        $dir = dirname($this->tasksFile);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new RuntimeException(sprintf('Failed to create directory: %s', $dir));
            }
        }

        $data = array_map(fn (Task $task) => $task->toArray(), $this->tasks);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode tasks to JSON');
        }

        $result = file_put_contents($this->tasksFile, $json . "\n");
        if ($result === false) {
            throw new RuntimeException(sprintf('Failed to write tasks to: %s', $this->tasksFile));
        }
    }

    public function getTasksFile(): string
    {
        return $this->tasksFile;
    }

    public static function generateTaskId(string $name): string
    {
        $id = strtolower(trim($name));
        $id = preg_replace('/[^a-z0-9]+/', '-', $id);
        $id = trim($id, '-');

        return $id . '-' . time();
    }

    public static function isValidTaskId(string $id): bool
    {
        return preg_match('/^[a-z0-9-]+$/', $id) === 1;
    }
}
