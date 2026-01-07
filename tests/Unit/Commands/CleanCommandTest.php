<?php

use Symfony\Component\Console\Tester\CommandTester;
use Xbot\Utils\Command\CleanCommand;
use Tests\Unit\Commands\CommandTestHelpers;

beforeEach(function () {
    $_SERVER['argv'] = ['xbot', 'clean'];
});

afterEach(function () {
    unset($_SERVER['argv']);
});

test('clean command has correct name', function () {
    $command = new CleanCommand();

    expect($command->getName())->toBe('clean');
});

test('clean command has correct description', function () {
    $command = new CleanCommand();

    expect($command->getDescription())->toBe('Clean Laravel project temporary and sensitive data');
});

test('clean command executes successfully with mock executor', function () {
    $command = new CleanCommand();
    $command->setScriptExecutor(CommandTestHelpers::createMockExecutor(0));

    $tester = new CommandTester($command);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Cleaning Laravel project...');
    expect($output)->toContain('Laravel project cleaned successfully!');
    expect($tester->getStatusCode())->toBe(0);
});

test('clean command handles script failure', function () {
    $command = new CleanCommand();
    $command->setScriptExecutor(CommandTestHelpers::createMockExecutor(1));

    $tester = new CommandTester($command);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Cleaning Laravel project...');
    expect($output)->toContain('Command failed with exit code: 1');
    expect($tester->getStatusCode())->toBe(1);
});

test('clean command handles script not found error', function () {
    $command = new CleanCommand();
    $command->setScriptExecutor(
        CommandTestHelpers::createThrowingExecutor('Script not found: /path/to/script.sh')
    );

    $tester = new CommandTester($command);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Cleaning Laravel project...');
    expect($output)->toContain('Script not found:');
    expect($tester->getStatusCode())->toBe(1);
});
