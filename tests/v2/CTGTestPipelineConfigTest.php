<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\CTGTestStatus;
use CTG\Test\CTGTestError;
use CTG\Test\Predicates\CTGTestPredicates;

class CTGTestPipelineConfigTest extends TestCase
{
    // Spec 4.4, Design Doc CONFIG: Default config — haltOnFailure=true, timeout=5000
    public function testDefaultHaltOnFailureIsTrue(): void
    {
        $state = CTGTest::init('default halt')
            ->assert(
                'will fail',
                fn(CTGTestState $s) => 'wrong',
                CTGTestPredicates::equals('right')
            )
            ->stage('should not run', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);

        // haltOnFailure=true by default, so stage should not run
        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame(CTGTestStatus::FAIL, $results[0]->_status);
    }

    // Spec 4.4: Empty config array uses defaults
    public function testEmptyConfigUsesDefaults(): void
    {
        $state = CTGTest::init('empty config')
            ->assert(
                'will fail',
                fn(CTGTestState $s) => 'wrong',
                CTGTestPredicates::equals('right')
            )
            ->stage('should not run', fn(CTGTestState $s) => $s->getSubject())
            ->start(null, []);

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame(CTGTestStatus::FAIL, $results[0]->_status);
    }

    // Spec 4.4: Omitted config uses defaults
    public function testOmittedConfigUsesDefaults(): void
    {
        $state = CTGTest::init('omitted config')
            ->stage('pass', fn(CTGTestState $s) => $s->getSubject())
            ->start(42);

        $this->assertCount(1, $state->getResults());
        $this->assertSame(CTGTestStatus::PASS, $state->getResults()[0]->_status);
    }

    // Spec Design Doc CONFIG: haltOnFailure=false runs all operations regardless of failures
    public function testHaltOnFailureFalseRunsAllOperations(): void
    {
        $state = CTGTest::init('no halt')
            ->assert(
                'fail first',
                fn(CTGTestState $s) => 'wrong',
                CTGTestPredicates::equals('right')
            )
            ->stage('runs anyway', fn(CTGTestState $s) => 'ran')
            ->assert(
                'also runs',
                fn(CTGTestState $s) => $s->getSubject(),
                CTGTestPredicates::equals('ran')
            )
            ->start(null, ['haltOnFailure' => false]);

        $results = $state->getResults();
        $this->assertCount(3, $results);
        $this->assertSame(CTGTestStatus::FAIL, $results[0]->_status);
        $this->assertSame(CTGTestStatus::PASS, $results[1]->_status);
        $this->assertSame(CTGTestStatus::PASS, $results[2]->_status);
    }

    // Spec Design Doc CONFIG: haltOnFailure=true stops after first FAIL or ERROR
    public function testHaltOnFailureTrueStopsAfterFirstFailOrError(): void
    {
        $state = CTGTest::init('halt on error')
            ->stage('explode', fn(CTGTestState $s) => throw new \RuntimeException('boom'))
            ->stage('never runs', fn(CTGTestState $s) => $s->getSubject())
            ->start(null, ['haltOnFailure' => true]);

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame(CTGTestStatus::ERROR, $results[0]->_status);
    }

    // Spec 4.3: Unknown config key throws INVALID_CONFIG
    public function testUnknownConfigKeyThrowsInvalidConfig(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_CONFIG);

        CTGTest::init('bad config')
            ->stage('op', fn(CTGTestState $s) => $s->getSubject())
            ->start(null, ['unknownKey' => true]);
    }

    // Spec 4.3: Wrong-typed haltOnFailure throws INVALID_CONFIG
    public function testWrongTypedHaltOnFailureThrowsInvalidConfig(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_CONFIG);

        CTGTest::init('bad halt')
            ->stage('op', fn(CTGTestState $s) => $s->getSubject())
            ->start(null, ['haltOnFailure' => 'yes']);
    }

    // Spec 4.3: Wrong-typed timeout throws INVALID_CONFIG
    public function testWrongTypedTimeoutThrowsInvalidConfig(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_CONFIG);

        CTGTest::init('bad timeout')
            ->stage('op', fn(CTGTestState $s) => $s->getSubject())
            ->start(null, ['timeout' => 'fast']);
    }

    // Spec 4.3: Negative timeout throws INVALID_CONFIG
    public function testNegativeTimeoutThrowsInvalidConfig(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_CONFIG);

        CTGTest::init('negative timeout')
            ->stage('op', fn(CTGTestState $s) => $s->getSubject())
            ->start(null, ['timeout' => -1]);
    }

    // Spec 4.4: Timeout of 0 disables timeout
    public function testTimeoutZeroDisablesTimeout(): void
    {
        // Should not throw — timeout 0 is valid and disables enforcement
        $state = CTGTest::init('no timeout')
            ->stage('op', fn(CTGTestState $s) => $s->getSubject())
            ->start(42, ['timeout' => 0]);

        $this->assertCount(1, $state->getResults());
        $this->assertSame(CTGTestStatus::PASS, $state->getResults()[0]->_status);
    }
}
