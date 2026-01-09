<?php

declare(strict_types=1);

namespace Xbot\Utils\Scheduler;

/**
 * Cron expression parser
 *
 * Supports standard 5-part format: minute hour day month weekday
 *
 * Field ranges:
 * - minute: 0-59
 * - hour: 0-23
 * - day: 1-31
 * - month: 1-12 or JAN-DEC
 * - weekday: 0-7 (0 and 7 are Sunday) or SUN-SAT
 *
 * Special characters:
 * - * : all values
 * - , : value list separator
 * - - : range
 * - / : step
 */
class CronExpression
{
    private const MINUTE_FIELD = 0;
    private const HOUR_FIELD = 1;
    private const DAY_FIELD = 2;
    private const MONTH_FIELD = 3;
    private const WEEKDAY_FIELD = 4;

    private const FIELD_RANGES = [
        self::MINUTE_FIELD => [0, 59],
        self::HOUR_FIELD => [0, 23],
        self::DAY_FIELD => [1, 31],
        self::MONTH_FIELD => [1, 12],
        self::WEEKDAY_FIELD => [0, 7],
    ];

    private const MONTH_NAMES = [
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4,
        'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8,
        'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
    ];

    private const WEEKDAY_NAMES = [
        'SUN' => 0, 'MON' => 1, 'TUE' => 2, 'WED' => 3,
        'THU' => 4, 'FRI' => 5, 'SAT' => 6,
    ];

    private string $expression;
    private array $parts;

    public function __construct(string $expression)
    {
        $this->expression = trim($expression);
        $this->parts = $this->parseExpression($this->expression);
    }

    private function parseExpression(string $expression): array
    {
        $parts = preg_split('/\s+/', $expression);

        if (count($parts) !== 5) {
            throw new \InvalidArgumentException(
                sprintf('Invalid cron expression "%s": must have exactly 5 parts', $expression)
            );
        }

        return $parts;
    }

    public static function isValid(string $expression): bool
    {
        try {
            new self($expression);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    public function getParts(): array
    {
        return $this->parts;
    }

    public function getMinute(): string
    {
        return $this->parts[self::MINUTE_FIELD];
    }

    public function getHour(): string
    {
        return $this->parts[self::HOUR_FIELD];
    }

    public function getDay(): string
    {
        return $this->parts[self::DAY_FIELD];
    }

    public function getMonth(): string
    {
        return $this->parts[self::MONTH_FIELD];
    }

    public function getWeekday(): string
    {
        return $this->parts[self::WEEKDAY_FIELD];
    }

    public function getNextRunDate(\DateTimeImmutable $currentTime = null): \DateTimeImmutable
    {
        $currentTime = $currentTime ?? new \DateTimeImmutable();
        $currentTime = $currentTime->modify('+1 minute');

        $maxIterations = 4 * 365 * 24 * 60;
        $iterations = 0;

        while ($iterations < $maxIterations) {
            if ($this->matches($currentTime)) {
                return $currentTime;
            }
            $currentTime = $currentTime->modify('+1 minute');
            $iterations++;
        }

        throw new \RuntimeException('Unable to calculate next run date');
    }

    public function matches(\DateTimeImmutable $date): bool
    {
        return $this->fieldMatches($date->format('i'), $this->getMinute(), self::MINUTE_FIELD)
            && $this->fieldMatches($date->format('H'), $this->getHour(), self::HOUR_FIELD)
            && $this->fieldMatches($date->format('d'), $this->getDay(), self::DAY_FIELD)
            && $this->fieldMatches($date->format('m'), $this->getMonth(), self::MONTH_FIELD)
            && $this->fieldMatches((string)((int)$date->format('w') ?: 7), $this->getWeekday(), self::WEEKDAY_FIELD);
    }

    private function fieldMatches(string $value, string $expression, int $fieldType): bool
    {
        $value = (int)$value;
        [$min, $max] = self::FIELD_RANGES[$fieldType];

        if ($expression === '*') {
            return true;
        }

        $values = $this->parseField($expression, $fieldType);

        return in_array($value, $values, true);
    }

    private function parseField(string $expression, int $fieldType): array
    {
        [$min, $max] = self::FIELD_RANGES[$fieldType];
        $values = [];

        $parts = explode(',', $expression);

        foreach ($parts as $part) {
            if (str_contains($part, '/')) {
                [$range, $step] = explode('/', $part, 2);
                $step = (int)$step;
                $rangeValues = $this->parseRange($range, $min, $max, $fieldType);

                foreach ($rangeValues as $i => $val) {
                    if ($i % $step === 0) {
                        $values[] = $val;
                    }
                }
            } else {
                $values = array_merge($values, $this->parseRange($part, $min, $max, $fieldType));
            }
        }

        return array_unique($values);
    }

    private function parseRange(string $expression, int $min, int $max, int $fieldType): array
    {
        if ($fieldType === self::MONTH_FIELD) {
            $expression = strtoupper($expression);
            foreach (self::MONTH_NAMES as $name => $num) {
                $expression = str_replace($name, (string)$num, $expression);
            }
        }

        if ($fieldType === self::WEEKDAY_FIELD) {
            $expression = strtoupper($expression);
            foreach (self::WEEKDAY_NAMES as $name => $num) {
                $expression = str_replace($name, (string)$num, $expression);
            }
        }

        if ($expression === '*') {
            return range($min, $max);
        }

        if (str_contains($expression, '-')) {
            [$start, $end] = explode('-', $expression, 2);
            return range((int)$start, (int)$end);
        }

        return [(int)$expression];
    }

    public function __toString(): string
    {
        return $this->expression;
    }
}
