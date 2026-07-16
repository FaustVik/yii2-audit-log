<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,

        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,

        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'global_namespace_import' => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],

        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'match', 'parameters']],
        'whitespace_after_comma_in_array' => true,

        'binary_operator_spaces' => ['default' => 'single_space'],
        'concat_space' => ['spacing' => 'one'],
        'single_blank_line_at_eof' => true,

        'visibility_required' => ['elements' => ['property', 'method', 'const']],

        'no_blank_lines_after_phpdoc' => true,
        'phpdoc_trim' => true,
        'phpdoc_order' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => false],

        'no_empty_statement' => true,
        'no_useless_return' => true,
        'no_useless_else' => true,
    ])
    ->setFinder(
        (new Finder())
            ->in(__DIR__ . '/src')
            ->in(__DIR__ . '/tests')
    )
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache');
