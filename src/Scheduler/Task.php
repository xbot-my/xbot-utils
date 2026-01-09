<?php

declare(strict_types=1);

namespace Xbot\Utils\Scheduler;

/**
 * Scheduled task entity
 */
class Task
{
    private string $id;
    private string $command;
    private string $cronExpression;
    private string $description;
    private bool $enabled;
    private ?string $workingDirectory;
    private ?string $outputFile;
    private ?string $errorFile;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $command,
        string $cronExpression,
        string $description = '',
        bool $enabled = true,
        ?string $workingDirectory = null,
        ?string $outputFile = null,
        ?string $errorFile = null
    ) {
        $this->id = $id;
        $this->command = $command;
        $this->setCronExpression($cronExpression);
        $this->description = $description;
        $this->enabled = $enabled;
        $this->workingDirectory = $workingDirectory;
        $this->outputFile = $outputFile;
        $this->errorFile = $errorFile;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getCronExpression(): string
    {
        return $this->cronExpression;
    }

    public function setCronExpression(string $expression): void
    {
        if (!CronExpression::isValid($expression)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid cron expression: %s', $expression)
            );
        }
        $this->cronExpression = $expression;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function getWorkingDirectory(): ?string
    {
        return $this->workingDirectory;
    }

    public function setWorkingDirectory(?string $directory): void
    {
        $this->workingDirectory = $directory;
    }

    public function getOutputFile(): ?string
    {
        return $this->outputFile;
    }

    public function setOutputFile(?string $file): void
    {
        $this->outputFile = $file;
    }

    public function getErrorFile(): ?string
    {
        return $this->errorFile;
    }

    public function setErrorFile(?string $file): void
    {
        $this->errorFile = $file;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'command' => $this->command,
            'cronExpression' => $this->cronExpression,
            'description' => $this->description,
            'enabled' => $this->enabled,
            'workingDirectory' => $this->workingDirectory,
            'outputFile' => $this->outputFile,
            'errorFile' => $this->errorFile,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    public static function fromArray(array $data): self
    {
        $task = new self(
            $data['id'],
            $data['command'],
            $data['cronExpression'],
            $data['description'] ?? '',
            $data['enabled'] ?? true,
            $data['workingDirectory'] ?? null,
            $data['outputFile'] ?? null,
            $data['errorFile'] ?? null
        );

        if (isset($data['createdAt'])) {
            $task->createdAt = new \DateTimeImmutable($data['createdAt']);
        }

        return $task;
    }

    public function toCrontabEntry(string $xbotPath): string
    {
        $parts = [$this->cronExpression];

        $command = sprintf('%s schedule run %s', $xbotPath, $this->id);

        if ($this->workingDirectory) {
            $command = sprintf('cd %s && %s', $this->workingDirectory, $command);
        }

        if ($this->outputFile) {
            $command .= ' > ' . $this->outputFile;
        } else {
            $command .= ' > /dev/null';
        }

        if ($this->errorFile) {
            $command .= ' 2> ' . $this->errorFile;
        } elseif (!$this->outputFile) {
            $command .= ' 2>&1';
        }

        $parts[] = $command;

        if ($this->description) {
            $parts[] = sprintf('# %s: %s', $this->id, $this->description);
        }

        return implode(' ', $parts);
    }
}
