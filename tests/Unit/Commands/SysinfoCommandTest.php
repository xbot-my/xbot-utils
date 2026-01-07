<?php

use Symfony\Component\Console\Tester\CommandTester;
use Xbot\Utils\Command\SysinfoCommand;
use Tests\Unit\Commands\CommandTestHelpers;

beforeEach(function () {
    $_SERVER['argv'] = ['xbot', 'sysinfo'];
});

afterEach(function () {
    unset($_SERVER['argv']);
});

test('sysinfo command has correct name', function () {
    $command = new SysinfoCommand();

    expect($command->getName())->toBe('sysinfo');
});

test('sysinfo command has correct description', function () {
    $command = new SysinfoCommand();

    expect($command->getDescription())->toBe('Display system information and environment details');
});

test('sysinfo command executes successfully with mock executor', function () {
    $command = new SysinfoCommand();
    $command->setScriptExecutor(CommandTestHelpers::createMockExecutor(0));

    $tester = new CommandTester($command);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Gathering system information...');
    expect($output)->toContain('System information retrieved successfully!');
    expect($tester->getStatusCode())->toBe(0);
});

test('sysinfo command handles script failure', function () {
    $command = new SysinfoCommand();
    $command->setScriptExecutor(CommandTestHelpers::createMockExecutor(1));

    $tester = new CommandTester($command);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Gathering system information...');
    expect($output)->toContain('Command failed with exit code: 1');
    expect($tester->getStatusCode())->toBe(1);
});

test('sysinfo command handles script not found error', function () {
    $command = new SysinfoCommand();
    $command->setScriptExecutor(
        CommandTestHelpers::createThrowingExecutor('Script not found: /path/to/script.sh')
    );

    $tester = new CommandTester($command);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Gathering system information...');
    expect($output)->toContain('Script not found:');
    expect($tester->getStatusCode())->toBe(1);
});
