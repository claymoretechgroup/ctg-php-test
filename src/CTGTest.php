<?php
declare(strict_types=1);

namespace CTG\Test;

/**
 * CTGTest
 *
 * Realizes the PIPELINE primitive. Builder methods append tagged
 * operations to an internal list; start() validates the entire
 * pipeline up-front then executes it, returning the final
 * CTGTestState. Builder arguments are deliberately typed mixed so
 * that every validation error surfaces as a canonical
 * CTGTestError from start(), never as a native TypeError at the
 * builder call site.
 */
class CTGTest {

    /* Constants */

    // Maximum chain nesting depth enforced by validation walker.
    private const MAX_CHAIN_DEPTH = 64;

    // Default configuration keys and values.
    private const DEFAULT_CONFIG = [
        'haltOnFailure' => true,
        'timeout'       => 5000,
    ];

    // Recognized config keys.
    private const ALLOWED_CONFIG_KEYS = ['haltOnFailure', 'timeout'];

    // Internal operation kind tags.
    private const OP_STAGE  = 'stage';
    private const OP_ASSERT = 'assert';
    private const OP_CHAIN  = 'chain';
    private const OP_SKIP   = 'skip';

    /* Instance Properties */

    private string $_label;
    private array $_operations;

    // CONSTRUCTOR :: STRING -> ctgTest
    // Private — use init() factory.
    // NOTE: Label is trimmed on construction so stored/emitted labels
    // match the validated form. Empty-after-trim is still caught in
    // start() validation as INVALID_OPERATION.
    private function __construct(string $label) {
        $this->_label      = trim($label);
        $this->_operations = [];
    }

    /* Instance Methods */

    // :: VOID -> STRING
    public function getLabel(): string {
        return $this->_label;
    }

    // :: STRING, MIXED -> $this
    // Appends a stage operation. fn is accepted as mixed; type
    // validation happens in start() so errors surface as canonical
    // CTGTestError instances with structured diagnostic data.
    public function stage(string $label, mixed $fn): static {
        $this->_operations[] = [
            'type'  => self::OP_STAGE,
            'label' => trim($label),
            'fn'    => $fn,
        ];
        return $this;
    }

    // :: STRING, MIXED, MIXED -> $this
    // Appends an assert operation.
    public function assert(string $label, mixed $fn, mixed $predicate): static {
        $this->_operations[] = [
            'type'      => self::OP_ASSERT,
            'label'     => trim($label),
            'fn'        => $fn,
            'predicate' => $predicate,
        ];
        return $this;
    }

    // :: STRING, MIXED -> $this
    // Appends a chain operation targeting a sub-pipeline.
    public function chain(string $label, mixed $pipeline): static {
        $this->_operations[] = [
            'type'     => self::OP_CHAIN,
            'label'    => trim($label),
            'pipeline' => $pipeline,
        ];
        return $this;
    }

    // :: STRING, MIXED -> $this
    // Appends a skip directive. Skips have no label of their own —
    // they are identified by the target they gate. Condition is
    // optional (null means unconditional).
    public function skip(string $targetLabel, mixed $condition = null): static {
        $this->_operations[] = [
            'type'        => self::OP_SKIP,
            'targetLabel' => trim($targetLabel),
            'condition'   => $condition,
        ];
        return $this;
    }

    // :: MIXED, ?ARRAY -> ctgTestState
    // Validates and executes the pipeline. Returns the final state.
    public function start(mixed $subject, array $config = []): CTGTestState {
        $resolvedConfig = self::resolveConfig($config);
        self::validatePipeline($this, 0);

        $state = CTGTestState::init($this->_label, $subject);
        self::executePipeline($this, $state, $resolvedConfig, []);
        return $state;
    }

    /* Static Methods */

    // Static Factory :: STRING -> ctgTest
    public static function init(string $label): static {
        return new static($label);
    }

    /* Private Methods */

    // Static :: ARRAY -> ARRAY
    // Validates config keys/values and merges with defaults.
    private static function resolveConfig(array $config): array {
        foreach ($config as $key => $value) {
            if (!in_array($key, self::ALLOWED_CONFIG_KEYS, true)) {
                throw new CTGTestError(
                    'INVALID_CONFIG',
                    "Unknown config key: {$key}",
                    ['key' => $key]
                );
            }
        }

        $haltOnFailure = array_key_exists('haltOnFailure', $config)
            ? $config['haltOnFailure']
            : self::DEFAULT_CONFIG['haltOnFailure'];

        if (!is_bool($haltOnFailure)) {
            throw new CTGTestError(
                'INVALID_CONFIG',
                'haltOnFailure must be bool',
                ['key' => 'haltOnFailure', 'value' => $haltOnFailure, 'expected' => 'bool']
            );
        }

        $timeout = array_key_exists('timeout', $config)
            ? $config['timeout']
            : self::DEFAULT_CONFIG['timeout'];

        if (!is_int($timeout)) {
            throw new CTGTestError(
                'INVALID_CONFIG',
                'timeout must be int',
                ['key' => 'timeout', 'value' => $timeout, 'expected' => 'int']
            );
        }

        if ($timeout < 0) {
            throw new CTGTestError(
                'INVALID_CONFIG',
                'timeout must be >= 0',
                ['key' => 'timeout', 'value' => $timeout, 'constraint' => '>= 0']
            );
        }

        return [
            'haltOnFailure' => $haltOnFailure,
            'timeout'       => $timeout,
        ];
    }

    // Static :: ctgTest, INT -> VOID
    // Recursively validates a pipeline. Depth is the current chain
    // nesting depth; the outermost pipeline is depth 0.
    private static function validatePipeline(CTGTest $pipeline, int $depth): void {
        if ($depth > self::MAX_CHAIN_DEPTH) {
            throw new CTGTestError(
                'CHAIN_DEPTH_EXCEEDED',
                "Chain nesting depth exceeds maximum of " . self::MAX_CHAIN_DEPTH,
                ['label' => $pipeline->_label, 'depth' => $depth, 'max' => self::MAX_CHAIN_DEPTH]
            );
        }

        // Label was trimmed in the constructor; empty-after-trim is the invalid case.
        if ($pipeline->_label === '') {
            throw new CTGTestError(
                'INVALID_OPERATION',
                'Pipeline label is empty',
                ['label' => '']
            );
        }

        $seenLabels = [];

        // First pass — validate each labeled operation (stage/assert/chain)
        // and collect their labels for uniqueness checking and skip lookup.
        foreach ($pipeline->_operations as $i => $op) {
            if ($op['type'] === self::OP_SKIP) {
                continue;
            }

            $opLabel = $op['label'];
            if ($opLabel === '') {
                throw new CTGTestError(
                    'INVALID_OPERATION',
                    'Operation label is empty',
                    ['label' => '', 'operation_index' => $i]
                );
            }

            if (array_key_exists($opLabel, $seenLabels)) {
                throw new CTGTestError(
                    'INVALID_OPERATION',
                    "Duplicate operation label: {$opLabel}",
                    [
                        'label'           => $opLabel,
                        'first_index'     => $seenLabels[$opLabel],
                        'duplicate_index' => $i,
                    ]
                );
            }
            $seenLabels[$opLabel] = $i;

            if ($op['type'] === self::OP_STAGE) {
                if (!($op['fn'] instanceof \Closure)) {
                    throw new CTGTestError(
                        'INVALID_OPERATION',
                        "Stage fn is not a Closure for '{$opLabel}'",
                        ['label' => $opLabel, 'got' => gettype($op['fn'])]
                    );
                }
            } elseif ($op['type'] === self::OP_ASSERT) {
                if (!($op['fn'] instanceof \Closure)) {
                    throw new CTGTestError(
                        'INVALID_OPERATION',
                        "Assert fn is not a Closure for '{$opLabel}'",
                        ['label' => $opLabel, 'got' => gettype($op['fn'])]
                    );
                }
                $pred = $op['predicate'];
                if (!($pred instanceof CTGTestPredicate)) {
                    // A bare callable/closure counts as "callable" with a
                    // useful hint; anything else falls through to the
                    // generic got = gettype() message.
                    if ($pred instanceof \Closure || is_callable($pred)) {
                        throw new CTGTestError(
                            'INVALID_EXPECTED_OUTCOME',
                            "Assert predicate must be CTGTestPredicate for '{$opLabel}', got callable",
                            [
                                'label' => $opLabel,
                                'got'   => 'callable',
                                'hint'  => 'Use CTGTestPredicate::init() to wrap',
                            ]
                        );
                    }
                    throw new CTGTestError(
                        'INVALID_EXPECTED_OUTCOME',
                        "Assert predicate must be CTGTestPredicate for '{$opLabel}'",
                        ['label' => $opLabel, 'got' => gettype($pred)]
                    );
                }
            } elseif ($op['type'] === self::OP_CHAIN) {
                $target = $op['pipeline'];
                if (!($target instanceof CTGTest)) {
                    throw new CTGTestError(
                        'INVALID_CHAIN',
                        "Chain target must be CTGTest for '{$opLabel}'",
                        ['label' => $opLabel, 'got' => gettype($target)]
                    );
                }
                self::validatePipeline($target, $depth + 1);
            }
        }

        // Second pass — validate skip directives. Targets must exist in the
        // operation label namespace and no target may be skipped twice.
        $seenSkipTargets = [];
        foreach ($pipeline->_operations as $i => $op) {
            if ($op['type'] !== self::OP_SKIP) {
                continue;
            }
            $target = $op['targetLabel'];
            if ($target === '') {
                throw new CTGTestError(
                    'INVALID_SKIP',
                    'Skip target label is empty',
                    ['targetLabel' => '']
                );
            }
            if (!array_key_exists($target, $seenLabels)) {
                throw new CTGTestError(
                    'INVALID_SKIP',
                    "Skip target not found: {$target}",
                    ['targetLabel' => $target, 'available' => array_keys($seenLabels)]
                );
            }
            if (array_key_exists($target, $seenSkipTargets)) {
                throw new CTGTestError(
                    'INVALID_SKIP',
                    "Duplicate skip for target: {$target}",
                    ['targetLabel' => $target]
                );
            }
            $seenSkipTargets[$target] = true;

            $cond = $op['condition'];
            if ($cond !== null && !($cond instanceof \Closure)) {
                throw new CTGTestError(
                    'INVALID_SKIP',
                    "Skip condition must be Closure or null for target '{$target}'",
                    ['targetLabel' => $target, 'got' => gettype($cond)]
                );
            }
        }
    }

    // Static :: ctgTest, ctgTestState, ARRAY, [STRING] -> VOID
    // Executes a validated pipeline against the given state. labelPrefix
    // is the path of parent chain labels to prepend to this pipeline's
    // result labels (empty for the outermost pipeline). Returns true if
    // the pipeline should halt (for parent-chain propagation).
    private static function executePipeline(
        CTGTest $pipeline,
        CTGTestState $state,
        array $config,
        array $labelPrefix
    ): bool {
        // Build skip lookup map from skip directives. Validation has
        // already rejected duplicates so keys are unique.
        $skipMap = [];
        foreach ($pipeline->_operations as $op) {
            if ($op['type'] === self::OP_SKIP) {
                $skipMap[$op['targetLabel']] = $op['condition'];
            }
        }

        foreach ($pipeline->_operations as $op) {
            if ($op['type'] === self::OP_SKIP) {
                // Skip directives do not execute inline.
                continue;
            }

            $opLabel = $op['label'];
            $fullLabel = array_merge($labelPrefix, [$opLabel]);

            // Reset the computed slot before each operation.
            $state->setComputed(null);

            // Check skip map. The condition (if any) is evaluated against
            // the current state, so it sees mutations from earlier ops.
            if (array_key_exists($opLabel, $skipMap)) {
                $condition = $skipMap[$opLabel];
                try {
                    $shouldSkip = $condition === null ? true : ($condition)($state);
                } catch (\Throwable $e) {
                    // Skip condition threw — record ERROR for the target
                    // (with the target's label) and possibly halt.
                    $state->addResult(CTGTestResult::stageResult(
                        $fullLabel,
                        CTGTestStatus::ERROR,
                        $e
                    ));
                    if ($config['haltOnFailure']) {
                        return true;
                    }
                    continue;
                }

                if ($shouldSkip) {
                    $state->addResult(CTGTestResult::skippedResult($fullLabel));
                    continue;
                }
                // Condition returned false — target runs normally.
            }

            // Snapshot framework-owned slots for timeout rollback.
            $snapshotSubject  = $state->getSubject();
            $snapshotComputed = $state->getComputed();

            $halt = self::executeOperation($op, $state, $config, $labelPrefix, $fullLabel, $snapshotSubject, $snapshotComputed);
            if ($halt) {
                return true;
            }
        }

        return false;
    }

    // Static :: ARRAY, ctgTestState, ARRAY, [STRING], [STRING], MIXED, MIXED -> BOOL
    // Executes a single non-skip operation. Returns true if haltOnFailure
    // is set and the operation produced a FAIL/ERROR result (so the caller
    // can propagate the halt up the chain).
    //
    // WARNING: Timeout enforcement is POST-HOC via hrtime() in this
    // implementation (pcntl is optional/unavailable in staging). This
    // means timeout is only observed AFTER the operation's closure
    // returns control. A truly blocking or infinite operation will hang
    // the pipeline indefinitely — the configured timeout is a budget
    // observer, not a hard interruption. See spec.v2.2.md section 4.5
    // for mitigations callers should apply (low-level I/O timeouts,
    // process-level timeouts, etc.).
    //
    // Timeout precedence: if an operation throws AND exceeds the
    // budget, the framework records the timeout error and discards
    // the thrown exception. The budget contract takes precedence over
    // user exceptions.
    private static function executeOperation(
        array $op,
        CTGTestState $state,
        array $config,
        array $labelPrefix,
        array $fullLabel,
        mixed $snapshotSubject,
        mixed $snapshotComputed
    ): bool {
        $timeout = $config['timeout'];

        if ($op['type'] === self::OP_STAGE) {
            $startNs = hrtime(true);
            $timedOut = false;
            try {
                $newSubject = ($op['fn'])($state);
            } catch (\Throwable $e) {
                // Rollback framework-owned slots.
                $state->setSubject($snapshotSubject);
                $state->setComputed($snapshotComputed);

                // Timeout precedence: if the handler also exceeded the
                // budget, the timeout wins over the thrown exception.
                // The operation failed the framework's budget contract
                // first; the user exception is secondary.
                if ($timeout > 0) {
                    $elapsedMs = (hrtime(true) - $startNs) / 1_000_000;
                    if ($elapsedMs > $timeout) {
                        $state->addResult(CTGTestResult::stageResult(
                            $fullLabel,
                            CTGTestStatus::ERROR,
                            new \RuntimeException("Operation timed out after {$timeout}ms")
                        ));
                        return $config['haltOnFailure'];
                    }
                }

                $state->addResult(CTGTestResult::stageResult(
                    $fullLabel,
                    CTGTestStatus::ERROR,
                    $e
                ));
                return $config['haltOnFailure'];
            }

            if ($timeout > 0) {
                $elapsedMs = (hrtime(true) - $startNs) / 1_000_000;
                if ($elapsedMs > $timeout) {
                    $timedOut = true;
                }
            }

            if ($timedOut) {
                $state->setSubject($snapshotSubject);
                $state->setComputed($snapshotComputed);
                $state->addResult(CTGTestResult::stageResult(
                    $fullLabel,
                    CTGTestStatus::ERROR,
                    new \RuntimeException("Operation timed out after {$timeout}ms")
                ));
                return $config['haltOnFailure'];
            }

            // Apply the stage's return value as the new subject.
            $state->setSubject($newSubject);
            $state->addResult(CTGTestResult::stageResult(
                $fullLabel,
                CTGTestStatus::PASS
            ));
            return false;
        }

        if ($op['type'] === self::OP_ASSERT) {
            $predicate = $op['predicate'];
            $startNs   = hrtime(true);
            $computed  = null;

            try {
                $computed = ($op['fn'])($state);
            } catch (\Throwable $e) {
                $state->setSubject($snapshotSubject);
                $state->setComputed($snapshotComputed);

                // Timeout precedence: framework budget wins over user exception.
                if ($timeout > 0) {
                    $elapsedMs = (hrtime(true) - $startNs) / 1_000_000;
                    if ($elapsedMs > $timeout) {
                        $state->addResult(CTGTestResult::assertResult(
                            $fullLabel,
                            CTGTestStatus::ERROR,
                            null,
                            null,
                            new \RuntimeException("Operation timed out after {$timeout}ms")
                        ));
                        return $config['haltOnFailure'];
                    }
                }

                $state->addResult(CTGTestResult::assertResult(
                    $fullLabel,
                    CTGTestStatus::ERROR,
                    null,
                    null,
                    $e
                ));
                return $config['haltOnFailure'];
            }

            // Check timeout at the handler boundary — a slow handler
            // should produce a timeout error with no computed applied.
            if ($timeout > 0) {
                $elapsedMs = (hrtime(true) - $startNs) / 1_000_000;
                if ($elapsedMs > $timeout) {
                    $state->setSubject($snapshotSubject);
                    $state->setComputed($snapshotComputed);
                    $state->addResult(CTGTestResult::assertResult(
                        $fullLabel,
                        CTGTestStatus::ERROR,
                        null,
                        null,
                        new \RuntimeException("Operation timed out after {$timeout}ms")
                    ));
                    return $config['haltOnFailure'];
                }
            }

            // Handler completed in time — deposit computed into state
            // and run the predicate. If the predicate throws, we record
            // ERROR but keep computed and expectedOutcome visible.
            $state->setComputed($computed);
            $expected = $predicate->getExpectedOutcome();

            try {
                $ok = $predicate->evaluate($computed);
            } catch (\Throwable $e) {
                // Timeout precedence: if the predicate also exceeded the
                // operation's total budget, the timeout wins. Roll back
                // framework-owned slots since the timeout error conveys
                // no meaningful computed value.
                if ($timeout > 0) {
                    $elapsedMs = (hrtime(true) - $startNs) / 1_000_000;
                    if ($elapsedMs > $timeout) {
                        $state->setSubject($snapshotSubject);
                        $state->setComputed($snapshotComputed);
                        $state->addResult(CTGTestResult::assertResult(
                            $fullLabel,
                            CTGTestStatus::ERROR,
                            null,
                            null,
                            new \RuntimeException("Operation timed out after {$timeout}ms")
                        ));
                        return $config['haltOnFailure'];
                    }
                }

                $state->addResult(CTGTestResult::assertResult(
                    $fullLabel,
                    CTGTestStatus::ERROR,
                    $computed,
                    $expected,
                    $e
                ));
                return $config['haltOnFailure'];
            }

            // Re-check timeout after the predicate runs — the assert
            // operation's budget covers both the handler and the
            // predicate. A slow predicate must produce a timeout error
            // with no computed or expected applied.
            if ($timeout > 0) {
                $elapsedMs = (hrtime(true) - $startNs) / 1_000_000;
                if ($elapsedMs > $timeout) {
                    $state->setSubject($snapshotSubject);
                    $state->setComputed($snapshotComputed);
                    $state->addResult(CTGTestResult::assertResult(
                        $fullLabel,
                        CTGTestStatus::ERROR,
                        null,
                        null,
                        new \RuntimeException("Operation timed out after {$timeout}ms")
                    ));
                    return $config['haltOnFailure'];
                }
            }

            $status = $ok ? CTGTestStatus::PASS : CTGTestStatus::FAIL;
            $state->addResult(CTGTestResult::assertResult(
                $fullLabel,
                $status,
                $computed,
                $expected
            ));

            if (!$ok && $config['haltOnFailure']) {
                return true;
            }
            return false;
        }

        if ($op['type'] === self::OP_CHAIN) {
            $subPipeline = $op['pipeline'];
            // The chain contributes its label to the prefix path for all
            // results produced by the sub-pipeline. It does not produce a
            // result entry of its own.
            $newPrefix = array_merge($labelPrefix, [$op['label']]);
            $halt = self::executePipeline($subPipeline, $state, $config, $newPrefix);
            return $halt;
        }

        // Should never happen — validation guards the type tag.
        throw new CTGTestError(
            'RUNNER_ERROR',
            "Unknown operation type: {$op['type']}"
        );
    }
}
