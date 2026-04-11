<?php
declare(strict_types=1);

namespace CTG\Test\Formatters;

use CTG\Test\CTGTestState;

/**
 * CTGTestFormatterInterface
 *
 * Contract for the FORMATTER primitive — transforms a final CTGTestState
 * into a string representation. Formatters consume STATE only; any
 * formatter-specific configuration (indentation, colour, verbosity) is
 * the formatter's own concern.
 */
interface CTGTestFormatterInterface {

    // Static :: ctgTestState -> STRING
    public static function format(CTGTestState $state): string;
}
