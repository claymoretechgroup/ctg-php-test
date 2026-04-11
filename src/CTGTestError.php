<?php
declare(strict_types=1);

namespace CTG\Test;

/**
 * CTGTestError
 *
 * Framework error class realizing the FRAMEWORK_ERROR primitive. Extends
 * \Exception so that thrown errors are catchable with the native PHP
 * exception machinery. Canonical error types have integer codes in the
 * ranges documented by the design doc; CHAIN_DEPTH_EXCEEDED is a
 * structural enforcement error outside the canonical range.
 */
class CTGTestError extends \Exception {

    /* Constants */

    // Canonical validation errors (1xxx)
    public const INVALID_OPERATION         = 1000;
    public const INVALID_CHAIN             = 1001;
    public const INVALID_CONFIG            = 1002;
    public const INVALID_EXPECTED_OUTCOME  = 1003;
    public const INVALID_SKIP              = 1004;

    // Canonical runtime errors (2xxx)
    public const FORMATTER_ERROR           = 2000;
    public const RUNNER_ERROR              = 2001;

    // Structural enforcement errors (1100-1199)
    public const CHAIN_DEPTH_EXCEEDED      = 1100;

    // Bidirectional type map: name => code
    public const TYPES = [
        'INVALID_OPERATION'        => 1000,
        'INVALID_CHAIN'            => 1001,
        'INVALID_CONFIG'           => 1002,
        'INVALID_EXPECTED_OUTCOME' => 1003,
        'INVALID_SKIP'             => 1004,
        'FORMATTER_ERROR'          => 2000,
        'RUNNER_ERROR'             => 2001,
        'CHAIN_DEPTH_EXCEEDED'     => 1100,
    ];

    /* Instance Properties */

    public readonly string $type;
    public readonly string $msg;
    public readonly mixed $data;

    // CONSTRUCTOR :: STRING|INT, ?STRING, MIXED -> ctgTestError
    // Creates an error from a canonical type (name or code), optional message and data.
    public function __construct(string|int $type, ?string $message = null, mixed $data = null) {
        // Normalize the type identifier. `lookup()` throws InvalidArgumentException
        // for anything outside the TYPES map — let it propagate; constructing an
        // error with an unknown type is itself a caller bug.
        if (is_int($type)) {
            $code = $type;
            $name = (string) self::lookup($type);
        } else {
            $code = (int) self::lookup($type);
            $name = $type;
        }

        $msg = $message ?? $name;

        parent::__construct($msg, $code);

        $this->type = $name;
        $this->msg  = $msg;
        $this->data = $data;
    }

    /* Static Methods */

    // Static :: STRING|INT -> STRING|INT
    // Bidirectional lookup — name to code or code to name. Throws
    // InvalidArgumentException for unknown values.
    public static function lookup(string|int $value): string|int {
        if (is_string($value)) {
            if (!array_key_exists($value, self::TYPES)) {
                throw new \InvalidArgumentException("Unknown CTGTestError type name: {$value}");
            }
            return self::TYPES[$value];
        }

        $name = array_search($value, self::TYPES, true);
        if ($name === false) {
            throw new \InvalidArgumentException("Unknown CTGTestError type code: {$value}");
        }
        return $name;
    }
}
