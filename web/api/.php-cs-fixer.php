<?php
/**
 * PHP-CS-Fixer config for the TacacsGUI API.
 * Focus: remove unused imports (the user's explicit ask) + a small, safe,
 * non-opinionated style baseline. We deliberately do NOT reformat the whole
 * codebase (no PSR-2/PSR-12 full reflow) — that would create a massive noisy
 * diff. We only apply rules that clean dead/unused constructs.
 */

// mavis/ and parser/ live at the REPO ROOT (sibling of web/), not under web/api,
// so they are not part of this autoloader's PSR-4 tree. Analyze web/api here;
// mavis/ + parser/ are covered by a separate run (see WORK_LOG).
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/app')
    ->in(__DIR__ . '/bootstrap')
    ->in(__DIR__ . '/self')
    ->name('*.php')
    ->notName('*.min.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        // ---- Dead code / unused constructs (the point of this pass) ----
        'no_unused_imports'            => true,  // the explicit ask
        'no_useless_else'              => true,
        'no_useless_return'            => true,
        'no_empty_statement'           => true,
        'no_extra_blank_lines'         => true,
        'no_whitespace_in_blank_line'  => true,
        'ordered_imports'              => ['sort_algorithm' => 'alpha'],
        'single_import_per_statement'  => true,
        'no_trailing_whitespace'       => true,
        'no_trailing_comma_in_singleline' => true,
        'blank_line_after_opening_tag' => true,
        'no_closing_tag'               => true,
        'encoding'                     => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
