<?php
declare(strict_types=1);

namespace CTG\Test;

/**
 * CTGTestStatus
 *
 * Backed string enum realizing the STATUS primitive from the design
 * doc. Exactly three cases — PASS, FAIL, ERROR. `skipped` is a bool
 * field on RESULT, not a status; RECOVERED is not a status value.
 */
enum CTGTestStatus: string {

    case PASS  = 'PASS';
    case FAIL  = 'FAIL';
    case ERROR = 'ERROR';
}
