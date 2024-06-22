<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src/',
        __DIR__ . '/tests/',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS2.0' => true,
        '@PHP82Migration' => true,
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => false,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments', 'arrays', 'match', 'parameters'],
        ],
        'fully_qualified_strict_types' => [
            'leading_backslash_in_global_namespace' => true,
        ],
    ])
    ->setFinder($finder)
;
