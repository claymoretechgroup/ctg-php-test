<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\CTGTestStatus;
use CTG\Test\CTGTestPredicate;
use CTG\Test\CTGTestError;
use CTG\Test\Predicates\CTGTestPredicates;

class CTGTestPipelineAssertTest extends TestCase
{
    // Spec Design Doc ASSERT: Assert computes value and evaluates predicate
    public function testAssertComputesValueAndEvaluatesPredicate(): void
    {
        $state = CTGTest::init('assert test')
            ->assert(
                'check subject',
                fn(CTGTestState $s) => $s->getSubject(),
                CTGTestPredicates::equals(42)
            )
            ->start(42);

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame(42, $results[0]->_computedValue);
        $this->assertSame(42, $results[0]->_expectedOutcome);
    }

    // Spec Design Doc Statuses: Assert records PASS when predicate returns true
    public function testAssertRecordsPassWhenPredicateReturnsTrue(): void
    {
        $state = CTGTest::init('pass assert')
            ->assert(
                'is 10',
                fn(CTGTestState $s) => $s->getSubject(),
                CTGTestPredicates::equals(10)
            )
            ->start(10);

        $this->assertSame(CTGTestStatus::PASS, $state->getResults()[0]->_status);
    }

    // Spec Design Doc Statuses: Assert records FAIL when predicate returns false
    public function testAssertRecordsFailWhenPredicateReturnsFalse(): void
    {
        $state = CTGTest::init('fail assert')
            ->assert(
                'is 10',
                fn(CTGTestState $s) => $s->getSubject(),
                CTGTestPredicates::equals(10)
            )
            ->start(99, ['haltOnFailure' => false]);

        $result = $state->getResults()[0];
        $this->assertSame(CTGTestStatus::FAIL, $result->_status);
        $this->assertSame(99, $result->_computedValue);
        $this->assertSame(10, $result->_expectedOutcome);
    }

    // Spec Design Doc Errors: Assert records ERROR when handler throws
    public function testAssertRecordsErrorWhenHandlerThrows(): void
    {
        $state = CTGTest::init('handler error')
            ->assert(
                'broken handler',
                fn(CTGTestState $s) => throw new \RuntimeException('handler broke'),
                CTGTestPredicates::equals(42)
            )
            ->start(null, ['haltOnFailure' => false]);

        $result = $state->getResults()[0];
        $this->assertSame(CTGTestStatus::ERROR, $result->_status);
        $this->assertInstanceOf(\Throwable::class, $result->_error);
        // Spec: computedValue and expectedOutcome are null when handler throws
        $this->assertNull($result->_computedValue);
        $this->assertNull($result->_expectedOutcome);
    }

    // Spec Design Doc Errors: Assert records ERROR when predicate.evaluate throws
    public function testAssertRecordsErrorWhenPredicateEvaluateThrows(): void
    {
        $throwingPredicate = CTGTestPredicate::init(
            'expected value',
            fn(mixed $v): bool => throw new \RuntimeException('predicate broke')
        );

        $state = CTGTest::init('predicate error')
            ->assert(
                'broken predicate',
                fn(CTGTestState $s) => $s->getSubject(),
                $throwingPredicate
            )
            ->start('hello', ['haltOnFailure' => false]);

        $result = $state->getResults()[0];
        $this->assertSame(CTGTestStatus::ERROR, $result->_status);
        $this->assertInstanceOf(\Throwable::class, $result->_error);
        // Spec: computedValue populated (handler ran), expectedOutcome populated (predicate had one)
        $this->assertSame('hello', $result->_computedValue);
        $this->assertSame('expected value', $result->_expectedOutcome);
    }

    // Spec 4.3: Assert requires PREDICATE instance — non-predicate throws INVALID_EXPECTED_OUTCOME in start()
    public function testAssertNonPredicateThrowsInvalidExpectedOutcome(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_EXPECTED_OUTCOME);

        CTGTest::init('bad predicate')
            ->assert('check', fn(CTGTestState $s) => $s->getSubject(), 'not a predicate')
            ->start(42);
    }

    // Spec 4.3: Callable passed as predicate throws INVALID_EXPECTED_OUTCOME in start()
    public function testAssertCallableAsPredicateThrowsInvalidExpectedOutcome(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_EXPECTED_OUTCOME);

        CTGTest::init('callable predicate')
            ->assert(
                'check',
                fn(CTGTestState $s) => $s->getSubject(),
                fn(mixed $v): bool => $v === 42
            )
            ->start(42);
    }
}
