# CTGTest

Class that implements a composable test pipeline. CTGTest instances are definitions -- they hold no subject and carry no runtime state. Steps execute only when `start()` is called with a subject.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| _name | STRING | Name of this test pipeline (trimmed) |
| _steps | [testStep] | Ordered list of step definitions |
| _skips | ARRAY | Skip directives, each with name and optional predicate |

## Methods

### CTGTest.init :: STRING -> ctgTest

Static factory that creates a new test definition. The name is trimmed and stored. Empty names are rejected when `start()` is called. Nothing executes at definition time -- all builder methods store raw definitions and return self for chaining.

```php
$test = CTGTest::init('User Registration');
```

### ctgTest.stage :: STRING, (MIXED -> MIXED), ?(ERROR -> MIXED) -> SELF

Adds a stage step to the pipeline. At execution time, the callable receives the current subject and returns a transformed subject that becomes the new subject for subsequent steps. If an error handler is provided, it receives the exception when the callable throws and its return value replaces the subject (status becomes `recovered`). If both the callable and the error handler throw, the step status is `error` with a `caused_by` field linking the two exceptions. Chainable.

```php
$test = CTGTest::init('DB setup')
    ->stage('connect', fn($config) => DB::connect($config))
    ->stage('insert', fn($db) => $db->create('users', $data));
```

### ctgTest.assert :: STRING, (MIXED -> MIXED), MIXED|[MIXED], ?(ERROR -> MIXED) -> SELF

Adds an assert step to the pipeline. At execution time, the callable receives the current subject and returns a value that is compared against the expected value. Assert does not mutate the subject -- multiple asserts in a row all operate on the same subject. Expected is always a value, never callable. For predicate-style checks, the callable returns bool and expected is `true`. If expected is an array, it is treated as a candidate set -- the assert passes if actual equals any one candidate. Chainable.

```php
$test = CTGTest::init('checks')
    ->assert('is int', fn($x) => is_int($x), true)
    ->assert('valid status', fn($x) => $x->getStatus(), ['active', 'pending']);
```

### ctgTest.chain :: STRING, ctgTest -> SELF

Composes another CTGTest definition into the pipeline as a named group. At execution time, the chained test's steps execute inline against the current subject. Subject mutations carry forward to the outer pipeline. The chain result's name comes from the first argument, not the child test's init name. Chained tests inherit the parent's config. Chainable.

```php
$validate = CTGTest::init('validate')
    ->assert('has id', fn($r) => isset($r['id']), true);

$test = CTGTest::init('CRUD')
    ->stage('create', fn($db) => $db->create('users', $data))
    ->chain('validate record', $validate);
```

### ctgTest.skip :: STRING, ?(MIXED -> BOOL) -> SELF

Marks a step for skipping by name. Skip is pure metadata -- not a step in the pipeline. Targets `stage`, `assert`, or `chain` names at the current level only. If a predicate is provided, it receives the current subject at execution time and the step is skipped only when the predicate returns `true`. Can be called anywhere in the definition; order doesn't matter. Chainable.

```php
$test = CTGTest::init('conditional')
    ->stage('migrate', fn($db) => $db->migrate())
    ->skip('migrate', fn($db) => $db->isProduction());
```

### ctgTest.start :: MIXED, ?ARRAY -> STRING|ARRAY|NULL

Executes the pipeline. This is the only method that triggers execution. Validates the entire pipeline at the beginning before any steps run -- definition-time errors (1xxx) are thrown here. Accepts a subject and an optional config array. Returns a string for `return` output, an array for `return-json`, or null for `console`/`json`/`junit` (which echo to stdout).

```php
// Default: echoes to stdout
$test->start($subject);

// Capture structured report
$report = $test->start($subject, ['output' => 'return-json']);
```

#### Config Options

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| output | STRING | `'console'` | Output mode: console, return, return-json, json, junit |
| haltOnFailure | BOOL | `true` | Stop on first fail or error. Recursive at every level. |
| strict | BOOL | `true` | Use `===` (true) or `==` (false) for comparisons |
| trace | BOOL | `false` | Include stack traces in exception structures |

#### Output Modes

| Value | Returns | Side Effect |
|-------|---------|-------------|
| `'console'` | NULL | Echoes human-readable report to stdout |
| `'return'` | STRING | None |
| `'return-json'` | ARRAY | None |
| `'json'` | NULL | Echoes JSON to stdout |
| `'junit'` | NULL | Echoes JUnit XML to stdout |

### ctgTest.compare :: MIXED, MIXED, BOOL -> BOOL

Protected method that performs the actual comparison between actual and expected values. Uses `===` when strict is true, `==` when false. Override this method to add custom matcher support.

```php
class CustomTest extends CTGTest {
    protected function compare(mixed $actual, mixed $expected, bool $strict): bool {
        // custom comparison logic
    }
}
```

## Report Structure

The root report has no `type` field -- it is a report, not a step:

```php
[
    'name' => 'DB CRUD',
    'status' => 'fail',
    'passed' => 4,
    'failed' => 1,
    'skipped' => 0,
    'recovered' => 1,
    'errored' => 0,
    'total' => 6,
    'duration_ms' => 23,
    'steps' => [ ... ],
]
```

Step results have `type`, `name`, `status`, `duration_ms`, `message`, `exception`. Assert steps add `actual` and `expected`. Chain steps add nested `steps` and counts.

## Status Semantics

| Status | Meaning | Failing? |
|--------|---------|----------|
| pass | Executed successfully | No |
| fail | Assert returned wrong value | Yes |
| error | Function threw before producing a result | Yes |
| recovered | Handler returned replacement value (degraded success) | No |
| skip | Not executed, intentionally | No |

Severity order for aggregation: `error` > `fail` > `recovered` > `pass` > `skip`. Empty pipelines are `pass` by special case.
