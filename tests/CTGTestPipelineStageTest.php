<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\CTGTestStatus;
use CTG\Test\Predicates\CTGTestPredicates;

class CTGTestPipelineStageTest extends TestCase
{
    // Spec 2.5, Design Doc STAGE: Stage transforms subject — handler return value goes to state.subject
    public function testStageTransformsSubject(): void
    {
        $state = CTGTest::init('stage test')
            ->stage('double', fn(CTGTestState $s) => $s->getSubject() * 2)
            ->start(5);

        $this->assertSame(10, $state->getSubject());
    }

    // Spec 2.5: Stage handler receives CTGTestState, not raw subject
    public function testStageHandlerReceivesCTGTestState(): void
    {
        $receivedArg = null;
        $state = CTGTest::init('state check')
            ->stage('capture arg', function ($arg) use (&$receivedArg) {
                $receivedArg = $arg;
                return $arg->getSubject();
            })
            ->start('hello');

        $this->assertInstanceOf(CTGTestState::class, $receivedArg);
    }

    // Spec Design Doc Statuses: Stage records PASS result on success
    public function testStageRecordsPassOnSuccess(): void
    {
        $state = CTGTest::init('pass test')
            ->stage('add one', fn(CTGTestState $s) => $s->getSubject() + 1)
            ->start(10);

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame(CTGTestStatus::PASS, $results[0]->_status);
        $this->assertSame(['add one'], $results[0]->_label);
        $this->assertFalse($results[0]->_skipped);
    }

    // Spec Design Doc Errors: Stage records ERROR result when handler throws
    public function testStageRecordsErrorWhenHandlerThrows(): void
    {
        $state = CTGTest::init('error test')
            ->stage('explode', fn(CTGTestState $s) => throw new \RuntimeException('boom'))
            ->start(null, ['haltOnFailure' => false]);

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame(CTGTestStatus::ERROR, $results[0]->_status);
        $this->assertInstanceOf(\Throwable::class, $results[0]->_error);
        $this->assertSame('boom', $results[0]->_error->getMessage());
    }

    // Spec Q1 (Resolved): state.computed resets to null before each operation
    public function testComputedResetsToNullBeforeEachOperation(): void
    {
        $computedValues = [];
        $state = CTGTest::init('computed reset')
            ->assert('set computed', fn(CTGTestState $s) => 'first', CTGTestPredicates::equals('first'))
            ->stage('check computed', function (CTGTestState $s) use (&$computedValues) {
                $computedValues[] = $s->getComputed();
                return $s->getSubject();
            })
            ->start('subject');

        // computed should have been reset to null before the stage ran
        $this->assertSame([null], $computedValues);
    }

    // Spec Design Doc STAGE: Multiple stages thread subject through sequentially
    public function testMultipleStagesThreadSubjectSequentially(): void
    {
        $state = CTGTest::init('threading')
            ->stage('add 1', fn(CTGTestState $s) => $s->getSubject() + 1)
            ->stage('multiply 3', fn(CTGTestState $s) => $s->getSubject() * 3)
            ->stage('subtract 2', fn(CTGTestState $s) => $s->getSubject() - 2)
            ->start(5);

        // (5 + 1) * 3 - 2 = 16
        $this->assertSame(16, $state->getSubject());

        $results = $state->getResults();
        $this->assertCount(3, $results);
        $this->assertSame(CTGTestStatus::PASS, $results[0]->_status);
        $this->assertSame(CTGTestStatus::PASS, $results[1]->_status);
        $this->assertSame(CTGTestStatus::PASS, $results[2]->_status);
    }

    // Spec 2.3 Q2: Stage results have null computedValue and expectedOutcome
    public function testStageResultHasNullComputedAndExpected(): void
    {
        $state = CTGTest::init('stage fields')
            ->stage('transform', fn(CTGTestState $s) => $s->getSubject())
            ->start(42);

        $result = $state->getResults()[0];
        $this->assertNull($result->_computedValue);
        $this->assertNull($result->_expectedOutcome);
    }

    // Spec Design Doc Errors: Stage error does not apply transformation
    public function testStageErrorDoesNotTransformSubject(): void
    {
        $state = CTGTest::init('no transform on error')
            ->stage('will fail', fn(CTGTestState $s) => throw new \RuntimeException('fail'))
            ->start('original', ['haltOnFailure' => false]);

        $this->assertSame('original', $state->getSubject());
    }
}
