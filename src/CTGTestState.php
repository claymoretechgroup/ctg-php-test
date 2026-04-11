<?php
declare(strict_types=1);

namespace CTG\Test;

/**
 * CTGTestState
 *
 * State carrier realizing the STATE primitive. Holds the pipeline
 * label, the current subject, the current computed value, and the
 * append-only list of results. Setters exist for the pipeline's own
 * slot-deposit model; callers receive state from start() and should
 * only read it.
 */
class CTGTestState {

    /* Instance Properties */

    private string $_label;
    private mixed $_subject;
    private mixed $_computed;
    private array $_results;

    // CONSTRUCTOR :: STRING, MIXED -> ctgTestState
    // Private — use init() factory.
    private function __construct(string $label, mixed $subject) {
        $this->_label    = $label;
        $this->_subject  = $subject;
        $this->_computed = null;
        $this->_results  = [];
    }

    /* Instance Methods */

    // :: VOID -> STRING
    public function getLabel(): string {
        return $this->_label;
    }

    // :: VOID -> MIXED
    public function getSubject(): mixed {
        return $this->_subject;
    }

    // :: MIXED -> VOID
    public function setSubject(mixed $subject): void {
        $this->_subject = $subject;
    }

    // :: VOID -> MIXED
    public function getComputed(): mixed {
        return $this->_computed;
    }

    // :: MIXED -> VOID
    public function setComputed(mixed $computed): void {
        $this->_computed = $computed;
    }

    // :: VOID -> [ctgTestResult]
    public function getResults(): array {
        return $this->_results;
    }

    // :: ctgTestResult -> VOID
    public function addResult(CTGTestResult $result): void {
        $this->_results[] = $result;
    }

    /* Static Methods */

    // Static Factory :: STRING, MIXED -> ctgTestState
    public static function init(string $label, mixed $subject): static {
        return new static($label, $subject);
    }
}
