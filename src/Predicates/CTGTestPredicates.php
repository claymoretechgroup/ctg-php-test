<?php
declare(strict_types=1);

namespace CTG\Test\Predicates;

use CTG\Test\CTGTestPredicate;

/**
 * CTGTestPredicates
 *
 * Static convenience builders that construct CTGTestPredicate
 * instances for the most common assertion shapes. All comparisons
 * use strict (===/!==) equality. For loose comparison or custom
 * logic, use satisfies() with a closure.
 */
class CTGTestPredicates {

    /* Static Methods */

    // Static :: MIXED -> ctgTestPredicate
    // Strict equality (===) against the expected value.
    public static function equals(mixed $expected): CTGTestPredicate {
        return CTGTestPredicate::init(
            $expected,
            fn(mixed $value): bool => $value === $expected
        );
    }

    // Static :: MIXED -> ctgTestPredicate
    // Strict inequality (!==) against the expected value.
    public static function notEquals(mixed $expected): CTGTestPredicate {
        return CTGTestPredicate::init(
            $expected,
            fn(mixed $value): bool => $value !== $expected
        );
    }

    // Static :: VOID -> ctgTestPredicate
    // Value is null.
    public static function isNull(): CTGTestPredicate {
        return CTGTestPredicate::init(
            null,
            fn(mixed $value): bool => $value === null
        );
    }

    // Static :: VOID -> ctgTestPredicate
    // Value is not null.
    public static function isNotNull(): CTGTestPredicate {
        return CTGTestPredicate::init(
            'not null',
            fn(mixed $value): bool => $value !== null
        );
    }

    // Static :: VOID -> ctgTestPredicate
    // Value is truthy — (bool)$value === true.
    public static function isTruthy(): CTGTestPredicate {
        return CTGTestPredicate::init(
            'truthy',
            fn(mixed $value): bool => (bool) $value === true
        );
    }

    // Static :: VOID -> ctgTestPredicate
    // Value is falsy — (bool)$value === false.
    public static function isFalsy(): CTGTestPredicate {
        return CTGTestPredicate::init(
            'falsy',
            fn(mixed $value): bool => (bool) $value === false
        );
    }

    // Static :: VOID -> ctgTestPredicate
    // Value is strictly true.
    public static function isTrue(): CTGTestPredicate {
        return CTGTestPredicate::init(
            true,
            fn(mixed $value): bool => $value === true
        );
    }

    // Static :: VOID -> ctgTestPredicate
    // Value is strictly false.
    public static function isFalse(): CTGTestPredicate {
        return CTGTestPredicate::init(
            false,
            fn(mixed $value): bool => $value === false
        );
    }

    // Static :: STRING -> ctgTestPredicate
    // Value is an instance of the given class name.
    public static function isInstanceOf(string $className): CTGTestPredicate {
        return CTGTestPredicate::init(
            $className,
            fn(mixed $value): bool => $value instanceof $className
        );
    }

    // Static :: STRING -> ctgTestPredicate
    // gettype($value) matches the given type name.
    public static function isType(string $type): CTGTestPredicate {
        return CTGTestPredicate::init(
            $type,
            fn(mixed $value): bool => gettype($value) === $type
        );
    }

    // Static :: MIXED -> ctgTestPredicate
    // Value is greater than the expected value.
    public static function greaterThan(mixed $expected): CTGTestPredicate {
        return CTGTestPredicate::init(
            $expected,
            fn(mixed $value): bool => $value > $expected
        );
    }

    // Static :: MIXED -> ctgTestPredicate
    // Value is less than the expected value.
    public static function lessThan(mixed $expected): CTGTestPredicate {
        return CTGTestPredicate::init(
            $expected,
            fn(mixed $value): bool => $value < $expected
        );
    }

    // Static :: STRING -> ctgTestPredicate
    // String value contains the expected substring.
    public static function contains(string $expected): CTGTestPredicate {
        return CTGTestPredicate::init(
            $expected,
            fn(mixed $value): bool => is_string($value) && str_contains($value, $expected)
        );
    }

    // Static :: STRING -> ctgTestPredicate
    // String value matches the given regex pattern.
    public static function matchesPattern(string $pattern): CTGTestPredicate {
        return CTGTestPredicate::init(
            $pattern,
            fn(mixed $value): bool => is_string($value) && preg_match($pattern, $value) === 1
        );
    }

    // Static :: INT -> ctgTestPredicate
    // Array or Countable has the expected count.
    public static function hasCount(int $expected): CTGTestPredicate {
        return CTGTestPredicate::init(
            $expected,
            fn(mixed $value): bool => (is_array($value) || $value instanceof \Countable)
                && count($value) === $expected
        );
    }

    // Static :: (MIXED -> BOOL) -> ctgTestPredicate
    // Custom predicate from a closure. ExpectedOutcome is '(custom)'.
    public static function satisfies(\Closure $fn): CTGTestPredicate {
        return CTGTestPredicate::init(
            '(custom)',
            $fn
        );
    }
}
