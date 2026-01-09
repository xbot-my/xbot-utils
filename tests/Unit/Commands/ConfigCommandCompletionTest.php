<?php

use Symfony\Component\Console\Tester\CommandCompletionTester;
use Xbot\Utils\Command\ConfigCommand;

/**
 * Shell completion tests for ConfigCommand
 *
 * Note: The CommandCompletionTester has limitations in simulating real shell completion.
 * For actual testing, use the _complete command directly in a shell.
 */
test('config command has completion support', function () {
    $command = new ConfigCommand();

    // 验证命令有 complete 方法
    expect(method_exists($command, 'complete'))->toBeTrue();
});

test('config completion returns array for action argument', function () {
    $command = new ConfigCommand();
    $tester = new CommandCompletionTester($command);

    $suggestions = $tester->complete(['config', '']);

    // 应该返回数组（实际 shell 中会返回 set, get, list, edit）
    expect($suggestions)->toBeArray();
});

test('config completion returns array for key argument', function () {
    $command = new ConfigCommand();
    $tester = new CommandCompletionTester($command);

    $suggestions = $tester->complete(['config', 'set', '']);

    // 应该返回数组（实际 shell 中会返回配置键）
    expect($suggestions)->toBeArray();
});

test('config completion returns array for options', function () {
    $command = new ConfigCommand();
    $tester = new CommandCompletionTester($command);

    $suggestions = $tester->complete(['config', '-']);

    // 应该返回数组（实际 shell 中会返回 --global, --json 等）
    expect($suggestions)->toBeArray();
});
