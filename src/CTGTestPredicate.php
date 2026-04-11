<?php
declare(strict_types=1);

namespace CTG\Test;

/**
 * CTGTestPredicate
 *
 * Realizes the PREDICATE primitive. Carries an expected-outcome value
 * for diagnostic display and an evaluate closure that maps a computed
 * value to a bool. The predicate does not determine status — the
 * pipeline maps true to PASS and false to FAIL; an exception from
 * evaluate maps to ERROR.
 */
class CTGTestPredicate {

    /* Instance Properties */

    private readonly mixed $_expectedOutcome;
    private readonly \Closure $_evaluate;

    // CONSTRUCTOR :: MIXED, (MIXED -> BOOL) -> ctgTestPredicate
    // Private — use init() factory.
    private function __construct(mixed $expectedOutcome, \Closure $evaluate) {
        $this->_expectedOutcome = $expectedOutcome;
        $this->_evaluate        = $evaluate;
    }

    /* Instance Methods */

    // :: VOID -> MIXED
    public function getExpectedOutcome(): mixed {
        return $this->_expectedOutcome;
    }

    // :: MIXED -> BOOL
    // Applies the evaluate closure to the computed value and returns bool.
    public function evaluate(mixed $value): bool {
        return ($this->_evaluate)($value);
    }

    /* Static Methods */

    // Static Factory :: MIXED, (MIXED -> BOOL) -> ctgTestPredicate
    public static function init(mixed $expectedOutcome, \Closure $evaluate): static {
        return new static($expectedOutcome, $evaluate);
    }
}
