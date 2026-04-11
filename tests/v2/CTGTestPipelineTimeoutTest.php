<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\CTGTestStatus;
use CTG\Test\Predicates\CTGTestPredicates;

// Behavioral tests for per-operation timeout enforcement and slot rollback.
//
// NOTE: The spec (section 4.5) allows two cancellation paths: pcntl_alarm() with
// second-level granularity, or hrtime() post-hoc detection with millisecond
// granularity. These tests must work under either path. We use a timeout of
// 1000ms and a sleep of 1500ms so that:
//   - Under pcntl: the 1s alarm fires during the 1.5s sleep -> timeout detected
//   - Under hrtime fallback: the 1.5s > 1s elapsed check -> timeout detected
// Each timeout test takes roughly 1-1.5 seconds as a consequence.
class CTGTestPipelineTimeoutTest extends TestCase
{
    // Spec 4.5, Design Doc CONFIG.timeout: A stage exceeding timeout records ERROR status
    public function testStageExceedingTimeoutRecordsErrorStatus(): void
    {
        $state = CTGTest::init('stage timeout')
            ->stage('slow op', function (CTGTestState $s) {
                usleep(1_500_000); // 1.5 seconds
                return 'never applied';
            })
            ->start('original', ['timeout' => 1000, 'haltOnFailure' => false]);

        $result = $state->getResults()[0];
        $this->assertSame(CTGTestStatus::ERROR, $result->_status);
    }

    // Spec 4.5: Timeout error field contains framework-generated exception
    public function testTimeoutErrorFieldContainsFrameworkException(): void
    {
        $state = CTGTest::init('timeout error field')
            ->stage('slow op', function (CTGTestState $s) {
                usleep(1_500_000);
                return 'never applied';
            })
            ->start('original', ['timeout' => 1000, 'haltOnFailure' => false]);

        $result = $state->getResults()[0];
        $this->assertInstanceOf(\Throwable::class, $result->_error);
    }

    // Spec 4.5: After timeout, state.subject is unchanged from before the operation ran
    public function testTimeoutDoesNotApplyStageReturnValueToSubject(): void
    {
        $state = CTGTest::init('timeout rollback subject')
            ->stage('slow op', function (CTGTestState $s) {
                usleep(1_500_000);
                return 'mutated';
            })
            ->start('original', ['timeout' => 1000, 'haltOnFailure' => false]);

        // Subject should remain 'original' — the slow stage's return value must not be applied
        $this->assertSame('original', $state->getSubject());
    }

    // Spec 4.5: After timeout, state.computed is unchanged from before the operation ran
    public function testTimeoutDoesNotApplyAssertComputedValue(): void
    {
        $state = CTGTest::init('timeout rollback computed')
            ->assert(
                'slow assert',
                function (CTGTestState $s) {
                    usleep(1_500_000);
                    return 'mutated computed';
                },
                CTGTestPredicates::equals('mutated computed')
            )
            ->start('original', ['timeout' => 1000, 'haltOnFailure' => false]);

        // state.computed should not carry the slow assert's value after timeout
        $this->assertNotSame('mutated computed', $state->getComputed());
    }

    // Spec Design Doc CONFIG.timeout: Per-step timeout applies to each operation individually
    public function testTimeoutAppliesPerOperationNotAggregate(): void
    {
        // Three fast stages, each well under the timeout individually
        $state = CTGTest::init('per-operation timeout')
            ->stage('op1', fn(CTGTestState $s) => 1)
            ->stage('op2', fn(CTGTestState $s) => 2)
            ->stage('op3', fn(CTGTestState $s) => 3)
            ->start(0, ['timeout' => 2000]);

        $results = $state->getResults();
        $this->assertCount(3, $results);
        foreach ($results as $result) {
            $this->assertSame(CTGTestStatus::PASS, $result->_status);
        }
        $this->assertSame(3, $state->getSubject());
    }

    // Spec 4.5, Design Doc CONFIG.timeout: A value of 0 disables timeout enforcement entirely
    public function testTimeoutZeroDisablesEnforcement(): void
    {
        // Sleep 1.5s with timeout disabled — should complete successfully
        $state = CTGTest::init('timeout disabled')
            ->stage('op', function (CTGTestState $s) {
                usleep(1_500_000);
                return 'completed';
            })
            ->start(null, ['timeout' => 0]);

        $result = $state->getResults()[0];
        $this->assertSame(CTGTestStatus::PASS, $result->_status);
        $this->assertSame('completed', $state->getSubject());
    }

    // Spec 4.5, haltOnFailure: Timeout ERROR halts pipeline when haltOnFailure is true
    public function testTimeoutErrorHaltsPipelineUnderHaltOnFailure(): void
    {
        $state = CTGTest::init('timeout halt')
            ->stage('slow op', function (CTGTestState $s) {
                usleep(1_500_000);
                return 'never';
            })
            ->stage('should not run', fn(CTGTestState $s) => 'should not run')
            ->start('original', ['timeout' => 1000, 'haltOnFailure' => true]);

        // Only the first (timed-out) operation should have a result entry
        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame(CTGTestStatus::ERROR, $results[0]->_status);
    }
}
