<?php
declare(strict_types=1);

namespace CTG\Test;

// Value object representing a single step definition in a test pipeline
class CTGTestStep {

    /* Constants */
    public const TYPE_STAGE = 'stage';
    public const TYPE_ASSERT = 'assert';
    public const TYPE_ASSERT_ANY = 'assert-any';
    public const TYPE_CHAIN = 'chain';

    /* Instance Properties */
    private readonly string $_type;
    private readonly string $_name;
    private readonly mixed $_fn;
    private readonly mixed $_expected;
    private readonly mixed $_errorHandler;

    // CONSTRUCTOR :: STRING, STRING, MIXED, MIXED, MIXED -> $this
    // Creates a step definition with type, name, callable/test, expected value, and optional error handler
    // NOTE: No validation here — all validation deferred to start()
    public function __construct(
        string $type,
        string $name,
        mixed $fn,
        mixed $expected = null,
        mixed $errorHandler = null
    ) {
        $this->_type = $type;
        $this->_name = trim($name);
        $this->_fn = $fn;
        $this->_expected = $expected;
        $this->_errorHandler = $errorHandler;
    }

    /**
     *
     * Instance Methods
     *
     */

    // :: VOID -> STRING
    // Returns the step type: 'stage', 'assert', 'assert-any', or 'chain'
    public function getType(): string {
        return $this->_type;
    }

    // :: VOID -> STRING
    // Returns the trimmed step name
    public function getName(): string {
        return $this->_name;
    }

    // :: VOID -> MIXED
    // Returns the callable (for stage/assert) or CTGTest instance (for chain)
    public function getFn(): mixed {
        return $this->_fn;
    }

    // :: VOID -> MIXED
    // Returns the expected value (assert steps only)
    public function getExpected(): mixed {
        return $this->_expected;
    }

    // :: VOID -> MIXED
    // Returns the error handler callable, or null
    public function getErrorHandler(): mixed {
        return $this->_errorHandler;
    }
}
