<?php
declare(strict_types=1);

// Sample test file demonstrating the ctg-php-test basic-runner convention.
//
// A test file returns an array of CTGTest instances (or a single
// CTGTest). Each pipeline seeds its own subject in its first stage
// so that pipelines run independently of whatever the runner passes
// as an initial subject.
//
// Copy this file to tests/ArithmeticTest.php (or use it as a template
// for your own test files).

use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\Predicates\CTGTestPredicates;

return [
    CTGTest::init('addition')
        ->stage('seed', fn(CTGTestState $s) => 1)
        ->stage('add 1', fn(CTGTestState $s) => $s->getSubject() + 1)
        ->assert(
            'equals 2',
            fn(CTGTestState $s) => $s->getSubject(),
            CTGTestPredicates::equals(2)
        ),

    CTGTest::init('multiplication')
        ->stage('seed', fn(CTGTestState $s) => 2)
        ->stage('double', fn(CTGTestState $s) => $s->getSubject() * 2)
        ->assert(
            'equals 4',
            fn(CTGTestState $s) => $s->getSubject(),
            CTGTestPredicates::equals(4)
        ),
];
