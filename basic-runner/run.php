<?php
declare(strict_types=1);

// Basic runner for ctg-php-test.
//
// Copy this file to tests/run.php in your project. It discovers test
// files in the same directory (glob pattern below), invokes each
// pipeline's start(), renders the result state with the reference
// text formatter, aggregates pass/fail counts, and sets an exit code.
//
// Test files must `return` an array of CTGTest instances (or a single
// CTGTest). Each pipeline runs independently; the runner does not
// share state across pipelines.
//
// Run with: php tests/run.php

require_once __DIR__ . '/../vendor/autoload.php';

use CTG\Test\CTGTest;
use CTG\Test\CTGTestStatus;
use CTG\Test\Formatters\CTGTestTextFormatter;

$passed  = 0;
$failed  = 0;
$errored = 0;
$skipped = 0;

$files = glob(__DIR__ . '/*Test.php') ?: [];

foreach ($files as $file) {
    $pipelines = require $file;
    if ($pipelines instanceof CTGTest) {
        $pipelines = [$pipelines];
    }

    foreach ($pipelines as $pipeline) {
        $state = $pipeline->start(null, ['haltOnFailure' => false]);
        echo CTGTestTextFormatter::format($state), "\n";

        foreach ($state->getResults() as $result) {
            if ($result->_skipped) {
                $skipped++;
                continue;
            }
            match ($result->_status) {
                CTGTestStatus::PASS  => $passed++,
                CTGTestStatus::FAIL  => $failed++,
                CTGTestStatus::ERROR => $errored++,
            };
        }
    }
}

echo "Total: {$passed} passed, {$failed} failed, {$errored} errored, {$skipped} skipped\n";
exit(($failed === 0 && $errored === 0) ? 0 : 1);
