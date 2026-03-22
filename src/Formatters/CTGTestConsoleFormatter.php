<?php
declare(strict_types=1);

namespace CTG\Test\Formatters;

// Human-readable console output formatter for test reports
class CTGTestConsoleFormatter implements CTGTestFormatterInterface {

    /**
     *
     * Static Methods
     *
     */

    // :: ARRAY -> STRING
    // Formats a report tree into a human-readable string
    public static function format(array $report): string {
        $output = $report['name'] . "\n";
        $output .= self::_formatSteps($report['steps'], 1);
        $output .= "\n";
        $output .= self::_formatSummary($report);
        $output .= "\n";

        return $output;
    }

    // :: ARRAY, INT -> STRING
    // Recursively formats steps with indentation
    private static function _formatSteps(array $steps, int $depth): string {
        $output = '';
        $indent = str_repeat('  ', $depth);

        foreach ($steps as $step) {
            $type = $step['type'];
            $name = $step['name'];
            $status = strtoupper($step['status']);
            $duration = $step['duration_ms'];
            $message = $step['message'] ?? null;

            if ($type === 'chain') {
                // Fix #12: Show chain pass/fail status
                $chainStatus = strtoupper($step['status']);
                $output .= "{$indent}[chain]  {$name} ({$duration}ms) ... {$chainStatus}\n";
                $output .= self::_formatSteps($step['steps'], $depth + 1);
            } else {
                $label = "[{$type}]";
                $line = "{$indent}{$label}  {$name} ({$duration}ms)";

                // Pad with dots
                $padLength = max(50 - strlen($line), 2);
                $padding = ' ' . str_repeat('.', $padLength) . ' ';

                $output .= "{$line}{$padding}{$status}\n";

                if ($message !== null) {
                    $output .= "{$indent}  {$message}\n";
                }
            }
        }

        return $output;
    }

    // :: ARRAY -> STRING
    // Formats the summary line with counts and duration
    private static function _formatSummary(array $report): string {
        $parts = [];
        $parts[] = "{$report['passed']} passed";
        $parts[] = "{$report['failed']} failed";
        $parts[] = "{$report['skipped']} skipped";
        $parts[] = "{$report['recovered']} recovered";
        $parts[] = "{$report['errored']} errored";

        return implode(', ', $parts) . " ({$report['duration_ms']}ms)";
    }
}
