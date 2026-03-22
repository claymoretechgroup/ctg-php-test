<?php
declare(strict_types=1);

namespace CTG\Test\Formatters;

// Contract for test report formatters — all formatters produce a string from a report array
interface CTGTestFormatterInterface {

    // :: ARRAY -> STRING
    // Formats a report array into a string representation
    public static function format(array $report): string;
}
