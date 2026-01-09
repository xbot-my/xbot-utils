<?php

declare(strict_types=1);

namespace Xbot\Utils\Output;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Progress Helper
 *
 * Encapsulates Symfony ProgressBar to provide a simple API for displaying task progress
 */
class ProgressHelper
{
    private ?ProgressBar $progressBar = null;
    private OutputInterface $output;

    // Progress bar format definitions
    private const DEFAULT_FORMAT = ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%';
    private const SIMPLE_FORMAT = ' [%bar%] %percent:3s%% %message%';
    private const VERBOSE_FORMAT = ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% %message%';

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Create a progress bar
     *
     * @param int $max Maximum steps
     * @param string $format Format type (default, simple, verbose)
     * @return self
     */
    public function create(int $max, string $format = 'default'): self
    {
        $this->progressBar = new ProgressBar($this->output, $max);

        // Set format
        $formatString = match($format) {
            'simple' => self::SIMPLE_FORMAT,
            'verbose' => self::VERBOSE_FORMAT,
            default => self::DEFAULT_FORMAT,
        };

        $this->progressBar->setFormat($formatString);
        $this->progressBar->setBarCharacter('<fg=green>■</>');
        $this->progressBar->setEmptyBarCharacter('<fg=gray>░</>');
        $this->progressBar->setProgressCharacter('<fg=green>➤</>');

        return $this;
    }

    /**
     * Start the progress bar
     */
    public function start(): self
    {
        if ($this->progressBar !== null) {
            $this->progressBar->start();
        }

        return $this;
    }

    /**
     * Advance the progress bar by specified steps
     *
     * @param int $step Number of steps to advance, default is 1
     * @param string $message Optional progress message
     */
    public function advance(int $step = 1, string $message = ''): self
    {
        if ($this->progressBar !== null) {
            $this->progressBar->advance($step);

            if ($message !== '') {
                $this->progressBar->setMessage($message);
            }
        }

        return $this;
    }

    /**
     * Set current progress (absolute value)
     *
     * @param int $current Current progress
     * @param string $message Optional progress message
     */
    public function setProgress(int $current, string $message = ''): self
    {
        if ($this->progressBar !== null) {
            $this->progressBar->setProgress($current);

            if ($message !== '') {
                $this->progressBar->setMessage($message);
            }
        }

        return $this;
    }

    /**
     * Set progress message
     */
    public function setMessage(string $message): self
    {
        if ($this->progressBar !== null) {
            $this->progressBar->setMessage($message);
        }

        return $this;
    }

    /**
     * Finish the progress bar
     */
    public function finish(): self
    {
        if ($this->progressBar !== null) {
            $this->progressBar->finish();
            $this->output->writeln(''); // Add newline
        }

        return $this;
    }

    /**
     * Get the underlying ProgressBar instance
     * Used for advanced customization
     */
    public function getProgressBar(): ?ProgressBar
    {
        return $this->progressBar;
    }
}
