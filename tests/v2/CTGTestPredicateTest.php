<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTestPredicate;

class CTGTestPredicateTest extends TestCase
{
    // Spec 2.4: init() creates predicate with expectedOutcome and evaluate closure
    public function testInitCreatesPredicateWithExpectedOutcomeAndClosure(): void
    {
        $predicate = CTGTestPredicate::init(42, fn(mixed $v): bool => $v === 42);

        $this->assertInstanceOf(CTGTestPredicate::class, $predicate);
    }

    // Spec 2.4: evaluate() calls the closure and returns bool
    public function testEvaluateReturnsTrueWhenClosureReturnsTrue(): void
    {
        $predicate = CTGTestPredicate::init(42, fn(mixed $v): bool => $v === 42);
        $this->assertTrue($predicate->evaluate(42));
    }

    // Spec 2.4: evaluate() returns false when closure returns false
    public function testEvaluateReturnsFalseWhenClosureReturnsFalse(): void
    {
        $predicate = CTGTestPredicate::init(42, fn(mixed $v): bool => $v === 42);
        $this->assertFalse($predicate->evaluate(99));
    }

    // Spec 2.4: getExpectedOutcome() returns the stored value
    public function testGetExpectedOutcomeReturnsStoredValue(): void
    {
        $predicate = CTGTestPredicate::init('hello', fn(mixed $v): bool => $v === 'hello');
        $this->assertSame('hello', $predicate->getExpectedOutcome());
    }

    // Spec 2.4: expectedOutcome can be any type (mixed)
    public function testExpectedOutcomeAcceptsMixedTypes(): void
    {
        $predNull = CTGTestPredicate::init(null, fn(mixed $v): bool => $v === null);
        $this->assertNull($predNull->getExpectedOutcome());

        $predArray = CTGTestPredicate::init([1, 2], fn(mixed $v): bool => $v === [1, 2]);
        $this->assertSame([1, 2], $predArray->getExpectedOutcome());

        $predBool = CTGTestPredicate::init(true, fn(mixed $v): bool => $v === true);
        $this->assertTrue($predBool->getExpectedOutcome());
    }

    // Spec 2.4: evaluate receives the computed value and returns bool
    public function testEvaluateReceivesComputedValue(): void
    {
        $received = null;
        $predicate = CTGTestPredicate::init('expected', function (mixed $v) use (&$received): bool {
            $received = $v;
            return true;
        });

        $predicate->evaluate('test input');
        $this->assertSame('test input', $received);
    }
}
