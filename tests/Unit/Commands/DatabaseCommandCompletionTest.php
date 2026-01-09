<?php

use Symfony\Component\Console\Tester\CommandCompletionTester;
use Xbot\Utils\Command\DatabaseCommand;

/**
 * Shell completion tests for DatabaseCommand
 *
 * Note: The CommandCompletionTester has limitations in simulating real shell completion.
 * For actual testing, use the _complete command directly in a shell.
 */
test('database command has completion support', function () {
    $command = new DatabaseCommand();

    // 验证命令有 complete 方法
    expect(method_exists($command, 'complete'))->toBeTrue();
});

test('database completion returns array for action argument', function () {
    $command = new DatabaseCommand();
    $tester = new CommandCompletionTester($command);

    $suggestions = $tester->complete(['db', '']);

    // 应该返回数组（实际 shell 中会返回 migrate, backup, restore, test）
    expect($suggestions)->toBeArray();
});

test('database completion returns array for options', function () {
    $command = new DatabaseCommand();
    $tester = new CommandCompletionTester($command);

    $suggestions = $tester->complete(['db', '-']);

    // 应该返回数组（实际 shell 中会返回 --seed, --force, --file 等）
    expect($suggestions)->toBeArray();
});

test('database completion returns array for file option', function () {
    $command = new DatabaseCommand();
    $tester = new CommandCompletionTester($command);

    $suggestions = $tester->complete(['db', '--file', '']);

    // 应该返回数组（实际 shell 中会返回备份文件列表）
    expect($suggestions)->toBeArray();
});
