<?php
declare(strict_types=1);

namespace CTG\Test;

// Structured result data for step outcomes and report aggregation
class CTGTestResult {

    /* Constants */
    public const STATUS_PASS = 'pass';
    public const STATUS_FAIL = 'fail';
    public const STATUS_ERROR = 'error';
    public const STATUS_RECOVERED = 'recovered';
    public const STATUS_SKIP = 'skip';

    // Severity order for aggregate status derivation (highest to lowest)
    public const SEVERITY = [
        self::STATUS_ERROR     => 5,
        self::STATUS_FAIL      => 4,
        self::STATUS_RECOVERED => 3,
        self::STATUS_PASS      => 2,
        self::STATUS_SKIP      => 1,
    ];

    /**
     *
     * Static Methods
     *
     */

    // Static Factory Method :: STRING, STRING, STRING, INT, ?STRING, ?ARRAY -> ARRAY
    // Creates a step result array for stage steps
    public static function stepResult(
        string $type,
        string $name,
        string $status,
        int $durationMs,
        ?string $message = null,
        ?array $exception = null
    ): array {
        return [
            'type' => $type,
            'name' => $name,
            'status' => $status,
            'duration_ms' => $durationMs,
            'message' => $message,
            'exception' => $exception,
        ];
    }

    // :: STRING, STRING, INT, MIXED, MIXED, ?STRING, ?ARRAY -> ARRAY
    // Creates a step result array for assert steps with actual/expected values
    public static function assertResult(
        string $name,
        string $status,
        int $durationMs,
        mixed $actual,
        mixed $expected,
        ?string $message = null,
        ?array $exception = null
    ): array {
        return [
            'type' => 'assert',
            'name' => $name,
            'status' => $status,
            'duration_ms' => $durationMs,
            'message' => $message,
            'exception' => $exception,
            'actual' => $actual,
            'expected' => $expected,
        ];
    }

    // :: STRING, STRING, INT, ?STRING, ?ARRAY, ARRAY, ARRAY -> ARRAY
    // Creates a chain step result with nested children and aggregate counts
    public static function chainResult(
        string $name,
        string $status,
        int $durationMs,
        ?string $message,
        ?array $exception,
        array $steps,
        array $counts
    ): array {
        return [
            'type' => 'chain',
            'name' => $name,
            'status' => $status,
            'duration_ms' => $durationMs,
            'message' => $message,
            'exception' => $exception,
            'steps' => $steps,
            'passed' => $counts['passed'],
            'failed' => $counts['failed'],
            'skipped' => $counts['skipped'],
            'recovered' => $counts['recovered'],
            'errored' => $counts['errored'],
            'total' => $counts['total'],
        ];
    }

    // :: STRING, ARRAY -> ARRAY
    // Creates a root report (not a step result — no type field)
    public static function report(string $name, array $steps): array {
        $counts = self::countSteps($steps);
        $status = self::aggregateStatus($steps);
        $durationMs = self::sumDuration($steps);

        return [
            'name' => $name,
            'status' => $status,
            'passed' => $counts['passed'],
            'failed' => $counts['failed'],
            'skipped' => $counts['skipped'],
            'recovered' => $counts['recovered'],
            'errored' => $counts['errored'],
            'total' => $counts['total'],
            'duration_ms' => $durationMs,
            'steps' => $steps,
        ];
    }

    // :: ARRAY -> STRING
    // Derives aggregate status from child steps using severity order
    // NOTE: Empty steps array returns 'pass' by special case (defined base case)
    public static function aggregateStatus(array $steps): string {
        if (empty($steps)) {
            return self::STATUS_PASS;
        }

        $worst = self::STATUS_SKIP;
        foreach ($steps as $step) {
            $stepStatus = $step['status'];
            if ((self::SEVERITY[$stepStatus] ?? 0) > (self::SEVERITY[$worst] ?? 0)) {
                $worst = $stepStatus;
            }
        }
        return $worst;
    }

    // :: ARRAY -> ARRAY
    // Counts steps by status at the current level only
    public static function countSteps(array $steps): array {
        $counts = [
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'recovered' => 0,
            'errored' => 0,
            'total' => count($steps),
        ];

        foreach ($steps as $step) {
            match ($step['status']) {
                self::STATUS_PASS => $counts['passed']++,
                self::STATUS_FAIL => $counts['failed']++,
                self::STATUS_SKIP => $counts['skipped']++,
                self::STATUS_RECOVERED => $counts['recovered']++,
                self::STATUS_ERROR => $counts['errored']++,
                default => null,
            };
        }

        return $counts;
    }

    // :: ARRAY -> INT
    // Sums duration_ms across steps at the current level
    public static function sumDuration(array $steps): int {
        $total = 0;
        foreach ($steps as $step) {
            $total += $step['duration_ms'];
        }
        return $total;
    }

    // :: \Throwable, BOOL, ?ARRAY -> ARRAY
    // Converts an exception to the structured exception array format
    public static function formatException(\Throwable $e, bool $includeTrace = false, ?array $causedBy = null): array {
        $result = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ];

        if ($includeTrace) {
            $result['trace'] = $e->getTraceAsString();
        }

        $result['caused_by'] = $causedBy;

        return $result;
    }

    // :: INT, INT, INT -> ?STRING
    // Generates canonical chain message from child counts
    // NOTE: Returns null if no failures or errors — status alone communicates pass/skip/recovered
    public static function chainMessage(int $failed, int $errored, int $total): ?string {
        if ($failed === 0 && $errored === 0) {
            return null;
        }
        return "{$failed} failed, {$errored} errored in {$total} steps";
    }

    // :: MIXED -> STRING
    // Formats a value for display in messages using var_export short form for scalars
    // and type summaries for complex values
    public static function formatValue(mixed $value): string {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }
        if (is_array($value)) {
            return 'array(' . count($value) . ')';
        }
        if (is_object($value)) {
            return 'object(' . get_class($value) . ')';
        }
        if (is_resource($value)) {
            return 'resource(' . get_resource_type($value) . ')';
        }
        return gettype($value);
    }
}
