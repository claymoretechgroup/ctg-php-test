<?php

require_once __DIR__ . '/TestError.php';   // Framework exception class
require_once __DIR__ . '/TestStep.php';    // Step definition value object
require_once __DIR__ . '/TestResult.php';  // Result data structure and helpers

require_once __DIR__ . '/Formatters/ConsoleFormatter.php'; // Human-readable output
require_once __DIR__ . '/Formatters/JsonFormatter.php';    // JSON output
require_once __DIR__ . '/Formatters/JunitFormatter.php';   // JUnit XML output

// Composable test pipeline framework — define steps, execute with a subject, get a report
class CTGTest {

    /* Constants */
    public const VALID_OUTPUT_MODES = ['console', 'return', 'return-json', 'json', 'junit'];
    public const VALID_CONFIG_KEYS = ['output', 'haltOnFailure', 'strict', 'trace'];

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
    public function stage(string $name, mixed $fn, mixed $errorHandler = null): self {
        $this->_steps[] = new TestStep(TestStep::TYPE_STAGE, $name, $fn, null, $errorHandler);
        return $this;
    }

    // :: STRING, (MIXED -> MIXED), MIXED, ?((MIXED -> MIXED)) -> $this
    // Adds an assert step to the pipeline — inspects the subject and compares to expected
    // NOTE: No validation during definition; all validation deferred to start()
    public function assert(string $name, mixed $fn, mixed $expected, mixed $errorHandler = null): self {
        $this->_steps[] = new TestStep(TestStep::TYPE_ASSERT, $name, $fn, $expected, $errorHandler);
        return $this;
    }

    // :: STRING, CTGTest -> $this
    // Adds a chain step — composes another CTGTest's steps as a named group
    // NOTE: No validation during definition; all validation deferred to start()
    public function chain(string $name, mixed $test): self {
        $this->_steps[] = new TestStep(TestStep::TYPE_CHAIN, $name, $test);
        return $this;
    }

    // :: STRING, ?((MIXED -> BOOL)) -> $this
    // Marks a step for conditional skipping by name
    // NOTE: Pure metadata — not a step. Validated and evaluated lazily at start() time.
    public function skip(string $name, mixed $predicate = null): self {
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
        $report = TestResult::report($this->_name, $steps);

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
        ];

        foreach ($config as $key => $value) {
            if (!in_array($key, self::VALID_CONFIG_KEYS, true)) {
                throw new TestError('INVALID_CONFIG', "Unrecognized config key: {$key}", [
                    'key' => $key,
                    'value' => $value,
                    'valid_keys' => self::VALID_CONFIG_KEYS,
                ]);
            }
        }

        $merged = array_merge($defaults, $config);

        if (!in_array($merged['output'], self::VALID_OUTPUT_MODES, true)) {
            throw new TestError('INVALID_CONFIG', "Invalid output mode: {$merged['output']}", [
                'key' => 'output',
                'value' => $merged['output'],
                'valid_values' => self::VALID_OUTPUT_MODES,
            ]);
        }

        if (!is_bool($merged['haltOnFailure'])) {
            throw new TestError('INVALID_CONFIG', "haltOnFailure must be bool", [
                'key' => 'haltOnFailure', 'value' => $merged['haltOnFailure'],
            ]);
        }

        if (!is_bool($merged['strict'])) {
            throw new TestError('INVALID_CONFIG', "strict must be bool", [
                'key' => 'strict', 'value' => $merged['strict'],
            ]);
        }

        if (!is_bool($merged['trace'])) {
            throw new TestError('INVALID_CONFIG', "trace must be bool", [
                'key' => 'trace', 'value' => $merged['trace'],
            ]);
        }

        return $merged;
    }

    // :: ARRAY -> VOID
    // Validates the entire pipeline before execution
    // NOTE: All definition-time errors (1xxx) are caught here
    private function _validate(array $config): void {
        if (empty($this->_name)) {
            throw new TestError('INVALID_STEP', "Test name is empty after trimming", [
                'name' => $this->_name,
            ]);
        }

        $this->_validateSteps($this->_steps);
        $this->_validateSkips($this->_skips, $this->_steps);
    }

    // :: ARRAY -> VOID
    // Validates step definitions: callables, names, uniqueness
    private function _validateSteps(array $steps): void {
        $names = [];

        foreach ($steps as $index => $step) {
            $name = $step->getName();

            if (empty($name)) {
                throw new TestError('INVALID_STEP', "Step name is empty after trimming", [
                    'step_index' => $index,
                ]);
            }

            if (isset($names[$name])) {
                throw new TestError('INVALID_STEP', "Duplicate step name: '{$name}'", [
                    'name' => $name,
                    'first_index' => $names[$name],
                    'duplicate_index' => $index,
                ]);
            }
            $names[$name] = $index;

            $type = $step->getType();
            $fn = $step->getFn();

            if ($type === TestStep::TYPE_CHAIN) {
                if (!($fn instanceof CTGTest)) {
                    throw new TestError('INVALID_CHAIN', "Chain '{$name}' requires a CTGTest instance", [
                        'chain_name' => $name, 'got' => gettype($fn),
                    ]);
                }
                $this->_validateSteps($fn->getSteps());
                $this->_validateSkips($fn->getSkips(), $fn->getSteps());
            } else {
                if (!is_callable($fn)) {
                    throw new TestError('INVALID_STEP', "Step '{$name}' function is not callable", [
                        'step_index' => $index, 'name' => $name, 'got' => gettype($fn),
                    ]);
                }

                $errorHandler = $step->getErrorHandler();
                if ($errorHandler !== null && !is_callable($errorHandler)) {
                    throw new TestError('INVALID_STEP', "Step '{$name}' error handler is not callable", [
                        'step_index' => $index, 'name' => $name, 'got' => gettype($errorHandler),
                    ]);
                }
            }

            if ($type === TestStep::TYPE_ASSERT) {
                $expected = $step->getExpected();
                if ($expected instanceof \Closure) {
                    throw new TestError('INVALID_EXPECTED', "Assert '{$name}' expected value is callable — predicates go in the fn argument", [
                        'step_name' => $name,
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
                throw new TestError('INVALID_SKIP', "Skip name is empty after trimming", ['skip_name' => $name]);
            }

            if (!isset($stepNames[$name])) {
                throw new TestError('INVALID_SKIP', "Skip target '{$name}' does not match any step", [
                    'skip_name' => $name, 'available_steps' => array_keys($stepNames),
                ]);
            }

            if (isset($seenSkips[$name])) {
                throw new TestError('INVALID_SKIP', "Duplicate skip directive for '{$name}'", ['skip_name' => $name]);
            }
            $seenSkips[$name] = true;

            $predicate = $skip['predicate'];
            if ($predicate !== null && !is_callable($predicate)) {
                throw new TestError('INVALID_SKIP', "Skip predicate for '{$name}' is not callable", [
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
                    $result = TestResult::stepResult(
                        $type, $name, TestResult::STATUS_ERROR, 0,
                        get_class($e) . ': ' . $e->getMessage(),
                        TestResult::formatException($e, $config['trace'])
                    );
                    $results[] = $result;
                    if ($config['haltOnFailure']) { break; }
                    continue;
                }
            }

            // Execute the step
            $result = match ($type) {
                TestStep::TYPE_STAGE => $this->_executeStage($step, $subject, $config),
                TestStep::TYPE_ASSERT => $this->_executeAssert($step, $subject, $config),
                TestStep::TYPE_CHAIN => $this->_executeChain($step, $subject, $config),
            };

            $results[] = $result;

            // Check haltOnFailure
            if ($config['haltOnFailure'] && ($result['status'] === TestResult::STATUS_FAIL || $result['status'] === TestResult::STATUS_ERROR)) {
                break;
            }
        }

        return $results;
    }

    // :: TestStep, MIXED, ARRAY -> ARRAY
    // Executes a stage step: call fn with subject, handle errors, return result
    private function _executeStage(TestStep $step, mixed &$subject, array $config): array {
        $name = $step->getName();
        $fn = $step->getFn();
        $errorHandler = $step->getErrorHandler();
        $startTime = hrtime(true);

        try {
            $newSubject = $fn($subject);
            $durationMs = $this->_elapsed($startTime);
            $subject = $newSubject;
            return TestResult::stepResult('stage', $name, TestResult::STATUS_PASS, $durationMs);
        } catch (\Throwable $e) {
            if ($errorHandler !== null) {
                try {
                    $recoveredSubject = $errorHandler($e);
                    $durationMs = $this->_elapsed($startTime);
                    $subject = $recoveredSubject;
                    return TestResult::stepResult('stage', $name, TestResult::STATUS_RECOVERED, $durationMs,
                        'error handler invoked, produced ' . TestResult::formatValue($recoveredSubject),
                        TestResult::formatException($e, $config['trace'])
                    );
                } catch (\Throwable $handlerError) {
                    $durationMs = $this->_elapsed($startTime);
                    return TestResult::stepResult('stage', $name, TestResult::STATUS_ERROR, $durationMs,
                        get_class($handlerError) . ': ' . $handlerError->getMessage(),
                        TestResult::formatException($handlerError, $config['trace'], TestResult::formatException($e, $config['trace']))
                    );
                }
            }
            $durationMs = $this->_elapsed($startTime);
            return TestResult::stepResult('stage', $name, TestResult::STATUS_ERROR, $durationMs,
                get_class($e) . ': ' . $e->getMessage(),
                TestResult::formatException($e, $config['trace'])
            );
        }
    }

    // :: TestStep, MIXED, ARRAY -> ARRAY
    // Executes an assert step: call fn, compare result to expected, handle errors
    private function _executeAssert(TestStep $step, mixed $subject, array $config): array {
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
                return TestResult::assertResult($name, TestResult::STATUS_ERROR, $durationMs, $actual, $expected, $typeError);
            }

            if ($this->_compareExpected($actual, $expected, $config['strict'])) {
                return TestResult::assertResult($name, TestResult::STATUS_PASS, $durationMs, $actual, $expected);
            }

            return TestResult::assertResult($name, TestResult::STATUS_FAIL, $durationMs, $actual, $expected,
                'expected ' . TestResult::formatValue($expected) . ' but got ' . TestResult::formatValue($actual)
            );
        } catch (\Throwable $e) {
            if ($errorHandler !== null) {
                try {
                    $recoveredValue = $errorHandler($e);
                    $durationMs = $this->_elapsed($startTime);
                    return TestResult::assertResult($name, TestResult::STATUS_RECOVERED, $durationMs, $recoveredValue, $expected,
                        'error handler invoked, produced ' . TestResult::formatValue($recoveredValue),
                        TestResult::formatException($e, $config['trace'])
                    );
                } catch (\Throwable $handlerError) {
                    $durationMs = $this->_elapsed($startTime);
                    return TestResult::assertResult($name, TestResult::STATUS_ERROR, $durationMs, null, $expected,
                        get_class($handlerError) . ': ' . $handlerError->getMessage(),
                        TestResult::formatException($handlerError, $config['trace'], TestResult::formatException($e, $config['trace']))
                    );
                }
            }
            $durationMs = $this->_elapsed($startTime);
            return TestResult::assertResult($name, TestResult::STATUS_ERROR, $durationMs, null, $expected,
                get_class($e) . ': ' . $e->getMessage(),
                TestResult::formatException($e, $config['trace'])
            );
        }
    }

    // :: TestStep, MIXED, ARRAY -> ARRAY
    // Executes a chain step: run the chained CTGTest's steps inline
    private function _executeChain(TestStep $step, mixed &$subject, array $config): array {
        $name = $step->getName();
        $chainedTest = $step->getFn();
        $startTime = hrtime(true);

        $childSteps = $this->_executeSteps($chainedTest->getSteps(), $chainedTest->getSkips(), $subject, $config);

        $durationMs = $this->_elapsed($startTime);
        $counts = TestResult::countSteps($childSteps);
        $status = TestResult::aggregateStatus($childSteps);
        $message = TestResult::chainMessage($counts['failed'], $counts['errored'], $counts['total']);

        return TestResult::chainResult($name, $status, $durationMs, $message, null, $childSteps, $counts);
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
    // Compares actual against expected, handling candidate-set arrays
    private function _compareExpected(mixed $actual, mixed $expected, bool $strict): bool {
        if (is_array($expected)) {
            foreach ($expected as $candidate) {
                if ($this->compare($actual, $candidate, $strict)) {
                    return true;
                }
            }
            return false;
        }
        return $this->compare($actual, $expected, $strict);
    }

    // :: MIXED, MIXED -> ?STRING
    // Checks if either side contains uncomparable types (resources, closures, cycles)
    private function _checkComparable(mixed $actual, mixed $expected): ?string {
        $visited = [];
        $error = $this->_checkValueComparable($actual, $visited);
        if ($error !== null) { return "actual value contains {$error}"; }

        $visited = [];
        $error = $this->_checkValueComparable($expected, $visited);
        if ($error !== null) { return "expected value contains {$error}"; }

        return null;
    }

    // :: MIXED, ARRAY -> ?STRING
    // Recursively checks a value for resources, closures, and cycles
    private function _checkValueComparable(mixed $value, array &$visited): ?string {
        if (is_resource($value)) { return 'a resource'; }
        if ($value instanceof \Closure) { return 'a closure'; }

        if (is_array($value)) {
            foreach ($value as $item) {
                $error = $this->_checkValueComparable($item, $visited);
                if ($error !== null) { return $error; }
            }
            return null;
        }

        if (is_object($value)) {
            $id = spl_object_id($value);
            if (isset($visited[$id])) { return 'a cyclic reference'; }
            $visited[$id] = true;

            $reflection = new \ReflectionObject($value);
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                $error = $this->_checkValueComparable($prop->getValue($value), $visited);
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
    // Delivers the report via the configured output mode
    private function _deliver(array $report, array $config): string|array|null {
        $output = $config['output'];

        try {
            return match ($output) {
                'console' => $this->_deliverConsole($report),
                'return' => ConsoleFormatter::format($report),
                'return-json' => $report,
                'json' => $this->_deliverJson($report),
                'junit' => $this->_deliverJunit($report, $config),
            };
        } catch (\Throwable $e) {
            throw new TestError('FORMATTER_ERROR', "Formatter '{$output}' threw an exception", [
                'formatter' => $output,
                'exception' => TestResult::formatException($e, $config['trace']),
                'report' => $report,
            ]);
        }
    }

    // :: ARRAY -> NULL
    private function _deliverConsole(array $report): null {
        echo ConsoleFormatter::format($report);
        return null;
    }

    // :: ARRAY -> NULL
    private function _deliverJson(array $report): null {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return null;
    }

    // :: ARRAY, ARRAY -> NULL
    private function _deliverJunit(array $report, array $config): null {
        echo JunitFormatter::format($report, $config['trace']);
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
            TestStep::TYPE_ASSERT => TestResult::assertResult($name, TestResult::STATUS_SKIP, 0, null, null),
            TestStep::TYPE_CHAIN => TestResult::chainResult($name, TestResult::STATUS_SKIP, 0, null, null, [], [
                'passed' => 0, 'failed' => 0, 'skipped' => 0, 'recovered' => 0, 'errored' => 0, 'total' => 0,
            ]),
            default => TestResult::stepResult($type, $name, TestResult::STATUS_SKIP, 0),
        };
    }

    /**
     *
     * Static Methods
     *
     */

    // Static Factory Method :: STRING -> CTGTest
    // Creates a new test definition with the given name
    // NOTE: Name is stored raw; validation deferred to start()
    public static function init(string $name): self {
        return new self($name);
    }
}
