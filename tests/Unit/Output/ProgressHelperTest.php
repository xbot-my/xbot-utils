<?php

use Xbot\Utils\Output\ProgressHelper;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

beforeEach(function () {
    $this->output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
});

test('progress helper creates progress bar with correct max steps', function () {
    $helper = new ProgressHelper($this->output);
    $helper->create(100);

    expect($helper->getProgressBar())->not->toBeNull();
    expect($helper->getProgressBar()->getMaxSteps())->toBe(100);
});

test('progress helper starts progress bar', function () {
    $helper = new ProgressHelper($this->output);
    $helper->create(10)->start();

    $progressBar = $helper->getProgressBar();
    expect($progressBar)->not->toBeNull();
    expect($progressBar->getProgress())->toBe(0);
});

test('progress helper advances progress', function () {
    $helper = new ProgressHelper($this->output);
    $helper->create(10)->start();

    $helper->advance(5, 'Processing...');

    expect($helper->getProgressBar()->getProgress())->toBe(5);
});

test('progress helper sets absolute progress', function () {
    $helper = new ProgressHelper($this->output);
    $helper->create(100)->start();

    $helper->setProgress(50, 'Half way...');

    expect($helper->getProgressBar()->getProgress())->toBe(50);
});

test('progress helper sets message', function () {
    $helper = new ProgressHelper($this->output);
    $helper->create(10)->start();

    $helper->setMessage('Custom message');

    $progressBar = $helper->getProgressBar();
    expect($progressBar)->not->toBeNull();
});

test('progress helper finishes progress bar', function () {
    $helper = new ProgressHelper($this->output);
    $helper->create(10)->start();
    $helper->finish();

    expect($helper->getProgressBar())->not->toBeNull();
});

test('progress helper supports default format', function () {
    $helper = new ProgressHelper($this->output);
    $helper->create(100, 'default');

    expect($helper->getProgressBar())->not->toBeNull();
});

test('progress helper supports simple format', function () {
    $helper = new ProgressHelper($this->output);
    $helper->create(100, 'simple');

    expect($helper->getProgressBar())->not->toBeNull();
});

test('progress helper supports verbose format', function () {
    $helper = new ProgressHelper($this->output);
    $helper->create(100, 'verbose');

    expect($helper->getProgressBar())->not->toBeNull();
});

test('progress helper method chaining works', function () {
    $helper = new ProgressHelper($this->output);

    $result = $helper->create(100)->start()->advance(10)->setMessage('Test')->finish();

    expect($result)->toBeInstanceOf(ProgressHelper::class);
});
