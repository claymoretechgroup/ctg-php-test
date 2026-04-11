<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\CTGTestStatus;
use CTG\Test\CTGTestError;
use CTG\Test\Predicates\CTGTestPredicates;

class CTGTestPipelineSkipTest extends TestCase
{
    // Spec Design Doc SKIP: Unconditional skip bypasses target, records skipped result
    public function testUnconditionalSkipBypassesTarget(): void
    {
        $state = CTGTest::init('skip test')
            ->skip('target op')
            ->stage('target op', fn(CTGTestState $s) => 'should not run')
            ->start('original');

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->_skipped);
        $this->assertSame('original', $state->getSubject());
    }

    // Spec Design Doc SKIP: Conditional skip evaluates condition against current state at target
    public function testConditionalSkipEvaluatesConditionAgainstCurrentState(): void
    {
        $state = CTGTest::init('conditional skip')
            ->stage('setup', fn(CTGTestState $s) => ['ready' => false])
            ->skip('check', fn(CTGTestState $s) => $s->getSubject()['ready'] === false)
            ->assert(
                'check',
                fn(CTGTestState $s) => $s->getSubject()['ready'],
                CTGTestPredicates::isTrue()
            )
            ->start(null);

        $results = $state->getResults();
        $this->assertSame(CTGTestStatus::PASS, $results[0]->_status); // setup stage
        $this->assertTrue($results[1]->_skipped); // check was skipped
    }

    // Spec Design Doc SKIP: Condition returning false does NOT skip — target runs normally
    public function testConditionReturningFalseDoesNotSkip(): void
    {
        $state = CTGTest::init('no skip')
            ->skip('target', fn(CTGTestState $s) => false)
            ->stage('target', fn(CTGTestState $s) => $s->getSubject() * 2)
            ->start(5);

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->_skipped);
        $this->assertSame(CTGTestStatus::PASS, $results[0]->_status);
        $this->assertSame(10, $state->getSubject());
    }

    // Spec 2.5: Skip has no label of its own — result uses target's label
    public function testSkipResultUsesTargetLabel(): void
    {
        $state = CTGTest::init('skip label')
            ->skip('my target')
            ->stage('my target', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);

        $results = $state->getResults();
        $this->assertSame(['my target'], $results[0]->_label);
    }

    // Spec 4.3: Skip can appear at any position relative to target (no ordering constraint)
    public function testSkipCanAppearAfterTarget(): void
    {
        $state = CTGTest::init('skip after')
            ->stage('my op', fn(CTGTestState $s) => 'should not run')
            ->skip('my op')
            ->start('original');

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->_skipped);
        $this->assertSame('original', $state->getSubject());
    }

    // Spec Q3: Skip condition that throws records ERROR for target
    public function testSkipConditionThatThrowsRecordsErrorForTarget(): void
    {
        $state = CTGTest::init('skip throws')
            ->skip('target', fn(CTGTestState $s) => throw new \RuntimeException('condition broke'))
            ->stage('target', fn(CTGTestState $s) => $s->getSubject())
            ->start(null, ['haltOnFailure' => false]);

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame(CTGTestStatus::ERROR, $results[0]->_status);
        $this->assertSame(['target'], $results[0]->_label);
        $this->assertInstanceOf(\Throwable::class, $results[0]->_error);
    }

    // Spec Q3 + haltOnFailure: Skip condition ERROR halts pipeline under haltOnFailure=true
    public function testSkipConditionErrorHaltsPipelineUnderHaltOnFailure(): void
    {
        $state = CTGTest::init('skip throws halt')
            ->skip('target', fn(CTGTestState $s) => throw new \RuntimeException('condition broke'))
            ->stage('target', fn(CTGTestState $s) => $s->getSubject())
            ->stage('should not run', fn(CTGTestState $s) => 'unreachable')
            ->start(null, ['haltOnFailure' => true]);

        $results = $state->getResults();
        // Only the ERROR for the target — the subsequent stage must not run
        $this->assertCount(1, $results);
        $this->assertSame(CTGTestStatus::ERROR, $results[0]->_status);
        $this->assertSame(['target'], $results[0]->_label);
    }

    // Spec 4.3: Skip target must exist — missing target throws INVALID_SKIP in start()
    public function testSkipMissingTargetThrowsInvalidSkip(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_SKIP);

        CTGTest::init('missing target')
            ->skip('nonexistent')
            ->stage('exists', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);
    }

    // Spec 4.3: Duplicate skip for same target throws INVALID_SKIP in start()
    public function testDuplicateSkipForSameTargetThrowsInvalidSkip(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_SKIP);

        CTGTest::init('dup skip')
            ->skip('target')
            ->skip('target')
            ->stage('target', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);
    }

    // Spec 4.3: Non-callable condition throws INVALID_SKIP in start()
    public function testNonCallableConditionThrowsInvalidSkip(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_SKIP);

        CTGTest::init('bad condition')
            ->skip('target', 'not a closure')
            ->stage('target', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);
    }

    // Spec Design Doc Skipped Results: Skipped result has skipped=true, status=null, all other fields null
    public function testSkippedResultFieldValues(): void
    {
        $state = CTGTest::init('skip fields')
            ->skip('target')
            ->stage('target', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);

        $result = $state->getResults()[0];
        $this->assertTrue($result->_skipped);
        $this->assertNull($result->_status);
        $this->assertNull($result->_computedValue);
        $this->assertNull($result->_expectedOutcome);
        $this->assertNull($result->_error);
    }

    // Spec 4.3: Skip condition evaluates at target execution time, seeing mutations from earlier ops
    public function testSkipConditionSeesStateFromEarlierOperations(): void
    {
        $state = CTGTest::init('late eval')
            ->stage('mutate', fn(CTGTestState $s) => 'mutated')
            ->skip('check', fn(CTGTestState $s) => $s->getSubject() === 'mutated')
            ->assert(
                'check',
                fn(CTGTestState $s) => $s->getSubject(),
                CTGTestPredicates::equals('mutated')
            )
            ->start('original');

        $results = $state->getResults();
        $this->assertSame(CTGTestStatus::PASS, $results[0]->_status); // mutate stage
        $this->assertTrue($results[1]->_skipped); // check was skipped because subject was 'mutated'
    }
}
