<?php

// Framework exception class for ctg-php-test — typed codes, bidirectional lookup, structured data
class TestError extends \Exception {

    /* Constants */

    // Definition-time errors (1xxx)
    public const INVALID_STEP = 1000;
    public const INVALID_CHAIN = 1001;
    public const INVALID_CONFIG = 1002;
    public const INVALID_EXPECTED = 1003;
    public const INVALID_SKIP = 1004;

    // Runtime errors (2xxx)
    public const FORMATTER_ERROR = 2000;
    public const RUNNER_ERROR = 2001;

    // Bidirectional type map: name <=> code
    public const TYPES = [
        'INVALID_STEP'     => self::INVALID_STEP,
        'INVALID_CHAIN'    => self::INVALID_CHAIN,
        'INVALID_CONFIG'   => self::INVALID_CONFIG,
        'INVALID_EXPECTED' => self::INVALID_EXPECTED,
        'INVALID_SKIP'     => self::INVALID_SKIP,
        'FORMATTER_ERROR'  => self::FORMATTER_ERROR,
        'RUNNER_ERROR'     => self::RUNNER_ERROR,
    ];

    /* Instance Properties */
    public readonly string $type;
    public readonly string $msg;
    public readonly ?array $data;

    // CONSTRUCTOR :: STRING|INT, ?STRING, ?ARRAY -> $this
    // Creates a TestError from type name or code, optional message, optional structured data
    // NOTE: Integer code is passed to parent::__construct so getCode() works natively
    public function __construct(string|int $type, ?string $message = null, ?array $data = null) {
        if (is_string($type)) {
            if (!isset(self::TYPES[$type])) {
                throw new \InvalidArgumentException("Unknown TestError type: {$type}");
            }
            $this->type = $type;
            $code = self::TYPES[$type];
        } else {
            $flipped = array_flip(self::TYPES);
            if (!isset($flipped[$type])) {
                throw new \InvalidArgumentException("Unknown TestError code: {$type}");
            }
            $this->type = $flipped[$type];
            $code = $type;
        }

        $this->msg = $message ?? $this->type;
        $this->data = $data;

        parent::__construct($this->msg, $code);
    }

    /**
     *
     * Static Methods
     *
     */

    // :: STRING|INT -> STRING|INT
    // Bidirectional lookup: string type name to int code, or int code to string type name
    public static function lookup(string|int $value): string|int {
        if (is_string($value)) {
            if (!isset(self::TYPES[$value])) {
                throw new \InvalidArgumentException("Unknown TestError type: {$value}");
            }
            return self::TYPES[$value];
        }

        $flipped = array_flip(self::TYPES);
        if (!isset($flipped[$value])) {
            throw new \InvalidArgumentException("Unknown TestError code: {$value}");
        }
        return $flipped[$value];
    }
}
