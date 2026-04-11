<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTestPredicate;
use CTG\Test\Predicates\CTGTestPredicates;

class CTGTestPredicatesConvenienceTest extends TestCase
{
    // Spec 7: equals() creates predicate that checks === equality
    public function testEqualsReturnsTrueOnStrictMatch(): void
    {
        $pred = CTGTestPredicates::equals(42);
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate(42));
        $this->assertFalse($pred->evaluate('42'));
        $this->assertFalse($pred->evaluate(43));
        $this->assertSame(42, $pred->getExpectedOutcome());
    }

    // Spec 7: notEquals() creates predicate that checks !== inequality
    public function testNotEqualsReturnsTrueOnStrictMismatch(): void
    {
        $pred = CTGTestPredicates::notEquals(42);
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate(43));
        $this->assertTrue($pred->evaluate('42'));
        $this->assertFalse($pred->evaluate(42));
        $this->assertSame(42, $pred->getExpectedOutcome());
    }

    // Spec 7: isNull() checks value is null
    public function testIsNull(): void
    {
        $pred = CTGTestPredicates::isNull();
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate(null));
        $this->assertFalse($pred->evaluate(0));
        $this->assertFalse($pred->evaluate(''));
        $this->assertFalse($pred->evaluate(false));
    }

    // Spec 7: isNotNull() checks value is not null
    public function testIsNotNull(): void
    {
        $pred = CTGTestPredicates::isNotNull();
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertFalse($pred->evaluate(null));
        $this->assertTrue($pred->evaluate(0));
        $this->assertTrue($pred->evaluate(''));
        $this->assertTrue($pred->evaluate(false));
    }

    // Spec 7: isTrue() checks value === true
    public function testIsTrue(): void
    {
        $pred = CTGTestPredicates::isTrue();
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate(true));
        $this->assertFalse($pred->evaluate(1));
        $this->assertFalse($pred->evaluate('true'));
        $this->assertFalse($pred->evaluate(false));
    }

    // Spec 7: isFalse() checks value === false
    public function testIsFalse(): void
    {
        $pred = CTGTestPredicates::isFalse();
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate(false));
        $this->assertFalse($pred->evaluate(0));
        $this->assertFalse($pred->evaluate(''));
        $this->assertFalse($pred->evaluate(null));
        $this->assertFalse($pred->evaluate(true));
    }

    // Spec 7: isTruthy() checks (bool)$value === true
    public function testIsTruthy(): void
    {
        $pred = CTGTestPredicates::isTruthy();
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate(true));
        $this->assertTrue($pred->evaluate(1));
        $this->assertTrue($pred->evaluate('hello'));
        $this->assertTrue($pred->evaluate([1]));
        $this->assertFalse($pred->evaluate(false));
        $this->assertFalse($pred->evaluate(0));
        $this->assertFalse($pred->evaluate(''));
        $this->assertFalse($pred->evaluate(null));
    }

    // Spec 7: isFalsy() checks (bool)$value === false
    public function testIsFalsy(): void
    {
        $pred = CTGTestPredicates::isFalsy();
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate(false));
        $this->assertTrue($pred->evaluate(0));
        $this->assertTrue($pred->evaluate(''));
        $this->assertTrue($pred->evaluate(null));
        $this->assertFalse($pred->evaluate(true));
        $this->assertFalse($pred->evaluate(1));
        $this->assertFalse($pred->evaluate('hello'));
    }

    // Spec 7: isInstanceOf() checks class
    public function testIsInstanceOf(): void
    {
        $pred = CTGTestPredicates::isInstanceOf(\stdClass::class);
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate(new \stdClass()));
        $this->assertFalse($pred->evaluate(new \RuntimeException()));
        $this->assertFalse($pred->evaluate('stdClass'));
        $this->assertSame(\stdClass::class, $pred->getExpectedOutcome());
    }

    // Spec 7: isType() checks gettype()
    public function testIsType(): void
    {
        $pred = CTGTestPredicates::isType('string');
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate('hello'));
        $this->assertFalse($pred->evaluate(42));
        $this->assertSame('string', $pred->getExpectedOutcome());

        $intPred = CTGTestPredicates::isType('integer');
        $this->assertTrue($intPred->evaluate(42));
        $this->assertFalse($intPred->evaluate('42'));
    }

    // Spec 7: greaterThan() checks >
    public function testGreaterThan(): void
    {
        $pred = CTGTestPredicates::greaterThan(10);
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate(11));
        $this->assertFalse($pred->evaluate(10));
        $this->assertFalse($pred->evaluate(9));
        $this->assertSame(10, $pred->getExpectedOutcome());
    }

    // Spec 7: lessThan() checks <
    public function testLessThan(): void
    {
        $pred = CTGTestPredicates::lessThan(10);
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate(9));
        $this->assertFalse($pred->evaluate(10));
        $this->assertFalse($pred->evaluate(11));
        $this->assertSame(10, $pred->getExpectedOutcome());
    }

    // Spec 7: contains() checks string substring
    public function testContains(): void
    {
        $pred = CTGTestPredicates::contains('world');
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate('hello world'));
        $this->assertFalse($pred->evaluate('hello'));
        $this->assertSame('world', $pred->getExpectedOutcome());
    }

    // Spec 7: matchesPattern() checks regex
    public function testMatchesPattern(): void
    {
        $pred = CTGTestPredicates::matchesPattern('/^hello/');
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate('hello world'));
        $this->assertFalse($pred->evaluate('world hello'));
        $this->assertSame('/^hello/', $pred->getExpectedOutcome());
    }

    // Spec 7: hasCount() checks count
    public function testHasCount(): void
    {
        $pred = CTGTestPredicates::hasCount(3);
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate([1, 2, 3]));
        $this->assertFalse($pred->evaluate([1, 2]));
        $this->assertFalse($pred->evaluate([1, 2, 3, 4]));
        $this->assertSame(3, $pred->getExpectedOutcome());
    }

    // Spec 7: satisfies() wraps custom closure, expectedOutcome is '(custom)'
    public function testSatisfies(): void
    {
        $pred = CTGTestPredicates::satisfies(fn(mixed $v): bool => $v > 5 && $v < 10);
        $this->assertInstanceOf(CTGTestPredicate::class, $pred);
        $this->assertTrue($pred->evaluate(7));
        $this->assertFalse($pred->evaluate(3));
        $this->assertFalse($pred->evaluate(15));
        $this->assertSame('(custom)', $pred->getExpectedOutcome());
    }

    // Spec 7: Each returns a CTGTestPredicate instance
    public function testAllMethodsReturnPredicateInstances(): void
    {
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::equals(1));
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::notEquals(1));
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::isNull());
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::isNotNull());
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::isTrue());
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::isFalse());
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::isTruthy());
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::isFalsy());
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::isInstanceOf(\stdClass::class));
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::isType('string'));
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::greaterThan(1));
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::lessThan(1));
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::contains('x'));
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::matchesPattern('/x/'));
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::hasCount(1));
        $this->assertInstanceOf(CTGTestPredicate::class, CTGTestPredicates::satisfies(fn($v) => true));
    }
}
