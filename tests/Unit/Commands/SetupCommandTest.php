<?php

use Symfony\Component\Console\Tester\CommandTester;
use Xbot\Utils\Command\SetupCommand;
use Tests\Unit\Commands\CommandTestHelpers;

beforeEach(function () {
    $_SERVER['argv'] = ['xbot', 'setup'];
});

afterEach(function () {
    unset($_SERVER['argv']);
});

test('setup command has correct name', function () {
    $command = new SetupCommand();

    expect($command->getName())->toBe('setup');
});

test('setup command has correct description', function () {
    $command = new SetupCommand();

    expect($command->getDescription())->toBe('Initialize Laravel project setup');
});

test('setup command executes successfully with mock executor', function () {
    $command = new SetupCommand();
    $command->setScriptExecutor(CommandTestHelpers::createMockExecutor(0));

    $tester = new CommandTester($command);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Setting up Laravel project...');
    expect($output)->toContain('Laravel project setup completed successfully!');
    expect($tester->getStatusCode())->toBe(0);
});

test('setup command handles script failure', function () {
    $command = new SetupCommand();
    $command->setScriptExecutor(CommandTestHelpers::createMockExecutor(1));

    $tester = new CommandTester($command);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Setting up Laravel project...');
    expect($output)->toContain('Command failed with exit code: 1');
    expect($tester->getStatusCode())->toBe(1);
});

test('setup command handles script not found error', function () {
    $command = new SetupCommand();
    $command->setScriptExecutor(
        CommandTestHelpers::createThrowingExecutor('Script not found: /path/to/script.sh')
    );

    $tester = new CommandTester($command);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Setting up Laravel project...');
    expect($output)->toContain('Script not found:');
    expect($tester->getStatusCode())->toBe(1);
});
