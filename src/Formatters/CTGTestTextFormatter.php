<?php
declare(strict_types=1);

namespace CTG\Test\Formatters;

use CTG\Test\CTGTestState;
use CTG\Test\CTGTestResult;
use CTG\Test\CTGTestStatus;

/**
 * CTGTestTextFormatter
 *
 * Reference text formatter per spec section 6. Renders a header, one
 * line per result with status brackets padded to 10 characters, indented
 * detail lines for FAIL and ERROR results, a summary line, and a final
 * aggregate result line. Skip directives never appear in the result
 * trace — only stage/assert/chain-child results and skipped entries.
 */
class CTGTestTextFormatter implements CTGTestFormatterInterface {

    /* Constants */

    private const LABEL_SEPARATOR  = ' > ';
    private const STATUS_COLUMN    = 10;
    private const DETAIL_INDENT    = '              ';
    private const LINE_INDENT      = '  ';

    /* Static Methods */

    // Static :: ctgTestState -> STRING
    // Transforms a final state into the spec's text output format.
    public static function format(CTGTestState $state): string {
        $lines = [];
        $lines[] = 'Pipeline: ' . $state->getLabel();
        $lines[] = '';

        $results = $state->getResults();

        $passCount    = 0;
        $failCount    = 0;
        $skippedCount = 0;
        $errorCount   = 0;

        foreach ($results as $result) {
            $labelPath = implode(self::LABEL_SEPARATOR, $result->_label);

            if ($result->_skipped) {
                $lines[] = self::LINE_INDENT . self::padStatus('[SKIPPED]') . $labelPath;
                $skippedCount++;
                continue;
            }

            $status = $result->_status;

            if ($status === CTGTestStatus::PASS) {
                $lines[] = self::LINE_INDENT . self::padStatus('[PASS]') . $labelPath;
                $passCount++;
            } elseif ($status === CTGTestStatus::FAIL) {
                $lines[] = self::LINE_INDENT . self::padStatus('[FAIL]') . $labelPath;
                $lines[] = self::DETAIL_INDENT . 'computed: ' . self::formatValue($result->_computedValue);
                $lines[] = self::DETAIL_INDENT . 'expected: ' . self::formatValue($result->_expectedOutcome);
                $failCount++;
            } elseif ($status === CTGTestStatus::ERROR) {
                $lines[] = self::LINE_INDENT . self::padStatus('[ERROR]') . $labelPath;
                $error = $result->_error;
                if ($error instanceof \Throwable) {
                    $className = (new \ReflectionClass($error))->getShortName();
                    $lines[] = self::DETAIL_INDENT . 'error: ' . $className . ': ' . $error->getMessage();
                }
                $errorCount++;
            }
        }

        $total = count($results);

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = sprintf(
            '%d passed, %d failed, %d skipped, %d errored (%d total)',
            $passCount,
            $failCount,
            $skippedCount,
            $errorCount,
            $total
        );
        $lines[] = 'Result: ' . self::resolveWorstStatus($passCount, $failCount, $skippedCount, $errorCount, $total);

        return implode("\n", $lines);
    }

    /* Private Methods */

    // Static :: STRING -> STRING
    // Pads the status bracket string to the fixed status column width.
    private static function padStatus(string $statusBracket): string {
        return str_pad($statusBracket, self::STATUS_COLUMN, ' ', STR_PAD_RIGHT);
    }

    // Static :: INT, INT, INT, INT, INT -> STRING
    // Aggregates the worst status across results. ERROR > FAIL > PASS.
    // VOID when every result is skipped (or no results at all).
    private static function resolveWorstStatus(
        int $passCount,
        int $failCount,
        int $skippedCount,
        int $errorCount,
        int $total
    ): string {
        if ($errorCount > 0) {
            return 'ERROR';
        }
        if ($failCount > 0) {
            return 'FAIL';
        }
        if ($passCount > 0) {
            return 'PASS';
        }
        // No PASS/FAIL/ERROR — either everything was skipped, or nothing ran.
        return 'VOID';
    }

    // Static :: MIXED -> STRING
    // Renders a value for display in computed/expected detail lines.
    private static function formatValue(mixed $value): string {
        if ($value === null) {
            return 'null';
        }
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            // Escape backslash first so the backslashes we introduce below
            // don't get double-escaped.
            $escaped = str_replace('\\', '\\\\', $value);
            // Common whitespace escapes as readable literal sequences.
            $escaped = str_replace(
                ["\n", "\r", "\t"],
                ['\\n', '\\r', '\\t'],
                $escaped
            );
            // Any remaining control byte (0x00-0x1F except the three above,
            // plus 0x7F DEL) is rendered as \xHH. This covers NUL, bell,
            // backspace, form feed, escape, and all other C0/DEL bytes
            // that would otherwise produce ambiguous or invisible output.
            $escaped = preg_replace_callback(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                fn(array $m): string => sprintf('\\x%02X', ord($m[0])),
                $escaped
            );
            // Escape single quotes (the delimiter we wrap the string in).
            $escaped = str_replace("'", "\\'", $escaped);
            return "'" . $escaped . "'";
        }
        if (is_array($value)) {
            return 'array(' . count($value) . ')';
        }
        if (is_object($value)) {
            return 'object(' . (new \ReflectionClass($value))->getShortName() . ')';
        }
        if (is_resource($value)) {
            return 'resource(' . get_resource_type($value) . ')';
        }
        return '(unknown)';
    }
}
