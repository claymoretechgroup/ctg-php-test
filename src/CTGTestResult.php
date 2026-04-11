<?php
declare(strict_types=1);

namespace CTG\Test;

/**
 * CTGTestResult
 *
 * Value object realizing the RESULT primitive. Fields are immutable
 * (readonly) and produced only by the framework through the three
 * named factory methods. There is no public constructor — shape
 * correctness is enforced by the factories, not by ad-hoc callers.
 */
class CTGTestResult {

    /* Instance Properties */

    public readonly array $_label;
    public readonly bool $_skipped;
    public readonly ?CTGTestStatus $_status;
    public readonly mixed $_computedValue;
    public readonly mixed $_expectedOutcome;
    public readonly ?\Throwable $_error;

    // CONSTRUCTOR :: [STRING], BOOL, ?ctgTestStatus, MIXED, MIXED, ?\Throwable -> ctgTestResult
    // Private — use stageResult / assertResult / skippedResult factories.
    private function __construct(
        array $label,
        bool $skipped,
        ?CTGTestStatus $status,
        mixed $computedValue,
        mixed $expectedOutcome,
        ?\Throwable $error
    ) {
        $this->_label           = $label;
        $this->_skipped         = $skipped;
        $this->_status          = $status;
        $this->_computedValue   = $computedValue;
        $this->_expectedOutcome = $expectedOutcome;
        $this->_error           = $error;
    }

    /* Static Factory Methods */

    // Static :: [STRING], ctgTestStatus, ?\Throwable -> ctgTestResult
    // Creates a stage result. computedValue and expectedOutcome are VOID (null)
    // because stages do not produce computed-vs-expected diagnostics.
    public static function stageResult(array $label, CTGTestStatus $status, ?\Throwable $error = null): static {
        return new static(
            $label,
            false,
            $status,
            null,
            null,
            $error
        );
    }

    // Static :: [STRING], ctgTestStatus, MIXED, MIXED, ?\Throwable -> ctgTestResult
    // Creates an assert result. computedValue/expectedOutcome may be null when
    // the handler itself threw before producing a value.
    public static function assertResult(
        array $label,
        CTGTestStatus $status,
        mixed $computedValue = null,
        mixed $expectedOutcome = null,
        ?\Throwable $error = null
    ): static {
        return new static(
            $label,
            false,
            $status,
            $computedValue,
            $expectedOutcome,
            $error
        );
    }

    // Static :: [STRING] -> ctgTestResult
    // Creates a skipped result. status is null, all value fields are null,
    // skipped is true. Applies to stage, assert, and chain operations alike.
    public static function skippedResult(array $label): static {
        return new static(
            $label,
            true,
            null,
            null,
            null,
            null
        );
    }
}
