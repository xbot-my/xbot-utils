<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$config = new Config();

return $config
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'single_quote' => true,
        'no_trailing_whitespace_in_comment' => true,
        'single_line_comment_style' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        Finder::create()
            ->in(__DIR__ . '/src')
            ->name('*.php')
            ->notName('*.blade.php')
    );
