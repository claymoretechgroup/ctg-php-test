<?php
declare(strict_types=1);

namespace CTG\Test;

use CTG\Test\Formatters\CTGTestConsoleFormatter;
use CTG\Test\Formatters\CTGTestFormatterInterface;
use CTG\Test\Formatters\CTGTestJsonFormatter;
use CTG\Test\Formatters\CTGTestJunitFormatter;

// Composable test pipeline framework — define steps, execute with a subject, get a report
class CTGTest {

    /* Constants */
    public const VALID_OUTPUT_MODES = ['console', 'return', 'return-json', 'json', 'junit'];
    public const VALID_CONFIG_KEYS = ['output', 'haltOnFailure', 'strict', 'trace', 'formatter'];

    // Fix #10: Maximum chain recursion depth to prevent infinite nesting
    private const MAX_CHAIN_DEPTH = 64;

    /* Static Properties */
    private static array $_cliConfig = [];

    /* Instance Properties */
    private string $_name;
    private array $_steps = [];
    private array $_skips = [];

    // CONSTRUCTOR :: STRING -> $this
    // Creates a new test definition with the given name
    // NOTE: Name is trimmed; empty names are rejected at validation time in start()
    private function __construct(string $name) {
        $this->_name = trim($name);
    }

    /**
     *
     * Instance Methods
     *
     */

    // :: STRING, (MIXED -> MIXED), ?((MIXED -> MIXED)) -> $this
    // Adds a stage step to the pipeline — transforms the subject
    // NOTE: No validation during definition; all validation deferred to start()
    public function stage(string $name, mixed $fn, mixed $errorHandler = null): static {
        $this->_steps[] = new CTGTestStep(CTGTestStep::TYPE_STAGE, $name, $fn, null, $errorHandler);
        return $this;
    }

    // :: STRING, (MIXED -> MIXED), MIXED, ?((MIXED -> MIXED)) -> $this
    // Adds an assert step to the pipeline — inspects the subject and compares to expected
    // NOTE: No validation during definition; all validation deferred to start()
    public function assert(string $name, mixed $fn, mixed $expected, mixed $errorHandler = null): static {
        $this->_steps[] = new CTGTestStep(CTGTestStep::TYPE_ASSERT, $name, $fn, $expected, $errorHandler);
        return $this;
    }

    // :: STRING, (MIXED -> MIXED), ARRAY, ?((MIXED -> MIXED)) -> $this
    // Adds an assert-any step to the pipeline — inspects the subject and passes if actual matches any candidate
    // NOTE: No validation during definition; all validation deferred to start()
    public function assertAny(string $name, mixed $fn, array $expected, mixed $errorHandler = null): static {
        $this->_steps[] = new CTGTestStep(CTGTestStep::TYPE_ASSERT_ANY, $name, $fn, $expected, $errorHandler);
        return $this;
    }

    // :: STRING, CTGTest -> $this
    // Adds a chain step — composes another CTGTest's steps as a named group
    // NOTE: No validation during definition; all validation deferred to start()
    public function chain(string $name, mixed $test): static {
        $this->_steps[] = new CTGTestStep(CTGTestStep::TYPE_CHAIN, $name, $test);
        return $this;
    }

    // :: STRING, ?((MIXED -> BOOL)) -> $this
    // Marks a step for conditional skipping by name
    // NOTE: Pure metadata — not a step. Validated and evaluated lazily at start() time.
    public function skip(string $name, mixed $predicate = null): static {
        $this->_skips[] = ['name' => trim($name), 'predicate' => $predicate];
        return $this;
    }

    // :: MIXED, ?ARRAY -> STRING|ARRAY|NULL
    // Executes the pipeline with a subject. Validates, runs steps, formats output.
    // NOTE: This is the only method that triggers execution. All validation happens here first.
    // Returns string for 'return', array for 'return-json', null for console/json/junit (echoed to stdout).
    public function start(mixed $subject, array $config = []): string|array|null {
        $config = $this->_resolveConfig($config);
        $this->_validate($config);

        $steps = $this->_execute($subject, $config);
        $report = CTGTestResult::report($this->_name, $steps);

        return $this->_deliver($report, $config);
    }

    // :: VOID -> STRING
    // Returns the test name
    public function getName(): string {
        return $this->_name;
    }

    // :: VOID -> ARRAY
    // Returns the step definitions (for chain execution)
    public function getSteps(): array {
        return $this->_steps;
    }

    // :: VOID -> ARRAY
    // Returns the skip directives (for chain execution)
    public function getSkips(): array {
        return $this->_skips;
    }

    /**
     *
     * Validation (private)
     *
     */

    // :: ARRAY -> ARRAY
    // Merges user config with defaults, validates keys and values
    private function _resolveConfig(array $config): array {
        $defaults = [
            'output' => 'console',
            'haltOnFailure' => true,
            'strict' => true,
            'trace' => false,
            'formatter' => null,
        ];

        foreach ($config as $key => $value) {
            if (!in_array($key, self::VALID_CONFIG_KEYS, true)) {
                throw new CTGTestError('INVALID_CONFIG', "Unrecognized config key: {$key}", [
                    'key' => $key,
                    'value' => $value,
                    'valid_keys' => self::VALID_CONFIG_KEYS,
                ]);
            }
        }

        $merged = array_merge($defaults, $config);

        if (!in_array($merged['output'], self::VALID_OUTPUT_MODES, true)) {
            throw new CTGTestError('INVALID_CONFIG', "Invalid output mode: {$merged['output']}", [
                'key' => 'output',
                'value' => $merged['output'],
                'valid_values' => self::VALID_OUTPUT_MODES,
            ]);
        }

        if (!is_bool($merged['haltOnFailure'])) {
            throw new CTGTestError('INVALID_CONFIG', "haltOnFailure must be bool", [
                'key' => 'haltOnFailure', 'value' => $merged['haltOnFailure'],
            ]);
        }

        if (!is_bool($merged['strict'])) {
            throw new CTGTestError('INVALID_CONFIG', "strict must be bool", [
                'key' => 'strict', 'value' => $merged['strict'],
            ]);
        }

        if (!is_bool($merged['trace'])) {
            throw new CTGTestError('INVALID_CONFIG', "trace must be bool", [
                'key' => 'trace', 'value' => $merged['trace'],
            ]);
        }

        $formatter = $merged['formatter'];
        if ($formatter !== null) {
            if (!is_string($formatter)) {
                throw new CTGTestError('INVALID_CONFIG', "formatter must be a class-string or null", [
                    'key' => 'formatter', 'value' => $formatter,
                ]);
            }
            if (!class_exists($formatter)) {
                throw new CTGTestError('INVALID_CONFIG', "formatter class does not exist: {$formatter}", [
                    'key' => 'formatter', 'value' => $formatter,
                ]);
            }
            if (!in_array(CTGTestFormatterInterface::class, class_implements($formatter) ?: [], true)) {
                throw new CTGTestError('INVALID_CONFIG', "formatter class must implement CTGTestFormatterInterface: {$formatter}", [
                    'key' => 'formatter',
                    'value' => $formatter,
                    'required_interface' => CTGTestFormatterInterface::class,
                ]);
            }
        }

        return $merged;
    }

    // :: ARRAY -> VOID
    // Validates the entire pipeline before execution
    // NOTE: All definition-time errors (1xxx) are caught here
    private function _validate(array $config): void {
        if (empty($this->_name)) {
            throw new CTGTestError('INVALID_STEP', "Test name is empty after trimming", [
                'name' => $this->_name,
            ]);
        }

        $this->_validateSteps($this->_steps, 0);
        $this->_validateSkips($this->_skips, $this->_steps);
    }

    // :: ARRAY, INT -> VOID
    // Validates step definitions: callables, names, uniqueness
    // Fix #10: depth parameter tracks chain nesting to enforce MAX_CHAIN_DEPTH
    private function _validateSteps(array $steps, int $chainDepth): void {
        $names = [];

        foreach ($steps as $index => $step) {
            $name = $step->getName();

            if (empty($name)) {
                throw new CTGTestError('INVALID_STEP', "Step name is empty after trimming", [
                    'step_index' => $index,
                ]);
            }

            if (isset($names[$name])) {
                throw new CTGTestError('INVALID_STEP', "Duplicate step name: '{$name}'", [
                    'name' => $name,
                    'first_index' => $names[$name],
                    'duplicate_index' => $index,
                ]);
            }
            $names[$name] = $index;

            $type = $step->getType();
            $fn = $step->getFn();

            if ($type === CTGTestStep::TYPE_CHAIN) {
                if (!($fn instanceof CTGTest)) {
                    throw new CTGTestError('INVALID_CHAIN', "Chain '{$name}' requires a CTGTest instance", [
                        'chain_name' => $name, 'got' => gettype($fn),
                    ]);
                }
                // Fix #10: Enforce chain recursion depth limit
                if ($chainDepth >= self::MAX_CHAIN_DEPTH) {
                    throw new CTGTestError('INVALID_CHAIN', "Chain depth exceeds maximum of " . self::MAX_CHAIN_DEPTH, [
                        'chain_name' => $name,
                        'depth' => $chainDepth,
                        'max_depth' => self::MAX_CHAIN_DEPTH,
                    ]);
                }
                $this->_validateSteps($fn->getSteps(), $chainDepth + 1);
                $this->_validateSkips($fn->getSkips(), $fn->getSteps());
            } else {
                if (!is_callable($fn)) {
                    throw new CTGTestError('INVALID_STEP', "Step '{$name}' function is not callable", [
                        'step_index' => $index, 'name' => $name, 'got' => gettype($fn),
                    ]);
                }

                $errorHandler = $step->getErrorHandler();
                if ($errorHandler !== null && !is_callable($errorHandler)) {
                    throw new CTGTestError('INVALID_STEP', "Step '{$name}' error handler is not callable", [
                        'step_index' => $index, 'name' => $name, 'got' => gettype($errorHandler),
                    ]);
                }
            }

            if ($type === CTGTestStep::TYPE_ASSERT) {
                $expected = $step->getExpected();
                // Fix #6: Use is_callable() instead of instanceof \Closure to catch all callables
                if (is_callable($expected)) {
                    throw new CTGTestError('INVALID_EXPECTED', "Assert '{$name}' expected value is callable — predicates go in the fn argument", [
                        'step_name' => $name,
                    ]);
                }
            }

            if ($type === CTGTestStep::TYPE_ASSERT_ANY) {
                $expected = $step->getExpected();
                if (!is_array($expected)) {
                    throw new CTGTestError('INVALID_EXPECTED', "AssertAny '{$name}' expected value must be an array of candidates", [
                        'step_name' => $name,
                        'got' => gettype($expected),
                    ]);
                }
            }
        }
    }

    // :: ARRAY, ARRAY -> VOID
    // Validates skip directives against the step name set
    private function _validateSkips(array $skips, array $steps): void {
        $stepNames = [];
        foreach ($steps as $step) {
            $stepNames[$step->getName()] = true;
        }

        $seenSkips = [];
        foreach ($skips as $skip) {
            $name = $skip['name'];

            if (empty($name)) {
                throw new CTGTestError('INVALID_SKIP', "Skip name is empty after trimming", ['skip_name' => $name]);
            }

            if (!isset($stepNames[$name])) {
                throw new CTGTestError('INVALID_SKIP', "Skip target '{$name}' does not match any step", [
                    'skip_name' => $name, 'available_steps' => array_keys($stepNames),
                ]);
            }

            if (isset($seenSkips[$name])) {
                throw new CTGTestError('INVALID_SKIP', "Duplicate skip directive for '{$name}'", ['skip_name' => $name]);
            }
            $seenSkips[$name] = true;

            $predicate = $skip['predicate'];
            if ($predicate !== null && !is_callable($predicate)) {
                throw new CTGTestError('INVALID_SKIP', "Skip predicate for '{$name}' is not callable", [
                    'skip_name' => $name, 'got' => gettype($predicate),
                ]);
            }
        }
    }

    /**
     *
     * Execution (private)
     *
     */

    // :: MIXED, ARRAY -> ARRAY
    // Executes pipeline steps in order, threading the subject through
    private function _execute(mixed $subject, array $config): array {
        return $this->_executeSteps($this->_steps, $this->_skips, $subject, $config);
    }

    // :: ARRAY, ARRAY, MIXED, ARRAY -> ARRAY
    // Core execution loop — runs steps, handles skip/halt, returns step results
    private function _executeSteps(array $steps, array $skips, mixed &$subject, array $config): array {
        $skipMap = [];
        foreach ($skips as $skip) {
            $skipMap[$skip['name']] = $skip['predicate'];
        }

        $results = [];

        foreach ($steps as $step) {
            $name = $step->getName();
            $type = $step->getType();

            // Check skip set
            if (array_key_exists($name, $skipMap)) {
                $predicate = $skipMap[$name];

                if ($predicate === null) {
                    $results[] = $this->_makeSkipResult($type, $name);
                    continue;
                }

                try {
                    if ($predicate($subject)) {
                        $results[] = $this->_makeSkipResult($type, $name);
                        continue;
                    }
                } catch (\Throwable $e) {
                    $result = CTGTestResult::stepResult(
                        $type, $name, CTGTestResult::STATUS_ERROR, 0,
                        get_class($e) . ': ' . $e->getMessage(),
                        CTGTestResult::formatException($e, $config['trace'])
                    );
                    $results[] = $result;
                    if ($config['haltOnFailure']) { break; }
                    continue;
                }
            }

            // Execute the step
            $result = match ($type) {
                CTGTestStep::TYPE_STAGE => $this->_executeStage($step, $subject, $config),
                CTGTestStep::TYPE_ASSERT => $this->_executeAssert($step, $subject, $config),
                CTGTestStep::TYPE_ASSERT_ANY => $this->_executeAssertAny($step, $subject, $config),
                CTGTestStep::TYPE_CHAIN => $this->_executeChain($step, $subject, $config),
                // Fix #11: Default arm for unknown step types
                default => CTGTestResult::stepResult($type, $name, CTGTestResult::STATUS_ERROR, 0,
                    "Unknown step type: {$type}"),
            };

            $results[] = $result;

            // Check haltOnFailure
            if ($config['haltOnFailure'] && ($result['status'] === CTGTestResult::STATUS_FAIL || $result['status'] === CTGTestResult::STATUS_ERROR)) {
                break;
            }
        }

        return $results;
    }

    // :: CTGTestStep, MIXED, ARRAY -> ARRAY
    // Executes a stage step: call fn with subject, handle errors, return result
    private function _executeStage(CTGTestStep $step, mixed &$subject, array $config): array {
        $name = $step->getName();
        $fn = $step->getFn();
        $errorHandler = $step->getErrorHandler();
        $startTime = hrtime(true);

        try {
            $newSubject = $fn($subject);
            $durationMs = $this->_elapsed($startTime);
            $subject = $newSubject;
            return CTGTestResult::stepResult('stage', $name, CTGTestResult::STATUS_PASS, $durationMs);
        } catch (\Throwable $e) {
            if ($errorHandler !== null) {
                try {
                    $recoveredSubject = $errorHandler($e);
                    $durationMs = $this->_elapsed($startTime);
                    $subject = $recoveredSubject;
                    return CTGTestResult::stepResult('stage', $name, CTGTestResult::STATUS_RECOVERED, $durationMs,
                        'error handler invoked, produced ' . CTGTestResult::formatValue($recoveredSubject),
                        CTGTestResult::formatException($e, $config['trace'])
                    );
                } catch (\Throwable $handlerError) {
                    $durationMs = $this->_elapsed($startTime);
                    return CTGTestResult::stepResult('stage', $name, CTGTestResult::STATUS_ERROR, $durationMs,
                        get_class($handlerError) . ': ' . $handlerError->getMessage(),
                        CTGTestResult::formatException($handlerError, $config['trace'], CTGTestResult::formatException($e, $config['trace']))
                    );
                }
            }
            $durationMs = $this->_elapsed($startTime);
            return CTGTestResult::stepResult('stage', $name, CTGTestResult::STATUS_ERROR, $durationMs,
                get_class($e) . ': ' . $e->getMessage(),
                CTGTestResult::formatException($e, $config['trace'])
            );
        }
    }

    // :: CTGTestStep, MIXED, ARRAY -> ARRAY
    // Executes an assert step: call fn, compare result to expected, handle errors
    private function _executeAssert(CTGTestStep $step, mixed $subject, array $config): array {
        $name = $step->getName();
        $fn = $step->getFn();
        $expected = $step->getExpected();
        $errorHandler = $step->getErrorHandler();
        $startTime = hrtime(true);

        try {
            $actual = $fn($subject);
            $durationMs = $this->_elapsed($startTime);

            $typeError = $this->_checkComparable($actual, $expected);
            if ($typeError !== null) {
                return CTGTestResult::assertResult($name, CTGTestResult::STATUS_ERROR, $durationMs, $actual, $expected, $typeError);
            }

            if ($this->_compareExpected($actual, $expected, $config['strict'])) {
                return CTGTestResult::assertResult($name, CTGTestResult::STATUS_PASS, $durationMs, $actual, $expected);
            }

            return CTGTestResult::assertResult($name, CTGTestResult::STATUS_FAIL, $durationMs, $actual, $expected,
                'expected ' . CTGTestResult::formatValue($expected) . ' but got ' . CTGTestResult::formatValue($actual)
            );
        } catch (\Throwable $e) {
            if ($errorHandler !== null) {
                try {
                    $recoveredValue = $errorHandler($e);
                    $durationMs = $this->_elapsed($startTime);
                    return CTGTestResult::assertResult($name, CTGTestResult::STATUS_RECOVERED, $durationMs, $recoveredValue, $expected,
                        'error handler invoked, produced ' . CTGTestResult::formatValue($recoveredValue),
                        CTGTestResult::formatException($e, $config['trace'])
                    );
                } catch (\Throwable $handlerError) {
                    $durationMs = $this->_elapsed($startTime);
                    return CTGTestResult::assertResult($name, CTGTestResult::STATUS_ERROR, $durationMs, null, $expected,
                        get_class($handlerError) . ': ' . $handlerError->getMessage(),
                        CTGTestResult::formatException($handlerError, $config['trace'], CTGTestResult::formatException($e, $config['trace']))
                    );
                }
            }
            $durationMs = $this->_elapsed($startTime);
            return CTGTestResult::assertResult($name, CTGTestResult::STATUS_ERROR, $durationMs, null, $expected,
                get_class($e) . ': ' . $e->getMessage(),
                CTGTestResult::formatException($e, $config['trace'])
            );
        }
    }

    // :: CTGTestStep, MIXED, ARRAY -> ARRAY
    // Executes an assert-any step: call fn, compare result against each candidate, pass if any match
    private function _executeAssertAny(CTGTestStep $step, mixed $subject, array $config): array {
        $name = $step->getName();
        $fn = $step->getFn();
        $candidates = $step->getExpected();
        $errorHandler = $step->getErrorHandler();
        $startTime = hrtime(true);

        try {
            $actual = $fn($subject);
            $durationMs = $this->_elapsed($startTime);

            // Check comparability of actual value
            $visited = [];
            $typeError = $this->_checkValueComparable($actual, $visited, 0);
            if ($typeError !== null) {
                return CTGTestResult::assertAnyResult($name, CTGTestResult::STATUS_ERROR, $durationMs, $actual, $candidates,
                    "actual value contains {$typeError}");
            }

            // Check comparability of each candidate
            foreach ($candidates as $i => $candidate) {
                $visited = [];
                $typeError = $this->_checkValueComparable($candidate, $visited, 0);
                if ($typeError !== null) {
                    return CTGTestResult::assertAnyResult($name, CTGTestResult::STATUS_ERROR, $durationMs, $actual, $candidates,
                        "candidate [{$i}] contains {$typeError}");
                }
            }

            // Try each candidate
            foreach ($candidates as $candidate) {
                if ($this->compare($actual, $candidate, $config['strict'])) {
                    return CTGTestResult::assertAnyResult($name, CTGTestResult::STATUS_PASS, $durationMs, $actual, $candidates);
                }
            }

            return CTGTestResult::assertAnyResult($name, CTGTestResult::STATUS_FAIL, $durationMs, $actual, $candidates,
                'expected any of ' . CTGTestResult::formatValue($candidates) . ' but got ' . CTGTestResult::formatValue($actual)
            );
        } catch (\Throwable $e) {
            if ($errorHandler !== null) {
                try {
                    $recoveredValue = $errorHandler($e);
                    $durationMs = $this->_elapsed($startTime);
                    return CTGTestResult::assertAnyResult($name, CTGTestResult::STATUS_RECOVERED, $durationMs, $recoveredValue, $candidates,
                        'error handler invoked, produced ' . CTGTestResult::formatValue($recoveredValue),
                        CTGTestResult::formatException($e, $config['trace'])
                    );
                } catch (\Throwable $handlerError) {
                    $durationMs = $this->_elapsed($startTime);
                    return CTGTestResult::assertAnyResult($name, CTGTestResult::STATUS_ERROR, $durationMs, null, $candidates,
                        get_class($handlerError) . ': ' . $handlerError->getMessage(),
                        CTGTestResult::formatException($handlerError, $config['trace'], CTGTestResult::formatException($e, $config['trace']))
                    );
                }
            }
            $durationMs = $this->_elapsed($startTime);
            return CTGTestResult::assertAnyResult($name, CTGTestResult::STATUS_ERROR, $durationMs, null, $candidates,
                get_class($e) . ': ' . $e->getMessage(),
                CTGTestResult::formatException($e, $config['trace'])
            );
        }
    }

    // :: CTGTestStep, MIXED, ARRAY -> ARRAY
    // Executes a chain step: run the chained CTGTest's steps inline
    private function _executeChain(CTGTestStep $step, mixed &$subject, array $config): array {
        $name = $step->getName();
        $chainedTest = $step->getFn();
        $startTime = hrtime(true);

        $childSteps = $this->_executeSteps($chainedTest->getSteps(), $chainedTest->getSkips(), $subject, $config);

        $durationMs = $this->_elapsed($startTime);
        $counts = CTGTestResult::countSteps($childSteps);
        $status = CTGTestResult::aggregateStatus($childSteps);
        $message = CTGTestResult::chainMessage($counts['failed'], $counts['errored'], $counts['total']);

        return CTGTestResult::chainResult($name, $status, $durationMs, $message, null, $childSteps, $counts);
    }

    /**
     *
     * Comparison (protected)
     *
     */

    // :: MIXED, MIXED, BOOL -> BOOL
    // Single comparison method — all comparison logic lives here
    // NOTE: Future matcher support replaces this one method
    protected function compare(mixed $actual, mixed $expected, bool $strict): bool {
        return $strict ? $actual === $expected : $actual == $expected;
    }

    // :: MIXED, MIXED, BOOL -> BOOL
    // Compares actual against expected — always direct comparison
    private function _compareExpected(mixed $actual, mixed $expected, bool $strict): bool {
        return $this->compare($actual, $expected, $strict);
    }

    // :: MIXED, MIXED -> ?STRING
    // Checks if either side contains uncomparable types (resources, closures, cycles)
    private function _checkComparable(mixed $actual, mixed $expected): ?string {
        $visited = [];
        $error = $this->_checkValueComparable($actual, $visited, 0);
        if ($error !== null) { return "actual value contains {$error}"; }

        $visited = [];
        $error = $this->_checkValueComparable($expected, $visited, 0);
        if ($error !== null) { return "expected value contains {$error}"; }

        return null;
    }

    // :: MIXED, ARRAY, INT -> ?STRING
    // Recursively checks a value for resources, closures, and cycles
    // Fix #4: Added array cycle detection using depth parameter passed through recursion
    private function _checkValueComparable(mixed $value, array &$visited, int $arrayDepth): ?string {
        if (is_resource($value)) { return 'a resource'; }
        if ($value instanceof \Closure) { return 'a closure'; }

        if (is_array($value)) {
            // Fix #4: Track array nesting depth as a pragmatic proxy for cycle detection
            // since PHP arrays can only form cycles via references, and deep nesting
            // is the observable symptom
            $arrayDepth++;
            if ($arrayDepth > 128) {
                return 'a deeply nested or cyclic array';
            }
            foreach ($value as $item) {
                $error = $this->_checkValueComparable($item, $visited, $arrayDepth);
                if ($error !== null) {
                    return $error;
                }
            }
            return null;
        }

        if (is_object($value)) {
            $id = spl_object_id($value);
            if (isset($visited[$id])) { return 'a cyclic reference'; }
            $visited[$id] = true;

            $reflection = new \ReflectionObject($value);
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                $error = $this->_checkValueComparable($prop->getValue($value), $visited, $arrayDepth);
                if ($error !== null) { return $error; }
            }
            return null;
        }

        return null;
    }

    /**
     *
     * Output delivery (private)
     *
     */

    // :: ARRAY, ARRAY -> STRING|ARRAY|NULL
    // Delivers the report via the configured output mode or custom formatter
    private function _deliver(array $report, array $config): string|array|null {
        $output = $config['output'];
        $formatter = $config['formatter'];

        try {
            // Custom formatter overrides output mode dispatch
            if ($formatter !== null) {
                return $this->_deliverCustom($report, $formatter, $config);
            }

            return match ($output) {
                'console' => $this->_deliverConsole($report),
                'return' => CTGTestConsoleFormatter::format($report),
                'return-json' => $report,
                // Fix #9: Use CTGTestJsonFormatter instead of inline json_encode
                'json' => $this->_deliverJson($report),
                'junit' => $this->_deliverJunit($report, $config),
                // Fix #11: Default arm for unknown output modes (should not reach here due to validation)
                default => throw new CTGTestError('INVALID_CONFIG', "Unknown output mode: {$output}"),
            };
        } catch (CTGTestError $e) {
            throw $e;
        } catch (\Throwable $e) {
            $formatterLabel = $formatter ?? $output;
            throw new CTGTestError('FORMATTER_ERROR', "Formatter '{$formatterLabel}' threw an exception", [
                'formatter' => $formatterLabel,
                'exception' => CTGTestResult::formatException($e, $config['trace']),
                'report' => $report,
            ]);
        }
    }

    // :: ARRAY -> NULL
    private function _deliverConsole(array $report): null {
        echo CTGTestConsoleFormatter::format($report);
        return null;
    }

    // :: ARRAY -> NULL
    // Fix #9: Now uses CTGTestJsonFormatter::format() instead of inline json_encode
    private function _deliverJson(array $report): null {
        echo CTGTestJsonFormatter::format($report);
        return null;
    }

    // :: ARRAY, ARRAY -> NULL
    private function _deliverJunit(array $report, array $config): null {
        echo CTGTestJunitFormatter::format($report, $config['trace']);
        return null;
    }

    // :: ARRAY, STRING, ARRAY -> STRING|NULL
    // Delivers via a custom formatter class — output mode determines return vs echo behavior
    private function _deliverCustom(array $report, string $formatter, array $config): string|null {
        $formatted = $formatter::format($report);
        $output = $config['output'];

        // 'return' and 'return-json' return the formatted string; all others echo to stdout
        if ($output === 'return' || $output === 'return-json') {
            return $formatted;
        }

        echo $formatted;
        return null;
    }

    /**
     *
     * Helpers (private)
     *
     */

    // :: INT -> INT
    // Calculates elapsed milliseconds from a hrtime start, truncated
    private function _elapsed(int $startNano): int {
        return (int) ((hrtime(true) - $startNano) / 1_000_000);
    }

    // :: STRING, STRING -> ARRAY
    // Creates a skip result for a step
    private function _makeSkipResult(string $type, string $name): array {
        return match ($type) {
            CTGTestStep::TYPE_ASSERT => CTGTestResult::assertResult($name, CTGTestResult::STATUS_SKIP, 0, null, null),
            CTGTestStep::TYPE_ASSERT_ANY => CTGTestResult::assertAnyResult($name, CTGTestResult::STATUS_SKIP, 0, null, []),
            CTGTestStep::TYPE_CHAIN => CTGTestResult::chainResult($name, CTGTestResult::STATUS_SKIP, 0, null, null, [], [
                'passed' => 0, 'failed' => 0, 'skipped' => 0, 'recovered' => 0, 'errored' => 0, 'total' => 0,
            ]),
            default => CTGTestResult::stepResult($type, $name, CTGTestResult::STATUS_SKIP, 0),
        };
    }

    /**
     *
     * Static Methods
     *
     */

    // Static Factory Method :: STRING -> static
    // Creates a new test definition with the given name
    // NOTE: Name is stored raw; validation deferred to start()
    // Fix #3: Returns static and uses new static() for subclass support
    public static function init(string $name): static {
        return new static($name);
    }

    // :: ARRAY -> VOID
    // Stores CLI config for retrieval by test files. Called by the CLI runner.
    // Replaces $GLOBALS['CTG_TEST_CONFIG'] with a typed static interface.
    public static function setCliConfig(array $config): void {
        self::$_cliConfig = $config;
    }

    // :: VOID -> ARRAY
    // Returns CLI config set by the runner. Returns empty array if none was set.
    // Test files use this instead of reading $GLOBALS.
    public static function getCliConfig(): array {
        return self::$_cliConfig;
    }
}
