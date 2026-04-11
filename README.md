# ctg-php-test

`ctg-php-test` is a pipeline-based test framework for PHP. A test is an
ordered sequence of operations that thread a shared state through
stages, asserts, chains, and skip directives. The framework orchestrates
execution, evaluates correctness via predicates, and returns a
structured result state. It does not format output, collect results
across pipelines, or ship a test runner — those are the caller's
responsibility.

**Key properties:**

- **Pipeline model** — tests are ordered operations against a shared
  `CTGTestState` carrier
- **First-class predicates** — assertions use `CTGTestPredicate`
  instances, not raw values
- **Composable chains** — pipelines can run other pipelines as
  sub-operations with label nesting
- **Three canonical statuses** — `PASS`, `FAIL`, `ERROR` (skipped
  operations carry a separate `skipped: true` flag)
- **Zero production dependencies** — only PHP's standard library
- **PHPUnit as the test oracle** — dev dependency only; PHPUnit
  verifies the framework's own correctness

## Requirements

- PHP >= 8.1
- `declare(strict_types=1)` compatible code
- Zero production dependencies

## Install

Add the GitHub repository to your `composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/claymoretechgroup/ctg-php-test" }
    ]
}
```

Then require the package:

```bash
composer require ctg/php-test
```

## Quick Start

```php
<?php
declare(strict_types=1);

require_once 'vendor/autoload.php';

use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\Predicates\CTGTestPredicates;

$state = CTGTest::init('arithmetic')
    ->stage('double', fn(CTGTestState $s) => $s->getSubject() * 2)
    ->assert('is 10', fn(CTGTestState $s) => $s->getSubject(), CTGTestPredicates::equals(10))
    ->assert('is even', fn(CTGTestState $s) => $s->getSubject() % 2, CTGTestPredicates::equals(0))
    ->start(5);

// $state is a CTGTestState — inspect its results
foreach ($state->getResults() as $result) {
    echo implode(' > ', $result->_label) . ' ' . $result->_status->value . "\n";
}
```

## Core Concepts

### State

`CTGTestState` is the carrier that threads through the pipeline. It
holds the subject, a computed slot for assertions, and the accumulated
results list.

- `getSubject(): mixed` — read the current subject
- `getComputed(): mixed` — read the most recent assert's computed value
- `getResults(): CTGTestResult[]` — read the full result trace
- `getLabel(): string` — read the pipeline's label

### Stage

A stage transforms the subject. The handler receives the current state
and returns a new subject value, which the framework writes to
`state.subject`.

```php
->stage('load user', fn(CTGTestState $s) => User::find($s->getSubject()))
```

### Assert

An assert computes a value and hands it to a predicate for evaluation.
The handler returns the value to be checked; the framework writes it to
`state.computed`, then calls the predicate and records the outcome as
`PASS`, `FAIL`, or `ERROR`.

```php
->assert(
    'has email',
    fn(CTGTestState $s) => $s->getSubject()->email,
    CTGTestPredicates::contains('@')
)
```

**Assertions require `CTGTestPredicate` instances.** Raw values are not
auto-wrapped. Passing a bare value or callable as the predicate argument
is a validation error (`INVALID_EXPECTED_OUTCOME`, code 1003). Use
`CTGTestPredicates::*` convenience builders, or construct predicates
directly with `CTGTestPredicate::init(expectedOutcome, evaluateClosure)`.

### Chain

A chain runs another pipeline against the same state. The chained
pipeline's results are appended to the outer state's results list with
the chain's label prepended to each child's label array.

```php
$userValidation = CTGTest::init('user validation')
    ->assert('has id', fn(CTGTestState $s) => $s->getSubject()->id, CTGTestPredicates::isInstanceOf(Uuid::class))
    ->assert('has email', fn(CTGTestState $s) => $s->getSubject()->email, CTGTestPredicates::contains('@'));

CTGTest::init('create user flow')
    ->stage('create', fn(CTGTestState $s) => User::create($s->getSubject()))
    ->chain('validate', $userValidation)
    ->stage('persist', fn(CTGTestState $s) => $s->getSubject()->save())
    ->start($formData);
```

A chain that runs normally produces no result entry of its own — only
its child results with the chain label prepended. A chain that is
skipped produces a single `skipped: true` entry.

### Skip

A skip directive gates a target operation by label. When the condition
holds (or is omitted for unconditional skip), the target does not
execute and a `skipped: true` result is recorded in its place. Skips
have no label of their own — they are identified by the target they
gate, and they can appear at any position in the pipeline relative to
the target.

```php
CTGTest::init('conditional migration')
    ->stage('connect', fn(CTGTestState $s) => DB::connect($s->getSubject()))
    ->stage('migrate', fn(CTGTestState $s) => $s->getSubject()->migrate())
    ->skip('migrate', fn(CTGTestState $s) => $s->getSubject()->isProduction())
    ->start($config);
```

### Predicates

`CTGTestPredicates` ships with 16 convenience builders:

| Builder | Meaning |
|---------|---------|
| `equals($v)` | `===` equality |
| `notEquals($v)` | `!==` inequality |
| `isNull()` / `isNotNull()` | null checks |
| `isTrue()` / `isFalse()` | strict bool checks |
| `isTruthy()` / `isFalsy()` | loose bool checks |
| `isInstanceOf($class)` | instance check |
| `isType($type)` | `gettype()` check |
| `greaterThan($v)` / `lessThan($v)` | numeric comparison |
| `contains($substr)` | string substring |
| `matchesPattern($regex)` | regex match |
| `hasCount($n)` | Countable/array size |
| `satisfies($fn)` | custom closure predicate |

For anything else, use `CTGTestPredicate::init($expected, $closure)`
directly.

### Pipeline Execution

`start(mixed $subject, array $config = []): CTGTestState` validates the
pipeline, executes every operation in order, and returns the final
state. **The pipeline never writes to stdout, chooses a formatter, or
collects results globally.** The caller owns delivery.

### Configuration

`start()` accepts exactly two config keys:

| Key | Type | Default | Meaning |
|-----|------|---------|---------|
| `haltOnFailure` | bool | `true` | Stop after the first `FAIL` or `ERROR` result |
| `timeout` | int | `5000` | Per-operation timeout in milliseconds; `0` disables |

Any other key throws `INVALID_CONFIG` (code 1002).

### Results and Formatters

The pipeline returns `CTGTestState`. To format it, pass the state to a
formatter. The framework ships one reference formatter — a text
renderer at `CTG\Test\Formatters\CTGTestTextFormatter`:

```php
use CTG\Test\Formatters\CTGTestTextFormatter;

$state = $pipeline->start($subject);
echo CTGTestTextFormatter::format($state);
```

Custom formatters implement `CTGTestFormatterInterface`:

```php
interface CTGTestFormatterInterface {
    public static function format(CTGTestState $state): string;
}
```

## Error Handling

All validation errors are thrown from `start()` as `CTGTestError`
instances with canonical codes stable across language implementations:

| Constant | Code | Meaning |
|----------|------|---------|
| `INVALID_OPERATION` | 1000 | Malformed stage/assert/chain definition |
| `INVALID_CHAIN` | 1001 | Chain target is not a `CTGTest` instance |
| `INVALID_CONFIG` | 1002 | Malformed config argument |
| `INVALID_EXPECTED_OUTCOME` | 1003 | Assert third arg is not a `CTGTestPredicate` |
| `INVALID_SKIP` | 1004 | Malformed skip directive |
| `FORMATTER_ERROR` | 2000 | Formatter failed (reserved for formatter implementations) |
| `RUNNER_ERROR` | 2001 | Reserved for caller-side runner scripts |
| `CHAIN_DEPTH_EXCEEDED` | 1100 | Implementation guardrail: chain nesting exceeded `MAX_CHAIN_DEPTH` (64) |

```php
use CTG\Test\CTGTestError;

try {
    $test->start($subject);
} catch (CTGTestError $e) {
    echo $e->type;          // 'INVALID_OPERATION'
    echo $e->getCode();     // 1000
    echo $e->msg;           // human-readable message
    print_r($e->data);      // structured diagnostic context
}
```

Errors thrown *inside* operation handlers (user code that fails during
stage/assert/predicate execution) do not propagate out of `start()` —
they are caught and recorded as `ERROR` status results on the affected
operation, and the pipeline continues or halts according to
`haltOnFailure`.

## Testing the Framework Itself

Tests for `ctg-php-test` use PHPUnit as an independent oracle. This
avoids the bootstrapping problem where a bug in result recording would
be invisible to tests using the same recording machinery.

```bash
composer install            # installs PHPUnit as a dev dependency
./vendor/bin/phpunit        # runs the full suite
```

PHPUnit is a dev-only dependency — it does not ship with the library
and is not required by consumers of `ctg-php-test`.

## Production Readiness and Operational Considerations

The framework is production-ready for its designed scope — running
sequential pipelines against a shared state and reporting structured
results. A few things sit deliberately outside that scope and become
the caller's responsibility when running at scale.

### Timeout is post-hoc, not preemptive

`CTGTest`'s timeout enforcement measures elapsed time *after* an
operation's closure returns. A truly blocking or infinite operation —
an unbounded loop, a network call with no socket timeout, a DB query
with no query timeout — **will hang the pipeline indefinitely**. The
configured `timeout` is a budget observer, not a hard interruption.

**Mitigations callers should apply:**

- Set low-level timeouts on I/O: `curl_setopt(..., CURLOPT_TIMEOUT)`,
  `stream_set_timeout`, `PDO::ATTR_TIMEOUT`, etc.
- Avoid unbounded loops inside operation handlers
- Run your CI runner with an outer process-level timeout (e.g., the
  `timeout` shell command, CI job timeout) as a hard backstop
- Consider a runner that executes each pipeline in a subprocess and
  can hard-kill stuck processes

### No built-in isolation or cleanup for side effects

The framework's timeout rollback guarantee covers `state.subject` and
`state.computed` only. It does **not** cover external side effects
(database writes, file system changes, network calls) or mutations to
shared objects that the handler performed before failing.

**Mitigations:**

- Wrap side-effecting operations in teardown stages at the end of the
  pipeline
- Use chains to compose setup + body + teardown sub-pipelines
- Run tests in disposable environments (Docker, database transactions
  that roll back, tmpfs directories)

```php
$fixture = CTGTest::init('with database')
    ->stage('begin transaction', fn(CTGTestState $s) => $s->getSubject()->beginTransaction())
    ->chain('body', $testBody)
    ->stage('rollback', fn(CTGTestState $s) => $s->getSubject()->rollBack());
```

### No built-in parallel orchestration

Pipelines are sequential by contract. Parallelism across independent
pipelines is a runner concern, not a framework concern. For production
test suites, build a runner layer that shards pipelines across
processes and aggregates results.

### No built-in runner, collector, or delivery mechanism

`CTGTest::start()` returns a `CTGTestState` and stops. Writing to
stdout, uploading to a dashboard, setting CI exit codes, and
aggregating across multiple pipelines are all caller-side concerns.
The design doc explicitly treats a built-in runner as an anti-pattern
because a single-collector runner cannot compose: inner pipelines
would leak into outer pipeline results.

Write a thin runner for your use case that:

1. Discovers your test files
2. Invokes each pipeline's `start()`
3. Formats the returned state with whatever formatter is appropriate
4. Aggregates pass/fail counts and sets a meaningful exit code
5. Optionally retries known-flaky pipelines

### Result metadata is thin

`CTGTestResult` carries exactly six canonical fields: `label`,
`skipped`, `status`, `computedValue`, `expectedOutcome`, `error`. It
does not include `duration_ms`, tags, or environment info.

This is deliberate — the canonical shape stays small for cross-language
portability. Richer metadata should be added via:

- **Extension:** subclass `CTGTestResult` to add fields like
  `$_durationMs`; subclass `CTGTest` to populate them. Canonical fields
  must be preserved.
- **Runner envelope:** wrap state output with `{environment, commit,
  state}` at the CI layer.

### Extensions and conformance testing

When you build domain-specific libraries on top of `ctg-php-test`
(browser testing, HTTP testing, database testing), the extension
surfaces are:

- **STATE** — subclass `CTGTestState` to add domain fields
- **PREDICATE** — build predicates via `CTGTestPredicate::init()` or
  subclass for richer types
- **PIPELINE** — subclass `CTGTest` to add builder methods that
  internally register stage/assert operations

Extensions should ship with **conformance tests** — a reusable test
suite that verifies the extension preserves the core semantic
invariants (skip behavior, timeout precedence, label path rules, error
code assignments). Run these conformance tests against the extension's
pipeline subclass to catch semantic regressions.

## Architecture

`ctg-php-test` is implemented against a language-agnostic design
document shared across language implementations. The PHP-specific
realization is documented in detail at
[`docs/spec.v2.2.md`](docs/spec.v2.2.md), which describes how each
primitive and procedure from the design doc maps to concrete PHP
classes, what every validation rule throws, and what every formatter
emits. A reader building an extension or debugging obscure behavior
should start there.

## License

MIT — see [LICENSE](LICENSE).
